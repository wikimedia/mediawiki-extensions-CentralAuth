<?php

class SpecialCentralLogin extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'CentralLogin' );
	}

	function execute( $subpage ) {
		$this->setHeaders();
		$this->getOutput()->disallowUserJs(); // just in case...

		$token = $this->getRequest()->getVal( 'token' );

		if ( $subpage === 'start' ) {
			$this->doLoginStart( $token );
		} elseif ( $subpage === 'complete' && $this->getRequest()->wasPosted() ) {
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
	 * @throws MWException
	 */
	protected function doLoginStart( $token ) {
		global $wgMemc;

		$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );

		// Get the token information
		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			$this->showError( 'centralauth-error-badtoken' );
			return;
		}

		$centralUser = new CentralAuthUser( $info['name'] );
		if ( !$centralUser->exists() ) { // sanity
			throw new MWException( "Global user '{$info['name']}' does not exist." );
		} elseif ( $centralUser->getId() !== $info['guid'] ) { // sanity
			throw new MWException( "Global user does not have ID '{$info['guid']}'." );
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
		$wgMemc->delete( $key );

		// Start an unusable placeholder session stub and send a cookie.
		// The cookie will not be usable until the session is unstubbed.
		// Note: the "remember me" token must be dealt with later (security).
		$newSessionId = CentralAuthUser::setSession( array(
			'pending_name' => $centralUser->getName(),
			'pending_guid' => $centralUser->getId()
		) );
		CentralAuthUser::setCookie( 'User', $centralUser->getName() );

		// Create a new token to pass to Special:CentralLogin/complete (local wiki).
		$token = MWCryptRand::generateHex( 32 );
		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$data = array(
			'sessionId' => $newSessionId,
			'secret'    => $info['secret'] // should match the login attempt secret
		);
		$wgMemc->set( $key, $data, 60 );

		$wiki = WikiMap::getWiki( $info['wikiId'] );
		// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
		$url = $wiki->getFullUrl( 'Special:CentralLogin/complete' );

		$this->getOutput()->addHtml(
			Xml::openElement( 'form',
				array( 'method' => 'post', 'action' => $url, 'id' => 'mw-centralloginform' ) ) .
			Html::rawElement( 'p', null,
				$this->msg( 'centralauth-completelogin-text' )->parse() ) .
			Html::hidden( 'token', $token ) .
			Xml::openElement( 'fieldset' ) .
			Html::rawElement( 'legend',
				null, $this->msg( 'centralauth-completelogin-legend' )->parse() ) .
			Xml::submitButton( $this->msg( 'centralauth-completelogin-submit' )->text() ) .
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);
		$this->getOutput()->addInlineStyle( // hide the form and let JS submit it
			'.client-js #mw-centralloginform { display: none; }'
		);
	}

	/**
	 * @param $token string
	 * @throws MWException
	 */
	protected function doLoginComplete( $token ) {
		global $wgUser, $wgMemc, $wgSecureLogin;

		$request = $this->getRequest();

		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt'; // session key

		// Get the token information
		$info = $wgMemc->get( $key );
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
		if ( !$user->getId() ) { // sanity
			throw new MWException( "The user account logged into does not exist." );
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->getId() ) { // sanity
			throw new MWException( "The user account is not attached." );
		}

		// Delete the temporary token
		$wgMemc->delete( $key );

		// Fully initialize the stub central user session and send the domain cookie.
		// This lets User::loadFromSession to initialize the User object from the local
		// session now that the global session is complete.
		$centralUser->setGlobalCookies( $_SESSION[$skey]['remember'], $info['sessionId'] );
		// Remove the "current login attempt" information
		$request->setSessionData( $skey, null );

		// Update the current user global
		$wgUser = $user;
		// This should set it for OutputPage and the Skin
		// which is needed or the personal links will be wrong.
		$this->getContext()->setUser( $user );

		// Show the login success page
		$form = new LoginForm( new FauxRequest() );
		$form->showReturnToPage( 'success',
			$attempt['returnTo'], $attempt['returnToQuery'], $attempt['stickHTTPS'] );
		$this->getOutput()->setPageTitle( $this->msg( 'centralloginsuccesful' ) );

		if ( $wgSecureLogin
			&& WebRequest::detectProtocol() === 'https' && !$attempt['stickHTTPS'] )
		{
			// The user wants an HTTP redirect link (as well as other links) and
			// this is on HTTPS, so send a redirect to the success page in HTTP.
			$query = array(
				'returnto'      => $attempt['returnTo'],
				'returntoquery' => $attempt['returnToQuery']
			);
			$url = $this->getFullTitle()->getFullUrl( $query, false, PROTO_HTTP );
			$this->getOutput()->redirect( $url );
		} else {
			// Show any icons that trigger cross-domain cookies.
			// This will trigger filling in the "remember me" token cookie on the
			// central wiki, which can only be done once authorization is completed.
			$this->getOutput()->addHtml(
				CentralAuthHooks::getDomainAutoLoginIconHtml( $user, $centralUser ) );
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
		// Show any icons that trigger cross-domain cookies
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginIconHtml( $this->getUser(), $centralUser ) );
	}

	protected function showError( /* varargs */ ) {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>', $args );
		$this->getOutput()->addHtml( '<p id="centralauth-backlink-section"></p>' ); // JS only
	}
}
