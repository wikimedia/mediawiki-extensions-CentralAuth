<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthSessionProvider;
use Exception;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\LoginCompleteHookHandler;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Session\Session;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\StubObject\StubGlobalUser;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Rdbms\IDBAccessObject;
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

	protected ?Session $session = null;

	private CentralAuthSessionManager $sessionManager;
	private CentralAuthTokenManager $tokenManager;
	private LoggerInterface $logger;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		CentralAuthSessionManager $sessionManager,
		CentralAuthTokenManager $tokenManager,
		SharedDomainUtils $sharedDomainUtils
	) {
		parent::__construct( 'CentralLogin' );
		$this->sessionManager = $sessionManager;
		$this->tokenManager = $tokenManager;
		$this->sharedDomainUtils = $sharedDomainUtils;
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	/** @inheritDoc */
	public function execute( $subpage ) {
		$token = $this->getRequest()->getVal( 'token' );
		if ( $token === null || !in_array( $subpage, [ 'start', 'complete' ], true ) ) {
			// invalid request
			$title = SpecialPage::getTitleFor( 'Userlogin' );
			$this->getOutput()->redirect( $title->getLocalURL() );
			return;
		}

		$this->logger->debug( 'CentralLogin step {step}', [
			'step' => $subpage,
		] );

		$this->setHeaders();
		$this->getOutput()->disallowUserJs();

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

		// Auto-submit and back links
		$this->getOutput()->addModules( 'ext.centralauth' );

		if ( $subpage === 'complete' ) {
			$this->doLoginComplete( $token );
			return;
		}
		$this->doLoginStart( $token );
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
		$info = $this->tokenManager->detokenizeAndDelete( $token, 'central-login-start-token' );
		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		$getException = static function ( CentralAuthUser $centralUser, UserIdentity $user, array $info ) {
			if ( !$centralUser->exists() ) {
				return new RuntimeException( "Global user '{$info['name']}' does not exist." );
			}

			if ( $centralUser->getId() !== $info['guid'] ) {
				return new RuntimeException( "Global user does not have ID '{$info['guid']}'." );
			}

			if ( !$centralUser->isAttached() && $user->isRegistered() ) {
				return new RuntimeException( "User '{$info['name']}' exists locally but is not attached." );
			}

			return null;
		};

		$user = User::newFromName( $info['name'] );
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $getException( $centralUser, $user, $info ) ) {
			// Retry from primary database. Central login is done right after user creation so lag problems
			// are common.
			$user = User::newFromName( $info['name'] );
			$user->load( IDBAccessObject::READ_LATEST );
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
		if ( isset( $session['user'] ) ) {
			// fully initialized session
			if ( $session['user'] !== $centralUser->getName() ) {
				if ( $user->isNamed() ) {
					// If the user is probably trying to switch accounts. Let them do so by
					// creating a new central session.
				} else {
					// Temp users can't switch accounts since they have no way of logging in. If
					// this is happening, the user ended up with different temp user identities on
					// different wikis. Not much we can do about it but let's at least log it.
					$this->logger->info( 'Temp user conflict: {old} / {new}', [
						'old' => $session['user'],
						'new' => $centralUser->getName(),
					] );
				}
			} else {
				// They're already logged in to the target account, don't stomp
				// on the existing session! (T125139)
				$createStubSession = false;
			}
		// If the user has a stub session, error out if the names do not match up
		} elseif ( isset( $session['pending_name'] ) ) {
			// stub session
			if ( $session['pending_name'] !== $centralUser->getName() ) {
				$this->showError( 'centralauth-error-token-wronguser' );
				return;
			}
		}

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
		$data = [
			'sessionId' => $newSessionId,
			// should match the login attempt secret
			'secret'    => $info['secret']
		];
		$token = $this->tokenManager->tokenize( $data, 'central-login-complete-token' );

		$query = [
			'token' => $token,
			'usesul3' => $this->sharedDomainUtils->isSul3Enabled( $this->getRequest() ) ? 1 : 0,
		];

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

		$sessionKey = 'CentralAuth:autologin:current-attempt';
		$info = $this->tokenManager->detokenizeAndDelete( $token, 'central-login-complete-token' );

		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		// Get the user's current login attempt information
		$attempt = $request->getSessionData( $sessionKey );
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
			if ( !$user->getId() ) {
				return new RuntimeException( "The user account logged into does not exist." );
			}
			if ( !$centralUser->getId() ) {
				return new RuntimeException( "The central user account does not exist." );
			}
			if ( !$centralUser->isAttached() ) {
				return new RuntimeException( "The user account is not attached." );
			}
			return null;
		};

		$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $getException( $centralUser, $user ) ) {
			$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
			$user->load( IDBAccessObject::READ_LATEST );
			$centralUser = CentralAuthUser::getPrimaryInstance( $user );
			$e = $getException( $centralUser, $user );
			if ( $e ) {
				throw $e;
			}
		}

		// Fully initialize the stub central user session and send the domain cookie.
		// This is a bit tricky. We start with a stub session with 'pending_name' and no 'user'.
		// CentralAuthSessionManager::setCentralSession() preserves most of the previous data,
		// but drops 'pending_name'. CentralAuthSessionProvider::persistSession() then sets 'user'
		// because it doesn't see 'pending_name'.
		// FIXME what are all these session changes for? The session was already set during login,
		//   all these should be noops, only setting the central session is needed.
		$delay = $this->session->delaySave();
		$this->session->setUser( User::newFromName( $centralUser->getName() ) );
		$this->session->setRememberUser( (bool)$attempt['remember'] );
		$this->sessionManager->setCentralSession( [
			'remember' => $attempt['remember'],
		], $info['sessionId'], $this->session );
		$this->session->persist();
		ScopedCallback::consume( $delay );

		// Remove the "current login attempt" information
		$request->setSessionData( $sessionKey, null );

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
			'accountType' => $user->isNamed() ? 'named' : 'temp',
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
		$this->logger->debug( 'Edge login on the next pageview after Special:CentralLogin' );
		$request->setSessionData( 'CentralAuthDoEdgeLogin', true );

		$returnToTitle = Title::newFromText( $attempt['returnTo'] ) ?: Title::newMainPage();
		$redirectUrl = $returnToTitle->getFullUrlForRedirect( $attempt['returnToQuery'] )
			. $attempt['returnToAnchor'];
		$this->getOutput()->redirect( $redirectUrl );
	}

	/**
	 * @param string|MessageSpecifier $key
	 * @param mixed ...$params
	 */
	private function showError( $key, ...$params ) {
		$accountType = 'anon';
		if ( $this->getUser()->isRegistered() ) {
			$accountType = $this->getUser()->isNamed() ? 'named' : 'temp';
		}

		LoggerFactory::getInstance( 'authevents' )->info( 'Central login attempt', [
			'event' => 'centrallogin',
			'successful' => false,
			'status' => $key instanceof MessageSpecifier ? $key->getKey() : $key,
			'extension' => 'CentralAuth',
			'accountType' => $accountType
		] );
		$this->getOutput()->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$this->getOutput()->addHTML( Html::errorBox( $this->msg( $key, ...$params )->parse() ) );
		// JS only
		$this->getOutput()->addHTML( '<p id="centralauth-backlink-section"></p>' );
	}
}
