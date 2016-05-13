<?php

class SpecialCentralLogin extends UnlistedSpecialPage {

	/** @var MediaWiki\\Session\\Session */
	protected $session = null;

	function __construct() {
		parent::__construct( 'CentralLogin' );
	}

	function execute( $subpage ) {
		// Enforce $wgSecureLogin
		global $wgSecureLogin;

		$request = $this->getRequest();
		if ( $wgSecureLogin
			&& $request->detectProtocol() == 'http'
			&& wfCanIPUseHTTPS( $request->getIP() )
		) {
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
		} elseif( $subpage === 'status' ) {
			$this->showLoginStatus();
		} else { // invalid request
			$this->showLoginStatus();
		}

		// Auto-submit and back links
		$this->getOutput()->addModules( 'ext.centralauth' );
	}

	/**
	 * @param $token string
	 * @throws Exception
	 */
	protected function doLoginStart( $token ) {
		$key = CentralAuthUtils::memcKey( 'central-login-start-token', $token );
		$cache = CentralAuthUtils::getSessionCache();

		// Get the token information
		$casToken = null;
		$info = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $info['name'] );
		if ( !$centralUser->exists() ) { // sanity
			throw new Exception( "Global user '{$info['name']}' does not exist." );
		} elseif ( $centralUser->getId() !== $info['guid'] ) { // sanity
			throw new Exception( "Global user does not have ID '{$info['guid']}'." );
		}

		$session = CentralAuthUtils::getCentralSession();
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
		$cache->delete( $key );

		if ( $createStubSession ) {
			// Determine if we can use the default cookie security, or if we need
			// to override it to insecure
			$secureCookie = $info['secureCookies'];

			// Start an unusable placeholder session stub and send a cookie.
			// The cookie will not be usable until the session is unstubbed.
			// Note: the "remember me" token must be dealt with later (security).
			$delay = $this->session->delaySave();
			$this->session->setUser( User::newFromName( $centralUser->getName() ) );
			$newSessionId = CentralAuthUtils::setCentralSession( array(
				'pending_name' => $centralUser->getName(),
				'pending_guid' => $centralUser->getId()
			), true, $this->session );
			$this->session->persist();
			ScopedCallback::consume( $delay );
		} else {
			// Since the full central session already exists, reuse it.
			$newSessionId = $session['sessionId'];
		}

		// Create a new token to pass to Special:CentralLogin/complete (local wiki).
		$token = MWCryptRand::generateHex( 32 );
		$key = CentralAuthUtils::memcKey( 'central-login-complete-token', $token );
		$data = array(
			'sessionId' => $newSessionId,
			'secret'    => $info['secret'] // should match the login attempt secret
		);
		$cache->set( $key, $data, 60 );

		$query = array( 'token' => $token );
		// On account creation, where a central user is added to the DB,
		// make sure the local wiki request actually sees the new row.
		// ChronologyProtector would otherwise delay if there is high lag.
		if ( CentralAuthUser::centralLBHasRecentMasterChanges() ) {
			$query['CentralAuthLatest'] = 1;
		}

		$wiki = WikiMap::getWiki( $info['wikiId'] );
		// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
		$url = $wiki->getFullUrl( 'Special:CentralLogin/complete' );
		// Ensure $url really is proto relative, and prepend the protocol of the original
		// login. If the local wiki is using wgSecureLogin, it will be https.
		$url = strstr( $url, '//' );

		//currentProto = the login form's protocol, so we go back to here. May then redir to finalProto
		$url = $info['currentProto'] . ':' . $url;

		$url = wfAppendQuery( $url, $query ); // expands to PROTO_CURRENT if $url doesn't have protocol
		Hooks::run( 'CentralAuthSilentLoginRedirect', array( $centralUser, &$url, $info ) );
		$this->getOutput()->redirect( $url );
	}

	/**
	 * @param $token string
	 * @throws Exception
	 */
	protected function doLoginComplete( $token ) {
		global $wgUser;
		global $wgCentralAuthCheckSULMigration;

		$request = $this->getRequest();
		$cache = CentralAuthUtils::getSessionCache();

		$key = CentralAuthUtils::memcKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt'; // session key

		// Get the token information
		$casToken = null;
		$info = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
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

		$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
		if ( !$user || !$user->getId() ) { // sanity
			throw new Exception( "The user account logged into does not exist." );
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->getId() ) { // sanity
			throw new Exception( "The user account is not attached." );
		}

		// Delete the temporary token
		$cache->delete( $key );

		// Fully initialize the stub central user session and send the domain cookie.
		$delay = $this->session->delaySave();
		$this->session->setUser( User::newFromName( $centralUser->getName() ) );
		$this->session->setRememberUser( (bool)$attempt['remember'] );
		if ( $attempt['stickHTTPS'] !== null ) {
			$this->session->setForceHTTPS( (bool)$attempt['stickHTTPS'] );
		}
		$newSessionId = CentralAuthUtils::setCentralSession( array(
			'finalProto' => $attempt['finalProto'],
			'secureCookies' => $attempt['stickHTTPS'],
			'remember' => $attempt['remember'],
		), $info['sessionId'], $this->session );
		$this->session->persist();
		ScopedCallback::consume( $delay );

		// Remove the "current login attempt" information
		$request->setSessionData( $skey, null );

		// Update the current user global
		$wgUser = $user;
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
		Hooks::run( 'CentralAuthPostLoginRedirect', array(
			&$attempt['returnTo'],
			&$attempt['returnToQuery'],
			$attempt['stickHTTPS'],
			$attempt['type'],
			&$inject_html
		) );

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
			$this->getOutput()->addHtml(
				CentralAuthHooks::getDomainAutoLoginHtml( $user, $centralUser ) );
		}

		$helper = new LoginHelper( $this->getContext() );
		$helper->showReturnToPage(
			$action,
			$attempt['returnTo'],
			$attempt['returnToQuery'],
			( $attempt['finalProto'] == 'https' ) // influnces http/https of returnTo page
		);
		$this->getOutput()->setPageTitle( $this->msg( 'centralloginsuccesful' ) );
		if ( $wgCentralAuthCheckSULMigration &&
			$request->getSessionData( 'CentralAuthForcedRename' ) === true
		) {
			wfDebugLog( 'CentralAuth',
				"CentralAuthMigration: Login completed for renamed user '{$wgUser->getName()}'"
			);
		}
	}

	protected function showLoginStatus() {
		if ( !$this->getUser()->isLoggedIn() ) {
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
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginHtml( $this->getUser(), $centralUser ) );
	}

	protected function showError( /* varargs */ ) {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>', $args );
		$this->getOutput()->addHtml( '<p id="centralauth-backlink-section"></p>' ); // JS only
	}
}
