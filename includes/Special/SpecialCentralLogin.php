<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthSessionProvider;
use Exception;
use IBufferingStatsdDataFactory;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\LoginCompleteHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Session\Session;
use MediaWiki\StubObject\StubGlobalUser;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;
use Psr\Log\LoggerInterface;
use SpecialPage;
use UnlistedSpecialPage;
use User;
use Wikimedia\ScopedCallback;

/**
 * Special page for handling the central login process, which is done right after a successful
 * login to create a session on the central wiki.
 *
 * It does different things depending on the subpage name:
 * - /start: Creates the stub central session and redirects to /complete. {@see self::doLoginStart()}
 * - /complete: Unstubs the central session, and redirects back to where the central login was
 *   started from. {@see self::doLoginComplete()}
 *
 * @see LoginCompleteHookHandler::onUserLoginComplete()
 * @see LoginCompleteHookHandler::onTempUserCreatedRedirect()
 * @see SpecialCentralAutoLogin
 * @see https://www.mediawiki.org/wiki/Extension:CentralAuth/authentication
 */
class SpecialCentralLogin extends UnlistedSpecialPage {

	/** @var Session */
	protected $session = null;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param CentralAuthSessionManager $sessionManager
	 * @param CentralAuthUtilityService $utilityService
	 */
	public function __construct(
		IBufferingStatsdDataFactory $statsdDataFactory,
		CentralAuthSessionManager $sessionManager,
		CentralAuthUtilityService $utilityService
	) {
		parent::__construct( 'CentralLogin' );
		$this->statsdDataFactory = $statsdDataFactory;
		$this->sessionManager = $sessionManager;
		$this->utilityService = $utilityService;
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	public function execute( $subpage ) {
		if ( in_array( $subpage, [ 'start', 'complete' ], true ) ) {
			$this->logger->debug( 'CentralLogin step {step}', [
				'step' => $subpage,
			] );
		}

		$this->setHeaders();
		$this->getOutput()->disallowUserJs(); // just in case...

		// Check session, if possible
		$session = $this->getRequest()->getSession();
		if ( !$session->getProvider() instanceof CentralAuthSessionProvider ) {
			$this->showError(
				'centralauth-error-wrongprovider',
				$session->getProvider()->describe( $this->getLanguage() )
			);
			return;
		}
		$this->session = $session;

		$token = $this->getRequest()->getVal( 'token' );

		if ( $subpage === 'start' ) {
			$this->doLoginStart( $token );
		} elseif ( $subpage === 'complete' ) {
			$this->doLoginComplete( $token );
		} else {
			// invalid request
			$title = SpecialPage::getTitleFor( 'Userlogin' );
			$this->getOutput()->redirect( $title->getLocalURL() );
		}

		// Auto-submit and back links
		$this->getOutput()->addModules( 'ext.centralauth' );
	}

	/**
	 * First step of central login. Runs on the central login wiki.
	 * - Reads the token store data stashed by getRedirectUrl() (using the token passed in the URL).
	 * - Creates a stub central session (basically a session that stores the username under
	 *   'pending_name' instead of 'user', and is not valid for authentication) in the central
	 *   session backend.
	 * - Creates the local session (which makes CentralAuthSessionProvider store the session
	 *   metadata in the normal session backend, and issue normal and central session cookies).
	 *   Since CentralAuthSessionProvider checks the central session when validating the local
	 *   session, in effect this will also be a stub session until the central session is unstubbed.
	 *   The "remember me" flag is forced off, since that would use the token mechanism which
	 *   doesn't require a valid session and so would ignore stubbing. It will be updated later
	 *   via Special:CentralAutoLogin/refreshCookies.
	 * - Redirects to /complete, and uses the token store and a GET parameter to pass the session
	 *   ID and the login secret from getRedirectUrl() in a secure way. It uses the
	 *   CentralAuthSilentLoginRedirect hook so the redirect can take into account URL modifications
	 *   not understood by WikiMap, such as a mobile domain.
	 *
	 * @param string $token
	 * @throws Exception
	 *
	 * @see LoginCompleteHookHandler::getRedirectUrl()
	 * @see CentralAuthSessionProvider
	 * @see CentralAuthSilentLoginRedirect
	 * @see SpecialCentralAutoLogin
	 */
	protected function doLoginStart( $token ) {
		$tokenStore = $this->sessionManager->getTokenStore();

		$key = $this->sessionManager->makeTokenKey( 'central-login-start-token', $token );
		$info = $this->utilityService->getKeyValueUponExistence( $tokenStore, $key );
		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		$getException = static function ( CentralAuthUser $centralUser, UserIdentity $user, array $info ) {
			if ( !$centralUser->exists() ) { // sanity
				return new Exception( "Global user '{$info['name']}' does not exist." );
			} elseif ( $centralUser->getId() !== $info['guid'] ) { // sanity
				return new Exception( "Global user does not have ID '{$info['guid']}'." );
			} elseif ( !$centralUser->isAttached() && $user->isRegistered() ) { // sanity
				return new Exception( "User '{$info['name']}' exists locally but is not attached." );
			}
			return null;
		};

		$user = User::newFromName( $info['name'] );
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $getException( $centralUser, $user, $info ) ) {
			// Retry from primary database. Central login is done right after user creation so lag problems
			// are common.
			$user = User::newFromName( $info['name'] );
			$user->load( User::READ_LATEST );
			$centralUser = CentralAuthUser::getPrimaryInstance( $user );
			$e = $getException( $centralUser, $user, $info );
			if ( $e ) {
				throw $e;
			}
		}

		$session = $this->sessionManager->getCentralSession();
		// If the user has a full session, make sure that the names match up.
		// If they do, then send the user back to the "login successful" page.
		// We want to avoid overwriting any session that may already exist.
		$createStubSession = true;
		if ( isset( $session['user'] ) ) { // fully initialized session
			if ( $session['user'] !== $centralUser->getName() ) {
				// User is trying to switch accounts. Let them do so by
				// creating a new central session.
			} else {
				// They're already logged in to the target account, don't stomp
				// on the existing session! (T125139)
				$createStubSession = false;
			}
		// If the user has a stub session, error out if the names do not match up
		} elseif ( isset( $session['pending_name'] ) ) { // stub session
			if ( $session['pending_name'] !== $centralUser->getName() ) {
				$this->showError( 'centralauth-error-token-wronguser' );
				return;
			}
		}

		// Delete the temporary token
		$tokenStore->delete( $key );

		if ( $createStubSession ) {
			// Start an unusable placeholder session stub and send a cookie.
			// The cookie will not be usable until the session is unstubbed.
			// Note: the "remember me" token must be dealt with later (security).
			$delay = $this->session->delaySave();
			$this->session->setUser( User::newFromName( $centralUser->getName() ) );
			$newSessionId = $this->sessionManager->setCentralSession( [
				'pending_name' => $centralUser->getName(),
				'pending_guid' => $centralUser->getId()
			], true, $this->session );
			$this->session->persist();
			ScopedCallback::consume( $delay );
		} else {
			// Since the full central session already exists, reuse it.
			$newSessionId = $session['sessionId'];
		}

		// Create a new token to pass to Special:CentralLogin/complete (local wiki).
		$token = MWCryptRand::generateHex( 32 );
		$key = $this->sessionManager->makeTokenKey( 'central-login-complete-token', $token );
		$data = [
			'sessionId' => $newSessionId,
			'secret'    => $info['secret'] // should match the login attempt secret
		];
		$tokenStore = $this->sessionManager->getTokenStore();
		$tokenStore->set( $key, $data, $tokenStore::TTL_MINUTE );

		$query = [ 'token' => $token ];

		$wiki = WikiMap::getWiki( $info['wikiId'] );
		$url = $wiki->getCanonicalUrl( 'Special:CentralLogin/complete' );
		$url = wfAppendQuery( $url, $query );

		$caHookRunner = new CentralAuthHookRunner( $this->getHookContainer() );
		$caHookRunner->onCentralAuthSilentLoginRedirect( $centralUser, $url, $info );

		$this->getOutput()->redirect( $url );
	}

