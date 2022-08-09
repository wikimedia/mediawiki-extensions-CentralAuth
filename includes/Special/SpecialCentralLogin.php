<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthSessionProvider;
use Exception;
use IBufferingStatsdDataFactory;
use LoginHelper;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Session\Session;
use MediaWiki\User\UserIdentity;
use MWCryptRand;
use StubGlobalUser;
use UnlistedSpecialPage;
use User;
use WebRequest;
use WikiMap;
use Wikimedia\ScopedCallback;

class SpecialCentralLogin extends UnlistedSpecialPage {

	/** @var Session */
	protected $session = null;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	/**
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param CentralAuthUtilityService $utilityService
	 * @param CentralAuthSessionManager $sessionManager
	 */
	public function __construct(
		IBufferingStatsdDataFactory $statsdDataFactory,
		CentralAuthUtilityService $utilityService,
		CentralAuthSessionManager $sessionManager
	) {
		parent::__construct( 'CentralLogin' );
		$this->statsdDataFactory = $statsdDataFactory;
		$this->utilityService = $utilityService;
		$this->sessionManager = $sessionManager;
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	public function execute( $subpage ) {
		// Enforce $wgSecureLogin
		$request = $this->getRequest();
		if ( $this->shouldDoProtocolRedirect() ) {
			$redirUrl = str_replace( 'http://', 'https://', $request->getFullRequestURL() );
			$output = $this->getOutput();
			$output->addVaryHeader( 'X-Forwarded-Proto' );
			$output->redirect( $redirUrl );
			$output->output();
			return;
		}

		$this->setHeaders();
		$this->getOutput()->disallowUserJs(); // just in case...

		// Check session, if possible
		$session = $request->getSession();
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
		} elseif ( $subpage === 'status' ) {
			$this->showLoginStatus();
		} else { // invalid request - @phan-suppress-current-line PhanPluginDuplicateIfStatements
			$this->showLoginStatus();
		}

		// Auto-submit and back links
		$this->getOutput()->addModules( 'ext.centralauth' );
	}

	/**
	 * Should we redirect from HTTP to HTTPS, according to $wgForceHTTPS and
	 * $wgSecureLogin?
	 *
	 * @return bool
	 */
	private function shouldDoProtocolRedirect() {
		if ( WebRequest::detectProtocol() !== 'http' ) {
			return false;
		}
		if ( $this->getConfig()->get( 'ForceHTTPS' ) ) {
			return true;
		}
		return (bool)$this->getConfig()->get( 'SecureLogin' );
	}

	/**
	 * @param string $token
	 * @throws Exception
	 */
	protected function doLoginStart( $token ) {
		$key = $this->sessionManager->memcKey( 'central-login-start-token', $token );
		$tokenStore = $this->sessionManager->getTokenStore();

		// Get the token information
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
		$key = $this->sessionManager->memcKey( 'central-login-complete-token', $token );
		$data = [
			'sessionId' => $newSessionId,
			'secret'    => $info['secret'] // should match the login attempt secret
		];
		$tokenStore->set( $key, $data, $tokenStore::TTL_MINUTE );

		$query = [ 'token' => $token ];

		$wiki = WikiMap::getWiki( $info['wikiId'] );
		// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
		$url = $wiki->getFullUrl( 'Special:CentralLogin/complete' );
		// Ensure $url really is proto relative, and prepend the protocol of the original
		// login. If the local wiki is using wgSecureLogin, it will be https.
		$url = strstr( $url, '//' );

		// currentProto = the login form's protocol, so we go back to here. May then redir to finalProto
		$url = $info['currentProto'] . ':' . $url;

		$url = wfAppendQuery( $url, $query ); // expands to PROTO_CURRENT if $url doesn't have protocol

		$caHookRunner = new CentralAuthHookRunner( $this->getHookContainer() );
		$caHookRunner->onCentralAuthSilentLoginRedirect( $centralUser, $url, $info );

		$this->getOutput()->redirect( $url );
	}

