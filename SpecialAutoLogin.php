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
	function SpecialAutoLogin() {
		SpecialPage::SpecialPage('AutoLogin');
	}
	
	function execute() {
		global $IP, $wgRequest,$wgOut,$wgUser;
		
		$username = $wgRequest->getVal( 'user' );
		$token = $wgRequest->getVal('token');
		$remember = $wgRequest->getBool( 'remember' );
		$logout = $wgRequest->getBool( 'logout' );
		
		if ((strlen($username) == 0 || strlen($token) == 0) && !$logout) {
			$wgOut->addWikitext( 'AutoLogin' );
			return;
		}
		
		if ($logout == true) {
			$centralUser = new CentralAuthUser( $wgUser->getName() );
			
			if ($centralUser->getId()) {
				$centralUser->deleteGlobalCookies();
			}
		} else {
			$centralUser = new CentralAuthUser( $username );
			
			$login_result = $centralUser->authenticateWithToken( $token );
		
			if ($login_result == 'ok' && $centralUser->isAttached()) {
				// Auth OK.
				$user = User::newFromName( $username );
				$user->setOption( 'rememberpassword', $remember );
				
				$centralUser->setGlobalCookies($user);
			}
		}
		
		require_once( "$IP/includes/StreamFile.php" );
		
		wfStreamFile( dirname(__FILE__).'/1x1.png' );
		
		$wgOut->disable();
	}
}