	/**
	 * Second step of central login. Runs on the wiki where the original login happened.
	 * - Verifies the login secret that was passed along the redirect chain via the token store,
	 *   against the login secret that was stored in the local session by getRedirectUrl().
	 * - Unstubs the central session, and sets the local session and issues cookies for it.
	 * - Redirects and sets up edge login pixels to be shown on the next request.
	 *   Lets extensions influence the redirect target via the CentralAuthPostLoginRedirect hook.
	 *
	 * Security-wise, we know we are on the same redirect chain as the original login because of
	 * the tokenstore data. This wouldn't necessarily mean the user is the same - an attacker might
	 * stop at some step in the redirect, and trick another user to continue from that step. But we
	 * know this is the same user who did the login, because of the login secret in the local session.
	 *
	 * @param string $token
	 * @throws Exception
	 *
	 * @see LoginCompleteHookHandler::getRedirectUrl()
	 * @see CentralAuthPostLoginRedirect
	 */
	protected function doLoginComplete( $token ) {
		$request = $this->getRequest();
		$tokenStore = $this->sessionManager->getTokenStore();

		$key = $this->sessionManager->makeTokenKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt'; // session key

		$info = $this->utilityService->getKeyValueUponExistence( $tokenStore, $key );

		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		// Get the user's current login attempt information
		$attempt = $request->getSessionData( $skey );
		if ( !isset( $attempt['secret'] ) ) {
			$this->showError( 'centralauth-error-nologinattempt' );
			return;
		}

		// Make sure this token belongs to the user who spawned the tokens.
		// This prevents users from giving out links that log people in as them.
		if ( $info['secret'] !== $attempt['secret'] ) {
			$this->showError( 'centralauth-error-token-wrongattempt' );
			return;
		}

		$getException = static function ( CentralAuthUser $centralUser, UserIdentity $user ) {
			if ( !$user->getId() ) { // sanity
				return new Exception( "The user account logged into does not exist." );
			}
			if ( !$centralUser->getId() ) { // sanity
				return new Exception( "The central user account does not exist." );
			}
			if ( !$centralUser->isAttached() ) { // sanity
				return new Exception( "The user account is not attached." );
			}
			return null;
		};

		$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $getException( $centralUser, $user ) ) {
			$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
			$user->load( User::READ_LATEST );
			$centralUser = CentralAuthUser::getPrimaryInstance( $user );
			$e = $getException( $centralUser, $user );
			if ( $e ) {
				throw $e;
			}
		}

