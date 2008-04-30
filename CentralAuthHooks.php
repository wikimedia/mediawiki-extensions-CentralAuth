<?php

class CentralAuthHooks {
	static function onAuthPluginSetup( &$auth ) {
		$auth = new StubObject( 'wgAuth', 'CentralAuthPlugin' );
		return true;
	}
	
	/**
	 * Make sure migration information in localuser table is populated
	 * on local account creation
	 */
	static function onAddNewAccount( $user ) {
		$central = CentralAuthUser::getInstance( $user );
		$central->addLocalName( wfWikiID() );
		return true;
	}
	
	/**
	 * Add a little pretty to the preferences user info section
	 */
	static function onPreferencesUserInformationPanel( $prefsForm, &$html ) {
		global $wgUser;

		if ( !$wgUser->isAllowed( 'centralauth-merge' ) ) {
			// Not allowed to merge, don't display merge information
			return true;
		}

		wfLoadExtensionMessages('SpecialCentralAuth');
		$skin = $wgUser->getSkin();
		$special = SpecialPage::getTitleFor( 'MergeAccount' );


		// Possible states:
		// - account not merged at all
		// - global accounts exists, but this local account is unattached
		// - this local account is attached, but migration incomplete
		// - all local accounts are attached

		$global = CentralAuthUser::getInstance( $wgUser );
		if( $global->exists() ) {
			if( $global->isAttached() ) {
				// Local is attached...
				$attached = count( $global->listAttached() );
				$unattached = count( $global->listUnattached() );
				if( $unattached ) {
					// Migration incomplete
					$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-migration' ) . '</strong>' .
						'<br />' .
						htmlspecialchars( wfMsgExt( 'centralauth-prefs-count-attached', array( 'parsemag' ), $attached ) ) .
						'<br />' .
						htmlspecialchars( wfMsgExt( 'centralauth-prefs-count-unattached', array( 'parsemag' ), $unattached ) );
				} else {
					// Migration complete
					$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-complete' ) . '</strong>' .
						'<br />' .
						htmlspecialchars( wfMsgExt( 'centralauth-prefs-count-attached', array( 'parsemag' ), $attached ) );
				}
			} else {
				// Account is in migration, but the local account is not attached
				$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-unattached' ) . '</strong>' .
					'<br />' .
					wfMsgHtml( 'centralauth-prefs-detail-unattached' );
			}
		} else {
			// Not migrated.
			$message = wfMsgHtml( 'centralauth-prefs-not-managed' );
		}

		$manageLink = $skin->makeKnownLinkObj( $special,
			wfMsgHtml( 'centralauth-prefs-manage' ) );
		$html .= $prefsForm->tableRow(
			wfMsgHtml( 'centralauth-prefs-status' ),
			"$message<br />($manageLink)" );
		return true;
	}
	
