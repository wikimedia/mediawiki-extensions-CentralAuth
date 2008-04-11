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
		global $wgRequest,$wgOut,$wgUser, $wgMemc, $wgDBname, $IP;
		
		$token = $wgRequest->getVal('token');
		$logout = $wgRequest->getBool( 'logout' );
		
		if (strlen($token) == 0 && !$logout) {
			$wgOut->addWikitext( 'AutoLogin' );
			return;
		}
		
		if ($logout == true) {
			$centralUser = new CentralAuthUser( $wgUser->getName() );
			
			if ($centralUser->getId()) {
				$centralUser->deleteGlobalCookies();
			}
		} else {
			$data = unserialize($wgMemc->get( 'centralauth_logintoken_'.$token ));
			$wgMemc->delete( 'centralauth_logintoken_'.$token );
			
			$username = $data['username'];
			$token = $data['token'];
			$remember = $data['remember'];
			
			#die( print_r( $data, true ));
			
			if ($data['wiki'] != $wgDBname) {
				$wgOut->addWikitext( 'Bad token (wrong wiki)' );
				return;
			}
			
			$centralUser = new CentralAuthUser( $username );
			
			$login_result = $centralUser->authenticateWithToken( $token );
		
			if ($login_result == 'ok' && $centralUser->isAttached()) {
				// Auth OK.
				$user = User::newFromName( $username );
				$user->setOption( 'rememberpassword', $remember );
				
				$centralUser->setGlobalCookies($user);
			} else {
				$wgOut->addWikitext( "Bad token (auth failed)" );
				return;
			}
		}
		
		require_once( "$IP/includes/StreamFile.php" );
		
		wfStreamFile( dirname(__FILE__).'/1x1.png' );
		
		$wgOut->disable();
	}
}