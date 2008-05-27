<?php
if (!defined('MEDIAWIKI')) {
	die('CentralAuth');
}

/**
 * Unlisted Special page to set requisite cookies for being logged into this wiki.
 *
 * @addtogroup Extensions
 */
 
class SpecialAutoLogin extends UnlistedSpecialPage
{
	function __construct() {
		parent::__construct('AutoLogin');
	}
	
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgMemc, $IP;
		
		$token = $wgRequest->getVal('token');
		$logout = $wgRequest->getBool( 'logout' );
		
		if (strlen($token) == 0 && !$logout) {
			$wgOut->addWikiText( 'AutoLogin' );
			return;
		}
		
		if ($logout == true) {
			$centralUser = new CentralAuthUser( $wgUser->getName() );
			
			if ($centralUser->getId()) {
				$centralUser->deleteGlobalCookies();
			}
		} else {
			$key = CentralAuthUser::memcKey( 'login-token', $token );
			$data = $wgMemc->get( $key );
			$wgMemc->delete( $key );
			
			$userName = $data['userName'];
			$token = $data['token'];
			$remember = $data['remember'];
			
			#die( print_r( $data, true ));
			
			if ($data['wiki'] != wfWikiID()) {
				$wgOut->addWikiText( 'Bad token (wrong wiki)' );
				return;
			}
			
			$centralUser = new CentralAuthUser( $userName );
			$login_result = $centralUser->authenticateWithToken( $token );
		
			if ($login_result == 'ok') {
				// Auth OK.
				$centralUser->setGlobalCookies($remember);
			} else {
				$wgOut->addWikiText( 'Bad token - Auth failed' );
				return;
			}
		}
		
		require_once( "$IP/includes/StreamFile.php" );
		
		wfStreamFile( dirname(__FILE__).'/1x1.png' );
		
		$wgOut->disable();
	}
}