	static function onAbortNewAccount( $user, &$abortError ) {
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists() ) {
			wfLoadExtensionMessages('SpecialCentralAuth');
			$abortError = wfMsg( 'centralauth-account-exists' );
			return false;
		}
		return true;
	}
	
	static function onUserLoginComplete( &$user, &$inject_html ) {
		global $wgCentralAuthCookies;
		if( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		
		if ($centralUser->exists() && $centralUser->isAttached()) {
			$centralUser->setGlobalCookies($user);
		} else {
			return true;
		}
		
		// On other wikis
		global $wgCentralAuthAutoLoginWikis;
		
		$inject_html .= Xml::openElement( 'p' );
		
		foreach( $wgCentralAuthAutoLoginWikis as $dbname ) {
			$data = array();
			$data['username'] = $user->getName();
			$data['token'] = $centralUser->getAuthToken();
			$data['remember'] = $user->getOption( 'rememberpassword' );
			$data['wiki'] = $dbname;
			
			$login_token = wfGenerateToken( $centralUser->getId() );
			
			global $wgMemc;
			$wgMemc->set( 'centralauth_logintoken_'.$login_token, $data, 600 );
			
			$wiki = WikiMap::byDatabase( $dbname );
			$url = $wiki->getUrl( 'Special:AutoLogin' );
			
			$querystring = 'token=' . $login_token;
			
			if (strpos($url, '?') > 0) {
				$url .= "&$querystring";
			} else {
				$url .= "?$querystring";
			}
			
			$inject_html .= Xml::element( 'img', array( 'src' => $url ) );
		}
		
		$inject_html .= Xml::closeElement( 'p' );
		
		return true;
	}
	
	static function onAutoAuthenticate( &$user ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;
		if( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}
		$prefix = $wgCentralAuthCookiePrefix;
		
		if( $user->isLoggedIn() ) {
			// Already logged in; don't worry about the global session.
			return true;
		}
		
		if (isset($_COOKIE["{$prefix}User"]) && isset($_COOKIE["{$prefix}Token"])) {
			list ($username, $token) = array( $_COOKIE["{$prefix}User"], $_COOKIE["{$prefix}Token"] );
			$centralUser = new CentralAuthUser( $username );

			if ( !$centralUser->authenticateWithToken( $token ) == 'ok' ) {
				wfDebug( __METHOD__.": token mismatch\n" );
			} elseif ( !$centralUser->isAttached() ) {
				wfDebug( __METHOD__.": not attached\n" );
			} else {
				// Auth OK.
				wfDebug( __METHOD__.": logged in from token\n" );
				$user = self::initSession( $username, $token );
				$user->centralAuthObj = $centralUser;
			}
		} elseif (isset($_COOKIE["{$prefix}Session"])) {
			$session_id = $_COOKIE["{$prefix}Session"];
			
			global $wgMemc;
			$global_session = $wgMemc->get( "centralauth_session_$session_id" );
			
			$token = $global_session['token'];
			$username = $global_session['user'];
			
			if ($global_session['expiry'] < time()) {
				wfDebug( __METHOD__.": session expired\n" );
				return true; // Session has expired. Don't let it be logged-in with.
			}
			
			$centralUser = new CentralAuthUser( $username );
			
			if ( !$centralUser->authenticateWithToken( $token ) == 'ok' ) {
				wfDebug( __METHOD__.": token mismatch\n" );
			} elseif ( !$centralUser->isAttached() && $user->idForName( $username ) ) {
				wfDebug( __METHOD__.": exists, and not attached\n" );
			} else {
				// Auth OK.
				wfDebug( __METHOD__.": logged in from session\n" );
				$user = self::initSession( $username, $token );
				$user->centralAuthObj = $centralUser;
			}
		} else {
			wfDebug( __METHOD__.": no token or session\n" );
		}
		
		return true;
	}
	
	static function onUserLogout( &$user ) {
		global $wgCentralAuthCookies;
		if( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		
		if ($centralUser->exists()) {
			$centralUser->deleteGlobalCookies();
		}
		
		return true;
	}
	
	static function onUserLogoutComplete( &$user, &$inject_html ) {
		global $wgCentralAuthCookies, $wgCentralAuthAutoLoginWikis;
		if( !$wgCentralAuthCookies || !$wgCentralAuthAutoLoginWikis ) {
			// Nothing to do.
			return true;
		}

		// Generate the images
		$inject_html .= Xml::openElement( 'p' );
		
		foreach( $wgCentralAuthAutoLoginWikis as $dbname ) {
			$wiki = WikiMap::byDatabase( $dbname );
			$url = $wiki->getUrl( 'Special:AutoLogin' );
			
			if (strpos($url, '?') > 0) {
				$url .= '&logout=1';
			} else {
				$url .= '?logout=1';
			}
			
			$inject_html .= Xml::element( 'img', array( 'src' => $url, 'alt' => '' ) );
		}
		
		$inject_html .= Xml::closeElement( 'p' );
		return true;
	}
	
	static function onGetCacheVaryCookies( $out, &$cookies ) {
		global $wgCentralAuthCookiePrefix;
		$cookies[] = $wgCentralAuthCookiePrefix . 'Token';
		$cookies[] = $wgCentralAuthCookiePrefix . 'Session';
		$cookies[] = $wgCentralAuthCookiePrefix . 'LoggedOut';
		return true;
	}
	
	static function onUserArrayFromResult( &$userArray, $res ) {
		$userArray = CentralAuthUserArray::newFromResult( $res );
		return true;
	}
	
	/**
	 * Don't allow an attached local account to be renamed with the old system.
	 */
	static function onRenameUserAbort( $userId, $oldName, $newName ) {
		$oldCentral = new CentralAuthUser( $oldName );
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			global $wgOut;
			wfLoadExtensionMessages('SpecialCentralAuth');
			$wgOut->addWikiMsg( 'centralauth-renameuser-abort', $oldName, $newName );
			return false;
		}
		$newCentral = new CentralAuthUser( $newName );
		if ( $newCentral->exists() ) {
			global $wgOut;
			wfLoadExtensionMessages('SpecialCentralAuth');
			$wgOut->addWikiMsg( 'centralauth-renameuser-exists', $oldName, $newName );
			return false;
		}

		// If no central record is present or this local account isn't attached,
		// do as thou wilt.
		return true;
	}
	
	/**
	 * When renaming an account, ensure that the presence records are updated.
	 */
	static function onRenameUserComplete( $userId, $oldName, $newName ) {
		$oldCentral = new CentralAuthUser( $oldName );
		$oldCentral->removeLocalName( wfWikiID() );

		$newCentral = new CentralAuthUser( $newName );
		$newCentral->addLocalName( wfWikiID() );

		return true;
	}

	/**
	 * Helper function for onAutoAuthenticate
	 */
	static function initSession( $username, $token ) {
		$user = User::newFromName( $username );
		global $wgAuth;
		
		if ($wgAuth->autoCreate() && $user->getId() == 0) {
			// User does not already exist locally. Create them :)
			$user->addToDatabase();

			$user->setToken();

			$wgAuth->initUser( $user, true );
			$wgAuth->updateUser( &$user );
	
			# Update user count
			$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssUpdate->doUpdate();
		}
		
		wfSetupSession();
		if ($token != @$_SESSION['globalloggedin'] ) {
			$_SESSION['globalloggedin'] = $token;
			$user->invalidateCache();
			wfDebug( 'centralauth', "Initialising session for $username with token $token." );
		} else {
			wfDebug( 'centralauth', "Session already initialised for $username with token $token." );
		}

		return $user;
	}

	static function onUserGetEmail( $user, &$email ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$email = $ca->getEmail();
		}
		return true;
	}

	static function onUserGetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$timestamp = $ca->getEmailAuthenticationTimestamp();
		}
		return true;
	}

	static function onUserSetEmail( $user, &$email ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmail( $email );
		}
		return true;
	}

	static function onUserSaveSettings( $user ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->saveSettings();
		}
		return true;
	}

	static function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmailAuthenticationTimestamp( $timestamp );
		}
		return true;
	}
}
