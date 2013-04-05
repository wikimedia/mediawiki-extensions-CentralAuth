<?php

// @FIXME: replace exceptions with internationalized errors
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
	}

	protected function doLoginStart( $token ) {
		global $wgMemc;

		$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );

		// Get the token information
		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			throw new MWException( "Key for token '$token' expired or is invalid." );
		}

		$centralUser = new CentralAuthUser( $info['name'] );
		if ( !$centralUser->exists() ) {
			throw new MWException( "Global user '{$info['name']}' does not exist." );
		} elseif ( $centralUser->getId() !== $info['guid'] ) {
			throw new MWException( "Global user does not have ID '{$info['guid']}'." );
		}

		$session = CentralAuthUser::getSession();
		// If the user has a full session, make sure that the names match up.
		// If they do, then send the user back to the "login successful" page.
		// We want to avoid overwriting any session that may already exist.
		if ( isset( $session['name'] ) ) { // fully initialized session
			if ( $session['name'] !== $centralUser->getName() ) {
				// @TODO: what if a user wants to login under another account?
				throw new MWException( "Token user name does not match session's user name." );
			} else {
				$url = WikiMap::getForeignURL( $info['wikiId'], 'Special:CentralLogin/status' );
				if ( !strlen( $url ) ) {
					throw new MWException( 'Could not generate form target URL.' );
				}
				$this->getOutput()->redirect( $url );
			}
			return;
		// If the user has a stub session, error out if the names do not match up
		} elseif ( isset( $session['pending_name'] ) ) { // stub session
			if ( $session['pending_name'] !== $centralUser->getName() ) {
				throw new MWException(
					"Token user name does not match session's pending_name." );
				return;
			}
		}

		// Delete the temporary token
		$wgMemc->delete( $key );

		// Start an unusable placeholder session stub and send a cookie.
		// The cookie will not be usable until the session is unstubbed.
		// Note: the "remember me" token must be deal with in later steps.
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

		$url = WikiMap::getForeignURL( $info['wikiId'], 'Special:CentralLogin/complete' );
		if ( !strlen( $url ) ) {
			throw new MWException( 'Could not generate form target URL.' );
		}

		$this->getOutput()->addHtml(
			Html::rawElement( 'p',
				null, $this->msg( 'centralauth-comletelogin-text' )->parse() ) .
			Xml::openElement( 'form',
				array( 'method' => 'post', 'action' => $url, 'id' => 'mw-centralloginform' ) ) .
			Html::hidden( 'token', $token ) .
			Xml::openElement( 'fieldset' ) .
			Html::rawElement( 'legend',
				null, $this->msg( 'centralauth-comletelogin-legend' )->parse() ) .
			Xml::submitButton( $this->msg( 'centralauth-comletelogin-submit' )->text() ) .
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);

		$this->getOutput()->addModules( 'ext.centralauth' ); // auto form submit
	}

	protected function doLoginComplete( $token ) {
		global $wgUser, $wgMemc;

		$request = $this->getRequest();

		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt';

		// Get the user's current login attempt information
		$attempt = $request->getSessionData( 'CentralAuth:autologin:current-attempt' );
		if ( !isset( $attempt['secret'] ) ) {
			throw new MWException( "No active login attempt is in progress for the session." );
		}

		// Get the token information
		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			throw new MWException( "Key for token '$token' expired or is invalid." );
		}

		// Make sure this token belongs to the user who spawned the tokens.
		// This prevents users from giving out links that log people in as them.
		if ( $info['secret'] !== $attempt['secret'] ) {
			throw new MWException( "The token is not for the current login attempt." );
		}

		$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
		if ( !$user->getId() ) {
			throw new MWException( "The user account logged into does not exist." );
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->getId() ) {
			throw new MWException( "The user account is not attached." );
		}

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
		// Show any icons that trigger cross-domain cookies
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginIconHtml( $user, $centralUser ) );
	}

	protected function showLoginStatus() {
		if ( !$this->getUser()->isLoggedIn() ) {
			throw new MWException( "User is not logged in." );
		}
		$centralUser = CentralAuthUser::getInstance( $this->getUser() );
		if ( !$centralUser->getId() ) {
			throw new MWException( "The user account is not attached." );
		}
		// Show the login success page
		$form = new LoginForm( new FauxRequest() );
		$form->showReturnToPage( 'success' );
		$this->getOutput()->setPageTitle( $this->msg( 'centralloginsuccesful' ) );
		// Show any icons that trigger cross-domain cookies.
		// This will trigger filling in the "remember me" token cookie on the
		// central wiki, which can only be done once authorization is completed.
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginIconHtml( $this->getUser(), $centralUser ) );
	}
}
