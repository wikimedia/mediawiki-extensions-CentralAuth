<?php

// @FIXME: replace exceptions with internationalized errors
class SpecialCentralLogin extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'CentralLogin' );
	}

	function execute( $subpage ) {
		$this->setHeaders();

		$token = $this->getRequest()->getVal( 'token' );

		$this->getOutput()->disallowUserJs(); // just in case...

		if ( $subpage === 'start' ) {
			$this->doLoginStart( $token );
		} elseif ( $subpage === 'complete' ) {
			if ( $this->getRequest()->wasPosted() ) {
				$this->doLoginComplete( $token );
			} else {
				throw new MWException( "Requests to this page must use HTTP POST." );
			}
		}
	}

	protected function doLoginStart( $token ) {
		global $wgMemc;

		$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );

		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			throw new MWException( "Key for token '$token' expired or is invalid." );
		}

		$caUser = new CentralAuthUser( $info['name'] );
		if ( !$caUser->exists() ) {
			throw new MWException( "Global user '{$info['name']}' does not exist." );
		} elseif ( $caUser->getId() !== $info['guid'] ) {
			throw new MWException( "Global user does not have ID '{$info['guid']}'." );
		}

		$session = CentralAuthUser::getSession();
		if ( $session !== array() ) {
			if ( isset( $session['pending_name'] )
				&& $session['pending_name'] !== $caUser->getName() )
			{
				throw new MWException( "Token user name does not match session's pending_name." );
				return;
			} elseif ( isset( $session['name'] )
				&& $session['name'] !== $caUser->getName() )
			{
				throw new MWException( "Token user name does not match session's user name." );
				return;
			} elseif ( isset( $session['name'] ) ) { // already initialized session
				$url = WikiMap::getForeignURL( $info['wikiId'], 'Special:Userlogin' );
				if ( strlen( $url ) ) {
					$url = wfAppendQuery( $url, array(
						'showSuccess'   => 1,
						'returnTo'      => $info['returnTo'],
						'returnToQuery' => $info['returnToQuery'],
						'wpStickHTTPS'  => $info['stickHTTPS']
					) );
					$this->getOutput()->redirect( $url );
				}
				return;
			}
		}

		// Delete the temporary token
		$wgMemc->delete( $key );

		// Start an unusable placeholder session stub
		$newSessionId = CentralAuthUser::setSession( array(
			'pending_name' => $caUser->getName(),
			'pending_guid' => $caUser->getId()
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

		// @TODO: missing messages
		$this->getOutput()->addHtml(
			Html::rawElement( 'p',
				null, $this->msg( 'centralauth-comletelogin-text' )->parse() ) .
			Xml::openElement( 'form',
				array( 'method' => 'post', 'action' => $url, 'id' => 'completeloginform' ) ) .
			Html::hidden( 'token', $token ) .
			Xml::openElement( 'fieldset' ) .
			Html::rawElement( 'legend',
				null, $this->msg( 'centralauth-comletelogin-legend' )->parse() ) .
			Xml::submitButton( $this->msg( 'centralauth-comletelogin-submit' )->text() ) .
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);

		$this->getOutput()->addInlineScript(
			"document.getElementById('completeloginform').submit();" );
	}

	protected function doLoginComplete( $token ) {
		global $wgMemc;

		$request = $this->getRequest();

		$key = CentralAuthUser::memcKey( 'central-login-complete-token', $token );
		$skey = 'CentralAuth:autologin:current-attempt';

		$attemptInfo = $request->getSessionData( 'CentralAuth:autologin:current-attempt' );
		if ( !isset( $attemptInfo['secret'] ) ) {
			throw new MWException( "No active login attempt is in progress for the session." );
		}

		$info = $wgMemc->get( $key );
		if ( !is_array( $info ) ) {
			throw new MWException( "Key for token '$token' expired or is invalid." );
		}

		$hash = hash_hmac( 'sha1', $info['sessionId'], $_SESSION[$skey]['secret'] );
		if ( $hash !== $info['signature'] ) {
			throw new MWException(
				"The token does not have the signature of the current login attempt." );
		}

		$caUser = new CentralAuthUser( $request->getSessionData( 'wsUserName' ) );
		if ( !$caUser->getId() ) {
			throw new MWException( "The user account is not attached." );
		}

		// Fully initialize the central user session.
		// This lets User::loadFromSession to initialize the User object
		// from the local session now that the global session is complete.
		$caUser->setGlobalCookies( $_SESSION[$skey]['remember'] );

		// Remove the "current login attempt" information
		$request->setSessionData( $skey, null );

		// Redirect the user to the login success page
		$form = new LoginForm( new FauxRequest() );
		$form->executeSuccessRedirect( $attemptInfo['returnTo'], $attemptInfo['returnToQuery'] );
	}
}