	/**
	 * @param string $token
	 * @throws Exception
	 */
	protected function doLoginComplete( $token ) {
		$request = $this->getRequest();
		$tokenStore = $this->sessionManager->getTokenStore();

		$key = $this->sessionManager->memcKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt'; // session key

		// Get the token information
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
		$delay = $this->session->delaySave();
		$this->session->setUser( User::newFromName( $centralUser->getName() ) );
		$this->session->setRememberUser( (bool)$attempt['remember'] );
		if ( $attempt['stickHTTPS'] !== null ) {
			$this->session->setForceHTTPS( (bool)$attempt['stickHTTPS'] );
		}
		$this->sessionManager->setCentralSession( [
			'finalProto' => $attempt['finalProto'],
			'secureCookies' => $attempt['stickHTTPS'],
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

		// Show the login success page

		$inject_html = '';
		if ( $attempt['type'] === 'signup' ) {
			$msg = $this->msg( 'centralauth-welcomecreation-msg' );
			if ( !$msg->isDisabled() ) {
				$inject_html .= $msg->params( wfEscapeWikiText( $user->getName() ) )->parseAsBlock();
			}
		}

		// Allow other extensions to modify the returnTo and returnToQuery
		$caHookRunner = new CentralAuthHookRunner( $this->getHookContainer() );
		$caHookRunner->onCentralAuthPostLoginRedirect(
			$attempt['returnTo'],
			$attempt['returnToQuery'],
			$attempt['stickHTTPS'],
			$attempt['type'],
			$inject_html
		);

		if ( $inject_html === '' ) {
			$action = 'successredirect';

			// Mark the session to include the edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
		} else {
			$action = 'success';

			$this->getOutput()->addHTML( $inject_html );

			// Show HTML to trigger cross-domain cookies.
			// This will trigger filling in the "remember me" token cookie on the
			// central wiki, which can only be done once authorization is completed.
			$csp = $this->getOutput()->getCSP();
			$this->getOutput()->addHtml(
				CentralAuthHooks::getDomainAutoLoginHtml( $user, $centralUser, $csp ) );
		}

		$helper = new LoginHelper( $this->getContext() );
		$helper->showReturnToPage(
			$action,
			$attempt['returnTo'],
			$attempt['returnToQuery'],
			( $attempt['finalProto'] == 'https' ), // influnces http/https of returnTo page
			$attempt['returnToAnchor'] ?? ''
		);
		$this->getOutput()->setPageTitle( $this->msg( 'centralloginsuccesful' ) );

		if ( $this->getConfig()->get( 'CentralAuthCheckSULMigration' ) &&
			$request->getSessionData( 'CentralAuthForcedRename' ) === true
		) {
			$this->logger->info(
				"CentralAuthMigration: Login completed for renamed user '{$user->getName()}'"
			);
		}
	}

	protected function showLoginStatus() {
		if ( !$this->getUser()->isRegistered() ) {
			$this->showError( 'centralauth-warning-notloggedin' );
			return;
		}
		$centralUser = CentralAuthUser::getInstance( $this->getUser() );
		if ( !$centralUser->getId() ) {
			$this->showError( 'centralauth-warning-notattached' );
			return;
		}

		// Show the login success page
		$helper = new LoginHelper( $this->getContext() );
		$helper->showReturnToPage( 'success',
			$this->getRequest()->getVal( 'returnto', '' ),
			$this->getRequest()->getVal( 'returntoquery', '' )
		);
		$this->getOutput()->setPageTitle( $this->msg( 'centralloginsuccesful' ) );
		// Show HTML to trigger cross-domain cookies
		$csp = $this->getOutput()->getCSP();
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginHtml( $this->getUser(), $centralUser, $csp ) );
	}

	protected function showError( ...$args ) {
		$this->statsdDataFactory->increment( 'centralauth.centrallogin_errors.' . $args[0] );
		$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>', $args );
		$this->getOutput()->addHtml( '<p id="centralauth-backlink-section"></p>' ); // JS only
	}
}