		// Delete the temporary token
		$tokenStore->delete( $key );

		// Fully initialize the stub central user session and send the domain cookie.
		// This is a bit tricky. We start with a stub session with 'pending_name' and no 'user'.
		// CentralAuthSessionManager::setCentralSession() preserves most of the previous data,
		// but drops 'pending_name'. CentralAuthSessionProvider::persistSession() then sets 'user'
		// because it doesn't see 'pending_name'.
		$delay = $this->session->delaySave();
		$this->session->setUser( User::newFromName( $centralUser->getName() ) );
		$this->session->setRememberUser( (bool)$attempt['remember'] );
		$this->sessionManager->setCentralSession( [
			'remember' => $attempt['remember'],
		], $info['sessionId'], $this->session );
		$this->session->persist();
		ScopedCallback::consume( $delay );

		// Remove the "current login attempt" information
		$request->setSessionData( $skey, null );

		// Update the current user global $wgUser,
		// bypassing deprecation warnings because CentralAuth is the one place outside
		// of core where we still support writing to $wgUser
		// See T291515
		StubGlobalUser::setUser( $user );

		// This should set it for OutputPage and the Skin
		// which is needed or the personal links will be wrong.
		$this->getContext()->setUser( $user );

		LoggerFactory::getInstance( 'authevents' )->info( 'Central login attempt', [
			'event' => 'centrallogin',
			'successful' => true,
			'extension' => 'CentralAuth',
		] );

		$unusedReference = '';
		// Allow other extensions to modify the returnTo and returnToQuery
		$caHookRunner = new CentralAuthHookRunner( $this->getHookContainer() );
		$caHookRunner->onCentralAuthPostLoginRedirect(
			$attempt['returnTo'],
			$attempt['returnToQuery'],
			true,
			$attempt['type'],
			$unusedReference
		);

		// Mark the session to include the edge login imgs on the next pageview
		$this->logger->debug( 'Edge login on the next pageview after CentralLogin' );
		$request->setSessionData( 'CentralAuthDoEdgeLogin', true );

		$returnToTitle = Title::newFromText( $attempt['returnTo'] ) ?: Title::newMainPage();
		$redirectUrl = $returnToTitle->getFullUrlForRedirect( $attempt['returnToQuery'] )
			. $attempt['returnToAnchor'];
		$this->getOutput()->redirect( $redirectUrl );
	}

	protected function showError( ...$args ) {
		LoggerFactory::getInstance( 'authevents' )->info( 'Central login attempt', [
			'event' => 'centrallogin',
			'successful' => false,
			'status' => $args[0],
			'extension' => 'CentralAuth',
		] );
		$this->statsdDataFactory->increment( 'centralauth.centrallogin_errors.' . $args[0] );
		$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>', $args );
		$this->getOutput()->addHtml( '<p id="centralauth-backlink-section"></p>' ); // JS only
	}
}
