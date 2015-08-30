<?php

class SpecialCentralLogin extends UnlistedSpecialPage {
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
		$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );
		$cache = CentralAuthUser::getSessionCache();

		// Get the token information
		$casToken = null;
		$info = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		$centralUser = new CentralAuthUser( $info['name'] );
		if ( !$centralUser->exists() ) { // sanity
			throw new Exception( "Global user '{$info['name']}' does not exist." );
		} elseif ( $centralUser->getId() !== $info['guid'] ) { // sanity
			throw new Exception( "Global user does not have ID '{$info['guid']}'." );
		}

		$session = CentralAuthUser::getSession();
		// If the user has a full session, make sure that the names match up.
		// If they do, then send the user back to the "login successful" page.
		// We want to avoid overwriting any session that may already exist.
		if ( isset( $session['name'] ) ) { // fully initialized session
			if ( $session['name'] !== $centralUser->getName() ) {
				// @FIXME: what if a user wants to login under another account?
				$this->showError( 'centralauth-error-token-wronguser' );
			} else {
				$wiki = WikiMap::getWiki( $info['wikiId'] );
				// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
				$this->getOutput()->redirect( // expands to PROTO_CURRENT
					$wiki->getFullUrl( 'Special:CentralLogin/status' )
				);
			}
			return; // don't override session
		// If the user has a stub session, error out if the names do not match up
		} elseif ( isset( $session['pending_name'] ) ) { // stub session
			if ( $session['pending_name'] !== $centralUser->getName() ) {
				$this->showError( 'centralauth-error-token-wronguser' );
				return;
			}
		}

		// Delete the temporary token
		$cache->delete( $key );

		// Determine if we can use the default cookie security, or if we need
		// to override it to insecure
		$secureCookie = $info['secureCookies'];

		// Start an unusable placeholder session stub and send a cookie.
		// The cookie will not be usable until the session is unstubbed.
		// Note: the "remember me" token must be dealt with later (security).
		$newSessionId = CentralAuthUser::setSession( array(
			'pending_name' => $centralUser->getName(),
			'pending_guid' => $centralUser->getId()
		), true, $secureCookie );
		CentralAuthUser::setCookie( 'User', $centralUser->getName(), -1, $secureCookie );
		CentralAuthUser::setCookie( 'Token', '', -86400, $secureCookie );

		// Create a new token to pass to Special:CentralLogin/complete (local wiki).
		$token = MWCryptRand::generateHex( 32 );
		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$data = array(
			'sessionId' => $newSessionId,
			'secret'    => $info['secret'] // should match the login attempt secret
		);
		$cache->set( $key, $data, 60 );

		$wiki = WikiMap::getWiki( $info['wikiId'] );
		// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
		$url = $wiki->getFullUrl( 'Special:CentralLogin/complete' );
		// Ensure $url really is proto relative, and prepend the protocol of the original
		// login. If the local wiki is using wgSecureLogin, it will be https.
		$url = strstr( $url, '//' );

		//currentProto = the login form's protocol, so we go back to here. May then redir to finalProto
		$url = $info['currentProto'] . ':' . $url;

		$url = wfAppendQuery( $url, array( 'token' => $token ) ); // expands to PROTO_CURRENT if $url doesn't have protocol
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
		$cache = CentralAuthUser::getSessionCache();

		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
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
		// This lets User::loadFromSession to initialize the User object from the local
		// session now that the global session is complete.
		// Without $wgSecureLogin, we should be on the correct protocol now, and we use the
		// default cookie security. With $wgSecureLogin, we use the stickHTTPS checkbox.
		$secureCookie = $attempt['stickHTTPS'];

		$centralUser->setGlobalCookies( $attempt['remember'], $info['sessionId'], $secureCookie, array(
			'finalProto' => $attempt['finalProto'],
			'secureCookies' => $attempt['stickHTTPS'],
			'remember' => $attempt['remember'],
		) );

		// Remove the "current login attempt" information
		$request->setSessionData( $skey, null );

		// Update the current user global
		$wgUser = $user;
		// This should set it for OutputPage and the Skin
		// which is needed or the personal links will be wrong.
		$this->getContext()->setUser( $user );

		// Show the login success page
		$form = new LoginForm( new FauxRequest() );
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

		$form->showReturnToPage(
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
		$form = new LoginForm( new FauxRequest() );
		$form->showReturnToPage( 'success',
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
