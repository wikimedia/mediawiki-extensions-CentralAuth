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
		} else {
			// invalid request
		}
	}

	protected function doLoginStart( $token ) {
		global $wgMemc;

		$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );

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
		if ( $session !== array() ) {
			if ( isset( $session['pending_name'] )
				&& $session['pending_name'] !== $centralUser->getName() )
			{
				throw new MWException( "Token user name does not match session's pending_name." );
				return;
			} elseif ( isset( $session['name'] )
				&& $session['name'] !== $centralUser->getName() )
			{
				throw new MWException( "Token user name does not match session's user name." );
				return;
			} elseif ( isset( $session['name'] ) ) { // already initialized session
				$url = WikiMap::getForeignURL( $info['wikiId'], 'Special:CentralLogin/status' );
				if ( !strlen( $url ) ) {
					throw new MWException( 'Could not generate form target URL.' );
				}
				$this->getOutput()->redirect( $url );
				return;
			}
		}

		// Delete the temporary token
		$wgMemc->delete( $key );

		// Start an unusable placeholder session stub
		$newSessionId = CentralAuthUser::setSession( array(
			'pending_name' => $centralUser->getName(),
			'pending_guid' => $centralUser->getId()
		) );

		// Create a new token to pass to Special:CentralLogin/complete (local wiki)
		$token = MWCryptRand::generateHex( 32 );
		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$data = array(
			'sessionId' => $newSessionId,
			'signature' => hash_hmac( 'sha1', $newSessionId, $info['secret'] )
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

		$attempt = $request->getSessionData( 'CentralAuth:autologin:current-attempt' );
		if ( !isset( $attempt['secret'] ) ) {
			throw new MWException( "No active login attempt is in progress for the session." );
		}

		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			throw new MWException( "Key for token '$token' expired or is invalid." );
		}

		$hash = hash_hmac( 'sha1', $info['sessionId'], $attempt['secret'] );
		if ( $hash !== $info['signature'] ) {
			throw new MWException(
				"The token does not have the signature of the current login attempt." );
		}

		$user = User::newFromName( $request->getSessionData( 'wsUserName' ) );
		if ( !$user->getId() ) {
			throw new MWException( "The user account logged into does not exist." );
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->getId() ) {
			throw new MWException( "The user account is not attached." );
		}

		// Fully initialize the central user session.
		// This lets User::loadFromSession to initialize the User object
		// from the local session now that the global session is complete.
		$centralUser->setGlobalCookies( $_SESSION[$skey]['remember'] );

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
		// Show any icons that trigger cross-domain cookies.
		// Normally if CentralLogin is used, this is not needed, though it could
		// be useful for migration reasons or backwards compatibility for a while.
		$this->getOutput()->addHtml(
			CentralAuthHooks::getDomainAutoLoginIconHtml( $this->getUser(), $centralUser ) );
	}
}
