<?php

class CentralAuthHooks {
	/**
	 * Callback to register with $wgExtensionFunctions to complete configuration
	 * after other initial configuration has completed. This can be used to
	 * avoid extension ordering issues and do things that are dependent on
	 * feature flags.
	 */
	public static function onRunExtensionFunctions() {
		global $wgAutoloadClasses, $wgExtensionCredits, $wgHooks;
		global $wgSpecialPages, $wgResourceModules;
		global $wgCentralAuthEnableGlobalRenameRequest;
		global $wgCentralAuthCheckSULMigration;
		$caBase = __DIR__ . '/..';

		if ( class_exists( 'RenameuserSQL' ) ) {
			// Credits should only appear on wikis with Extension:Renameuser
			// installed
			$wgExtensionCredits['specialpage'][] = array(
				'path' => "{$caBase}/CentralAuth.php",
				'name' => 'Renameuser for CentralAuth',
				'url' => 'https://www.mediawiki.org/wiki/Extension:CentralAuth',
				'author' => array( 'Kunal Mehta', 'Marius Hoch', 'Chris Steipp' ),
				'descriptionmsg' => 'centralauth-rename-desc',
			);
		}

		if ( class_exists( 'AntiSpoof' ) ) {
			// If AntiSpoof is installed, we can do some AntiSpoof stuff for CA
			$wgExtensionCredits['antispam'][] = array(
				'path' => "{$caBase}/CentralAuth.php",
				'name' => 'AntiSpoof for CentralAuth',
				'url' => 'https://www.mediawiki.org/wiki/Extension:CentralAuth',
				'author' => 'Sam Reed',
				'descriptionmsg' => 'centralauth-antispoof-desc',
			);
			$wgAutoloadClasses['CentralAuthSpoofUser'] =
				"$caBase/AntiSpoof/CentralAuthSpoofUser.php";
			$wgAutoloadClasses['CentralAuthAntiSpoofHooks'] =
				"$caBase/AntiSpoof/CentralAuthAntiSpoofHooks.php";

			$wgHooks['AbortNewAccount'][] =
				'CentralAuthAntiSpoofHooks::asAbortNewAccountHook';
			$wgHooks['AddNewAccount'][] =
				'CentralAuthAntiSpoofHooks::asAddNewAccountHook';
			$wgHooks['RenameUserComplete'][] =
				'CentralAuthAntiSpoofHooks::asAddRenameUserHook';
		}

		if ( $wgCentralAuthEnableGlobalRenameRequest ) {
			$wgExtensionCredits['specialpage'][] = array(
				'path' => "{$caBase}/CentralAuth.php",
				'name' => 'GlobalRenameRequest',
				'author' => 'Bryan Davis',
				'url' => '//www.mediawiki.org/wiki/Extension:CentralAuth',
				'descriptionmsg' => 'globalrenamerequest-desc',
			);
			$wgSpecialPages['GlobalRenameRequest'] = 'SpecialGlobalRenameRequest';
			$wgResourceModules['ext.centralauth.globalrenamerequest'] = array(
				'scripts'       => array(
					'ext.centralauth.globalrenamerequest.js',
				),
				'dependencies' => array( 'oojs-ui' ),
				'messages' => array(
					'globalrenamerequest-email-why-label',
					'globalrenamerequest-email-why-explain',
				),
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
			);
			$wgResourceModules['ext.centralauth.globalrenamerequest.styles'] = array(
				'position'		=> 'top',
				'styles'        => 'ext.centralauth.globalrenamerequest.css',
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
			);

			$wgExtensionCredits['specialpage'][] = array(
				'path' => "{$caBase}/CentralAuth.php",
				'name' => 'GlobalRenameQueue',
				'author' => 'Bryan Davis',
				'url' => '//www.mediawiki.org/wiki/Extension:CentralAuth',
				'descriptionmsg' => 'globalrenamequeue-desc',
			);
			$wgSpecialPages['GlobalRenameQueue'] = 'SpecialGlobalRenameQueue';
			$wgResourceModules['ext.centralauth.globalrenamequeue'] = array(
				'scripts'        => 'ext.centralauth.globalrenamequeue.js',
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
				'messages' => array(
					'centralauth-rename-confirm',
					'centralauth-rename-deny'
				)
			);
			$wgResourceModules['ext.centralauth.globalrenamequeue.styles'] = array(
				'position'		=> 'top',
				'styles'        => 'ext.centralauth.globalrenamequeue.less',
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
			);
		}

		if ( $wgCentralAuthCheckSULMigration ) {
			// Install hidden special page for renamed users
			$wgSpecialPages['SulRenameWarning'] = 'SpecialSulRenameWarning';
			$wgHooks['PostLoginRedirect'][] = 'CentralAuthHooks::onPostLoginRedirect';
		}

		if ( $wgCentralAuthCheckSULMigration ) {
			$wgHooks['LoginUserMigrated'][] =
				'CentralAuthHooks::onLoginUserMigrated';
		}
	}

	/**
	 * @param $type
	 * @param $action
	 * @param $title
	 * @param $skin Skin|null If null, we want to use the wiki content language, since that will go to the IRC feed.
	 * @param $params
	 * @param $filterWikilinks bool
	 * @return String
	 */
	public static function onHandleWikiSetLogEntry( $type, $action, $title, $skin, $params, $filterWikilinks = false ) {
		if ( $skin ) {
			$link = Linker::link( $title, htmlspecialchars( $params[0] ) );
		} else {
			$link = $params[0];
		}

		switch( $action ) {
			case 'newset':
				$args = array( WikiSet::formatType( $params[1] ), $params[2] );
				break;
			case 'setrename':
				$args = array( $params[1] );
				break;
			case 'setnewtype':
				$args = array( WikiSet::formatType( $params[1] ), WikiSet::formatType( $params[2] ) );
				break;
			case 'setchange':
				$args = array( $params[1]
					? $params[1] : wfMessage( 'rightsnone' )->text(), $params[2] ? $params[2] : wfMessage( 'rightsnone' )->text() );
				break;
			default: //'deleteset'
				$args = array();
		}

		// Give grep a chance to find the usages:
		// centralauth-rightslog-entry-newset, centralauth-rightslog-entry-setrename,
		// centralauth-rightslog-entry-setnewtype, centralauth-rightslog-entry-setchange,
		// centralauth-rightslog-entry-deleteset
		$msg = wfMessage( "centralauth-rightslog-entry-{$action}", $link )->params( $args );
		if( $skin ) {
			return $msg->text();
		} else {
			return $msg->inContentLanguage()->text();
		}
	}

	/**
	 * Format global group rename log entries
	 *
	 * @param $type
	 * @param $action
	 * @param $title
	 * @param $skin Skin|null If null, we want to use the wiki content language, since that will go to the IRC feed.
	 * @param $params
	 * @param $filterWikilinks bool
	 *
	 * @return String
	 */
	public static function onHandleGrouprenameLogEntry( $type, $action, $title, $skin, $params, $filterWikilinks = false ) {
		// $params[0] is the new one, $params[1] the old one
		if ( $skin ) {
			$params[0] = Linker::link( Title::newFromText( $params[0] ), htmlspecialchars( $params[0] ) );
			$params[1] = Linker::link( Title::newFromText( $params[1] ), htmlspecialchars( $params[1] ) );
		} else {
			$params[0] = htmlspecialchars( $params[0] );
			$params[1] = htmlspecialchars( $params[1] );
		}

		$msg = wfMessage( 'centralauth-rightslog-entry-grouprename' )->rawParams( $params[0], $params[1] );
		if ( $skin ) {
			return $msg->text();
		} else {
			return $msg->inContentLanguage()->text();
		}
	}

	/**
	 * This hook is used in cases where SpecialPageFactory::getPageList() is called before
	 * $wgExtensionFunctions are run, which happens when E:ShortUrl is installed.
	 *
	 * @param array $list
	 * @return bool
	 */
	public static function onSpecialPage_initList( &$list ) {
		global $wgCentralAuthEnableGlobalRenameRequest, $wgCentralAuthCheckSULMigration;
		if ( $wgCentralAuthEnableGlobalRenameRequest ) {
			$list['GlobalRenameRequest'] = 'SpecialGlobalRenameRequest';
			$list['GlobalRenameQueue'] = 'SpecialGlobalRenameQueue';
		}

		if ( $wgCentralAuthCheckSULMigration ) {
			$list['SulRenameWarning'] = 'SpecialSulRenameWarning';
		}

		return true;
	}

	/**
	 * @param $auth
	 * @return bool
	 */
	static function onAuthPluginSetup( &$auth ) {
		$auth = new CentralAuthPlugin;
		return true;
	}

	/**
	 * Make sure migration information in localuser table is populated
	 * on local account creation
	 * @param $user User
	 * @param $byEmail bool
	 * @return bool
	 */
	static function onAddNewAccount( $user, $byEmail ) {
		$central = CentralAuthUser::getInstance( $user );
		$central->addLocalName( wfWikiID() );
		return true;
	}

	/**
	 * Add a little pretty to the preferences user info section
	 *
	 * @param $user User
	 * @param $preferences array
	 * @return bool
	 */
	static function onGetPreferences( $user, &$preferences ) {
		global $wgLang;

		// Possible states:
		// - account not merged at all
		// - global accounts exists, but this local account is unattached
		// - this local account is attached, but migration incomplete
		// - all local accounts are attached (no $message shown)

		$global = CentralAuthUser::getInstance( $user );
		$unattached = count( $global->listUnattached() );
		if ( $global->exists() ) {
			if ( $global->isAttached() && $unattached ) {
				// Migration incomplete - unattached accounts at other wikis
				$attached = count( $global->listAttached() );
				$message = wfMessage( 'centralauth-prefs-unattached' )->parse() .
					'<br />' .
					wfMessage( 'centralauth-prefs-count-attached' )->numParams( $attached )->parse() .
					'<br />' .
					wfMessage( 'centralauth-prefs-count-unattached' )->numParams( $unattached )->parse();
			} elseif ( !$global->isAttached() ) {
				// Global account exists but the local account is not attached
				$message = wfMessage( 'centralauth-prefs-detail-unattached' )->parse();
			}
		} else {
			// No global account
			$message = wfMessage( 'centralauth-prefs-not-managed' )->parse();
		}

		$manageLinks = array();
		if ( $unattached && $user->isAllowed( 'centralauth-merge' ) ) {
			$manageLinks[] = Linker::linkKnown( SpecialPage::getTitleFor( 'MergeAccount' ),
				wfMessage( 'centralauth-prefs-manage' )->parse() );
		}
		$manageLinks[] = Linker::linkKnown( SpecialPage::getTitleFor( 'CentralAuth', $user->getName() ),
			wfMessage( 'centralauth-prefs-view' )->parse() );
		$manageLinkList = $wgLang->pipeList( $manageLinks );

		$preferences['globalaccountstatus'] = array(
			'section' => 'personal/info',
			'label-message' => 'centralauth-prefs-status',
			'type' => 'info',
			'raw' => true,
			'default' => $manageLinkList
		);
		if ( isset( $message ) ) {
			$manageLinkList = wfMessage( 'parentheses', $manageLinkList )->text(); // looks weird otherwise
			$preferences['globalaccountstatus']['default'] = "$message<br />$manageLinkList";
		}

		return true;
	}

	/**
	 * @param $user User
	 * @param $abortError
	 * @return bool
	 */
	static function onAbortNewAccount( $user, &$abortError ) {
		global $wgCentralAuthPreventUnattached;
		global $wgCentralAuthEnableGlobalRenameRequest;

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists() || $centralUser->renameInProgressOn( wfWikiID() ) ) {
			$abortError = wfMessage( 'centralauth-account-exists' )->text();
			return false;
		}

		if ( $wgCentralAuthPreventUnattached && !$centralUser->exists()
			&& $centralUser->listUnattached()
		) {
			// If no global account exists and there are unattached accounts,
			// don't let a new unattached account get created. We'll pretend
			// that the name is already taken, because someone will eventually
			// get it. See bug 67901.
			$abortError = wfMessage( 'centralauth-account-unattached-exists' )->text();
			return false;
		}

		// Block account creation if name is a pending rename request
		if ( $wgCentralAuthEnableGlobalRenameRequest &&
			GlobalRenameRequest::nameHasPendingRequest( $user->getName() )
		) {
			$abortError = wfMessage( 'centralauth-account-rename-exists' )->text();
			return false;
		}

		return true;
	}

	/**
	 * Prevent accounts from being autocreated if a rename is in progress
	 * @param User $user
	 * @param $msg
	 * @return bool
	 */
	static function onAbortAutoAccount( User $user, &$msg ) {
		$caUser = CentralAuthUser::getInstance( $user );
		if ( $caUser->renameInProgress() ) {
			$msg = 'centralauth-rename-abortlogin';
			return false;
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param string $pass
	 * @param integer &$retval
	 * @param string &$msg
	 * @return bool
	 */
	static function onAbortLogin( $user, $pass, &$retval, &$msg ) {
		$centralUser = CentralAuthUser::getInstance( $user );

		// Since logins are rare, check the actual DB
		$rename = $centralUser->renameInProgressOn( wfWikiID() );
		if ( $rename ) {
			// This wiki specifically has a rename in progress, so always abort
			$retval = LoginForm::NOT_EXISTS;
			$msg = 'centralauth-rename-abortlogin';
			return false;
		}

		// Now check if the user is the target of a rename anywhere
		$rename = $centralUser->renameInProgress();
		if ( $rename ) {
			// It's possible a user is being renamed but someone else with
			// an unattached account is trying to login. Since we've already
			// moved everything over to the new account name, we only need
			// to check one username.
			$newCAUser = $rename[1] === $user->getName()
				? $centralUser
				: new CentralAuthUser( $rename[0] );

			if ( $newCAUser->isAttached() ) {
				// If there is an account using that name that exists on this wiki
				// reject the login.
				$retval = LoginForm::NOT_EXISTS;
				// This is an icky hack so the message is ->parse()d and
				// gets the username as a parameter
				$msg = 'centralauth-rename-abortlogin';
				return false;
			}
		}

		switch ( (string)$centralUser->canAuthenticate() ) {
			case '1': // boolean true
				return true;

			case 'no user':
				// If they're local, we still want to let them log in. And if they
				// don't exist, this hook wouldn't have even been called.
				return true;

			case 'locked':
				$msg = 'centralauth-login-error-locked';
				$retval = LoginForm::USER_BLOCKED;
				return false;

			default:
				throw new Exception( "Unexpected result from CentralAuthUser::canAuthenticate()" );
		}
		return true;
	}

	/**
	 * Inform a user that their username was renamed as part of SUL
	 * Finalization, if their previous username doesn't exist any more (winner
	 * was renamed).
	 * @param User $user
	 * @param string &$msg return error key, or return an array with key and params
	 * @return bool
	 */
	public static function onLoginUserMigrated( $user, &$msg ) {
		global $wgCentralAuthCheckSULMigration;
		if ( $wgCentralAuthCheckSULMigration ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $user->getID() === 0 && !$centralUser->exists() ) {
				// If the local and global accounts don't exist,
				// otherwise wgAuth will handle those.
				$testName = $user->getName() . '~' . wfWikiID();
				$test = new CentralAuthUser( $testName );
				if ( $test->exists() && $test->isAttached() ) {
					$msg = array( 'centralauth-abortlogin-renamed', $testName );
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Show a nicer error when the user account does not exist on the local wiki, but
	 * does exist globally
	 * @param $users Array
	 * @param $data Array
	 * @param $abortError String
	 * @return bool
	 */
	static function onSpecialPasswordResetOnSubmit( &$users, $data, &$abortError ) {
		if ( count( $users ) == 0 || !$users[0] instanceof User ){
			// We can't handle this
			return true;
		}

		$firstUser = $users[0];
		if( !$firstUser->getID() ) {
			$centralUser = CentralAuthUser::getInstance( $firstUser );
			if ( $centralUser->exists() ) {
				$abortError = array( 'centralauth-account-exists-reset', $centralUser->getName() );
				return false;
			}
		}

		return true;
	}

	/**
	 * @param $user User
	 * @param $inject_html string
	 * @return bool
	 */
	static function onUserLoginComplete( &$user, &$inject_html ) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthCookies;
		global $wgCentralAuthCheckSULMigration;

		if ( $wgCentralAuthCheckSULMigration &&
			isset( $user->sulRenamed ) &&
			$user->sulRenamed === true
		) {
			// Add a flag in the user's session to track that they authenticated
			// with a pre-migration username.
			$request = $user->getRequest();
			$request->setSessionData( 'CentralAuthForcedRename', true );
		}

		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		// Redirect to the central wiki and back to complete login, if necessary
		$centralUser = CentralAuthUser::getInstance( $user );
		self::doCentralLoginRedirect( $user, $centralUser, $inject_html );

		return true;
	}

	/**
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @return String
	 */
	public static function getDomainAutoLoginHtml( User $user, CentralAuthUser $centralUser ) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;

		// No other domains
		if ( !$wgCentralAuthAutoLoginWikis ) {
			$inject_html = wfMessage( 'centralauth-login-no-others' )->escaped();
		} else {
			$inject_html = '<div class="centralauth-login-box"><p>' .
				wfMessage( 'centralauth-login-progress', $user->getName() )->escaped() . "</p>\n<p>";
			foreach ( $wgCentralAuthAutoLoginWikis as $alt => $wikiID ) {
				$wiki = WikiMap::getWiki( $wikiID );
				// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
				$params = array(
					'type' => 'icon',
					'from' => wfWikiID(),
				);
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$url = wfAppendQuery(
					$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
					$params
				);
				$inject_html .= Xml::element( 'img',
					array(
						'src' => $url,
						'alt' => $alt,
						'title' => $alt,
						'width' => 20,
						'height' => 20,
						'style' => 'border: 1px solid #ccc;',
					)
				);
			}
			$inject_html .= "</p></div>\n";
		}
		if ( $wgCentralAuthLoginWiki ) {
			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/refreshCookies' ), array(
				'type' => '1x1',
				'wikiid' => wfWikiID(),
				'proto' => RequestContext::getMain()->getRequest()->detectProtocol(),
			) );
			$inject_html .= Xml::element( 'img',
				array(
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none; position: absolute;',
				)
			);
		}

		return $inject_html;
	}

	/**
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @param string $inject_html
	 * @return bool
	 */
	protected static function doCentralLoginRedirect( User $user, CentralAuthUser $centralUser, &$inject_html ) {
		global $wgCentralAuthLoginWiki, $wgSecureLogin;

		$context = RequestContext::getMain();
		$request = $context->getRequest();

		if ( !$wgCentralAuthLoginWiki || defined( 'MW_API' ) ) {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
			return true;
		}

		// Check that this is actually for a special login page view
		if ( $context->getTitle()->isSpecial( 'Userlogin' ) && $request->wasPosted() ) {
			// User will be redirected to Special:CentralLogin/start (central wiki),
			// then redirected back to Special:CentralLogin/complete (this wiki).
			// Sanity check that "returnto" is not one of the central login pages. If it
			// is, then clear the "returnto" options (LoginForm will use the main page).
			$returnTo = $request->getVal( 'returnto', '' );
			$returnToQuery = $request->getVal( 'returntoquery', '' );
			$returnToTitle = Title::newFromText( $returnTo );
			if ( $returnToTitle && $returnToTitle->isSpecial( 'CentralLogin' ) ) {
				$returnTo = '';
				$returnToQuery = '';
			}

			// Determine the final protocol of page, after login
			$finalProto = $request->detectProtocol();
			$secureCookies = ( $finalProto === 'https' );

			if ( $wgSecureLogin ) {
				$finalProto = 'http';

				if ( $request->getBool( 'wpForceHttps', false ) ||
					( $user->getBoolOption( 'prefershttps' ) && wfCanIPUseHTTPS( $request->getIP() ) )
				) {
					$finalProto = 'https';
				}

				$secureCookies = ( ( $finalProto === 'https' ) && $user->getBoolOption( 'prefershttps' ) );
			}

			// When POSTs triggered from Special:CentralLogin/start are sent back to
			// this wiki, the token will be checked to see if it was signed with this.
			// This is needed as Special:CentralLogin/start only takes a token argument
			// and we need to make sure an agent requesting such a URL actually initiated
			// the login request that spawned that token server-side.
			$secret = MWCryptRand::generateHex( 32 );
			$request->setSessionData( 'CentralAuth:autologin:current-attempt', array(
				'secret'        => $secret,
				'remember'      => $request->getCheck( 'wpRemember' ),
				'returnTo'      => $returnTo,
				'returnToQuery' => $returnToQuery,
				'stickHTTPS'    => $secureCookies, // cookies set secure or not (local CentralAuth cookies)
				'finalProto'    => $finalProto, // final page http or https
				'type'          => $request->getText( 'type' )
			) );

			// Create a new token to pass to Special:CentralLogin/start (central wiki)
			$token = MWCryptRand::generateHex( 32 );
			$key = CentralAuthUtils::memcKey( 'central-login-start-token', $token );
			$data = array(
				'secret'        => $secret,
				'name'          => $centralUser->getName(),
				'guid'          => $centralUser->getId(),
				'wikiId'        => wfWikiId(),
				'secureCookies' => $secureCookies, // (bool) cookies secure or not
				'finalProto'    => $finalProto, // http or https for very final page
				'currentProto'  => $request->detectProtocol() // current proto (in case login is https, but final page is http)
			);
			Hooks::run( 'CentralAuthLoginRedirectData', array( $centralUser, &$data ) );
			CentralAuthUtils::getSessionCache()->set( $key, $data, 60 );

			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$context->getOutput()->redirect( // expands to PROTO_CURRENT
				wfAppendQuery( $wiki->getFullUrl( 'Special:CentralLogin/start' ), "token=$token" )
			);
			// Set $inject_html to some text to bypass the LoginForm redirection
			$inject_html .= '<!-- do CentralAuth redirect -->';
		} else {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
		}

		return true;
	}

	private static function isMobileDomain() {
		return class_exists( 'MobileContext' )
			&& MobileContext::singleton()->usingMobileDomain();
	}

	/**
	 * @param $user User
	 * @return bool
	 */
	static function onUserLogout( &$user ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( $centralUser->exists() ) {
			DeferredUpdates::addCallableUpdate( function() use ( $centralUser ) {
				$centralUser->resetAuthToken();
			} );
		}

		// Clean up any possible forced rename markers
		$user->getRequest()->setSessionData( 'CentralAuthForcedRename', null );

		return true;
	}

	/**
	 * @param $user
	 * @param $inject_html
	 * @param $userName
	 * @return bool
	 */
	static function onUserLogoutComplete( &$user, &$inject_html, $userName ) {
		global $wgCentralAuthCookies, $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;

		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		$wikis = $wgCentralAuthAutoLoginWikis;
		if ( $wgCentralAuthLoginWiki ) {
			$wikis[$wgCentralAuthLoginWiki] = $wgCentralAuthLoginWiki;
		}

		// No other domains
		if ( !$wikis ) {
			$inject_html = wfMessage( 'centralauth-logout-no-others' )->escaped();
		} else {
			$inject_html = '<div class="centralauth-logout-box"><p>' .
				wfMessage( 'centralauth-logout-progress', $user->getName() )->escaped() . "</p>\n<p>";
			foreach ( $wikis as $alt => $wikiID ) {
				$wiki = WikiMap::getWiki( $wikiID );
				// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
				$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/deleteCookies' ), array(
					'type' => 'icon',
				) );
				$inject_html .= Xml::element( 'img',
					array(
						'src' => $url,
						'alt' => $alt,
						'title' => $alt,
						'width' => 20,
						'height' => 20,
						'style' => 'border: 1px solid #ccc;',
					)
				);
			}
			$inject_html .= "</p></div>\n";
		}

		return true;
	}

	/**
	 * @param $userArray
	 * @param $res
	 * @return bool
	 */
	static function onUserArrayFromResult( &$userArray, $res ) {
		$userArray = CentralAuthUserArray::newFromResult( $res );
		return true;
	}

	/**
	 * Warn bureaucrat about possible conflicts with unified accounts
	 * @param $oldName
	 * @param $newName
	 * @param $warnings
	 * @return bool
	 */
	static function onRenameUserWarning( $oldName, $newName, &$warnings ) {
		$oldCentral = new CentralAuthUser( $oldName );
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$warnings[] = array( 'centralauth-renameuser-merged', $oldName, $newName );
		}
		if ( $oldCentral->renameInProgress() ) {
			$warnings[] = array( 'centralauth-renameuser-global-inprogress', $oldName );
		}

		$newCentral = new CentralAuthUser( $newName );
		if ( $newCentral->exists() && !$newCentral->isAttached() ) {
			$warnings[] = array( 'centralauth-renameuser-reserved', $oldName, $newName );
		}

		if ( $newCentral->renameInProgress() ) {
			$warnings[] = array( 'centralauth-renameuser-global-inprogress', $newName );
			// Can potentially be renaming two accounts into the same name, so throw an error
			throw new ErrorPageError( 'error', 'centralauth-renameuser-global-inprogress', array( $newName ) );
		}

		return true;
	}

	/**
	 * @param $uid int
	 * @param $oldName string
	 * @param $newName string
	 * @return bool
	 */
	static function onRenameUserPreRename( $uid, $oldName, $newName ) {
		$oldCentral = new CentralAuthUser( $oldName );
		// If we're doing a global rename, the account will not get unattached
		// because the old account no longer exists
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$oldCentral->adminUnattach( array( wfWikiID() ) );
		}
		return true;
	}

	/**
	 * When renaming an account, ensure that the presence records are updated.
	 * @param $userId int
	 * @param $oldName string
	 * @param $newName string
	 * @return bool
	 */
	static function onRenameUserComplete( $userId, $oldName, $newName ) {
		$oldCentral = new CentralAuthUser( $oldName );
		$newCentral = new CentralAuthUser( $newName );

		if ( $newCentral->exists() && $oldCentral->renameInProgressOn( wfWikiID() ) ) {
			// This is a global rename, just update the row.
			$oldCentral->updateLocalName( wfWikiID(), $newName );
		} else {
			$oldCentral->removeLocalName( wfWikiID() );
			$newCentral->addLocalName( wfWikiID() );
		}

		return true;
	}

	/**
	 * @param $user
	 * @param $email
	 * @return bool
	 */
	static function onUserGetEmail( $user, &$email ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$email = $ca->getEmail();
		}
		return true;
	}

	/**
	 * @param $user
	 * @param $timestamp
	 * @return bool
	 */
	static function onUserGetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			if ( $ca->isLocked() ) {
				// Locked users shouldn't be receiving email (T87559)
				$timestamp = null;
			} else {
				$timestamp = $ca->getEmailAuthenticationTimestamp();
			}
		}
		return true;
	}

	/**
	 * @param $user
	 * @return bool
	 */
	static function onUserInvalidateEmailComplete( $user ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmail( '' );
			$ca->setEmailAuthenticationTimestamp( null );
			$ca->saveSettings();
		}
		return true;
	}

	/**
	 * @param $user
	 * @param $email
	 * @return bool
	 */
	static function onUserSetEmail( $user, &$email ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmail( $email );
		}
		return true;
	}

	/**
	 * @param $user
	 * @return bool
	 */
	static function onUserSaveSettings( $user ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->saveSettings();
		}
		return true;
	}

	/**
	 * @param $user
	 * @param $timestamp
	 * @return bool
	 */
	static function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmailAuthenticationTimestamp( $timestamp );
			$ca->saveSettings();
		}
		return true;
	}

	/**
	 * @param $user User
	 * @param $rights
	 * @return bool
	 */
	static function onUserGetRights( $user, &$rights ) {
		if ( !$user->isAnon() ) {
			$centralUser = CentralAuthUser::getInstance( $user );

			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$extraRights = $centralUser->getGlobalRights();

				$rights = array_merge( $extraRights, $rights );
			}
		}

		return true;
	}

	/**
	 * @param $id user_id integer
	 * @param User $user
	 * @param SpecialPage $sp
	 */
	static function onSpecialContributionsBeforeMainOutput( $id, User $user, SpecialPage $sp ) {
		if ( $user->isAnon() ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached()
			|| !$centralUser->isLocked() || $centralUser->isHidden()
		) {
			return true;
		}

		$out = $sp->getOutput();
		$count = LogEventsList::showLogExtract(
			$out,
			array( 'globalauth' ),
			MWNamespace::getCanonicalName( NS_USER ) . ":{$user}@global",
			'',
			array(
				'lim' => 1,
				'showIfEmpty' => false,
				'msgKey' => array(
					'centralauth-contribs-locked-log',
					$user->getName()
				),
				'offset' => '',
			)
		);

		if ( $count === 0 ) { // we couldn't load the log entry
			$out->wrapWikiMsg( '<div class="mw-warning-with-logexcerpt">$1</div>',
				array( 'centralauth-contribs-locked', $user )
			);
		}

		return true;
	}

	/**
	 * @param &$vars
	 * @return bool
	 */
	static function onMakeGlobalVariablesScript( &$vars ) {
		global $wgUser;
		if ( !$wgUser->isAnon() ) {
			$centralUser = CentralAuthUser::getInstance( $wgUser );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$vars['wgGlobalGroups'] = $centralUser->getGlobalGroups();
			}
		}
		return true;
	}

	/**
	 * @param &$vars
	 * @return bool
	 */
	static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgCentralAuthLoginWiki;
		if ( $wgCentralAuthLoginWiki && $wgCentralAuthLoginWiki !== wfWikiID() ) {
			$url = WikiMap::getForeignURL(
				$wgCentralAuthLoginWiki, 'Special:CentralAutoLogin/checkLoggedIn'
			);
			if ( $url !== false ) {
				$params = array(
					'type' => 'script',
					'wikiid' => wfWikiID(),
				);
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$vars['wgCentralAuthCheckLoggedInURL'] = wfAppendQuery( $url, $params );
			}
		}
	}

	/**
	 * @param $title Title
	 * @param $user User
	 * @param $action
	 * @param $result
	 * @return bool
	 */
	static function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ) {
		global $wgCentralAuthLockedCanEdit, $wgDisableUnmergedEditing;
		if ( $action == 'read' || $user->isAnon() ) {
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( 	$wgDisableUnmergedEditing
			&& ( $action === 'edit' || $action === 'delete' )
			&& !$centralUser->exists()
			&& !$title->inNamespaces( NS_USER_TALK, NS_PROJECT_TALK )
		) {
			$result = 'centralauth-error-unmerged';
			return false;
		}

		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}
		if (
			$centralUser->isOversighted() ||	// Oversighted users should *never* be able to edit
			( $centralUser->isLocked() && !in_array( $title->getPrefixedText(), $wgCentralAuthLockedCanEdit ) )
				) {
			$result = 'centralauth-error-locked';
			return false;
		}
		return true;
	}

	/**
	 * @param &$out OutputPage
	 * @param &$skin Skin
	 * @return bool
	 * @todo Add 1x1 images somewhere besides page content
	 */
	static function onBeforePageDisplay( &$out, &$skin ) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthUseEventLogging;
		if ( $out->getUser()->isAnon() ) {
			if ( $wgCentralAuthLoginWiki && wfWikiID() !== $wgCentralAuthLoginWiki ) {
				$out->addModules( 'ext.centralauth.centralautologin' );

				// For non-JS clients. Use WikiMap to avoid localization of the
				// 'Special' namespace, see bug 54195.
				$wiki = WikiMap::getWiki( wfWikiID() );
				$params = array(
					'type' => '1x1',
				);
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$url = wfAppendQuery(
					$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
					$params
				);
				$out->addHTML( '<noscript>' . Xml::element( 'img',
					array(
						'src' => $url,
						'alt' => '',
						'title' => '',
						'width' => 1,
						'height' => 1,
						'style' => 'border: none; position: absolute;',
					)
				) . '</noscript>' );
			}
		} else {
			$centralUser = CentralAuthUser::getInstance( $out->getUser() );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$out->addModules( 'ext.centralauth.centralautologin.clearcookie' );
			}

			if ( $out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' ) ) {
				$out->getRequest()->setSessionData( 'CentralAuthDoEdgeLogin', null );
				$out->addHTML( self::getEdgeLoginHTML() );

				if ( $wgCentralAuthUseEventLogging ) {
					// Need to correlate user_id across wikis
					EventLogging::logEvent( 'CentralAuth', 5690875,
						array( 'version' => 1,
							'userId' => $centralUser->getId(),
							'action' => 'sul2-autologin-login'
						)
					);
				}
			}
		}
		return true;
	}

	/**
	 * Build the HTML containing the 1x1 images
	 * @return string
	 */
	static function getEdgeLoginHTML() {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;

		// Put images inside a div so that other code that manipulates page content can
		// explicitly ignore them.
		$html = Xml::openElement( 'div',
			array(
				'id' => 'central-auth-images',
				'style' => 'position: absolute;',
			)
		);

		foreach ( $wgCentralAuthAutoLoginWikis as $wiki ) {
			$wiki = WikiMap::getWiki( $wiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$params = array(
				'type' => '1x1',
				'from' => wfWikiID(),
			);
			$url = wfAppendQuery(
				$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
				$params
			);
			if ( self::isMobileDomain() ) {
				$params['mobile'] = 1;
				// Do autologin on the mobile domain for each wiki
				$url = MobileContext::singleton()->getMobileUrl(
					wfAppendQuery(
						$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
						$params
					)
				);
			}
			$html .= Xml::element( 'img',
				array(
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none;',
				)
			);
		}
		if ( $wgCentralAuthLoginWiki ) {
			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/refreshCookies' ), array(
				'type' => '1x1',
				'wikiid' => wfWikiID(),
				'proto' => RequestContext::getMain()->getRequest()->detectProtocol(),
			) );
			$html .= Xml::element( 'img',
				array(
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none;',
				)
			);
		}

		$html .= Xml::closeElement( 'div' );

		return $html;
	}

	/**
	 * @param $auth
	 * @param $user User
	 * @param $params
	 * @return bool
	 */
	static function onSecurePoll_GetUserParams( $auth, $user, &$params ) {
		if ( $user->isAnon() ) {
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}
		$wikiID = $centralUser->getHomeWiki();
		if ( strval( $wikiID ) === '' ) {
			return true;
		}
		$wiki = WikiMap::getWiki( $wikiID );
		$wikiUrl = $wiki->getUrl( '' );
		$parts = explode( '/', $wikiUrl );
		if ( isset( $parts[2] ) ) {
			$params['properties']['ca-local-domain'] = $params['domain'];
			$params['domain'] = $parts[2];
		}
		$params['properties']['ca-local-url'] = $params['url'];
		$params['url'] = $wiki->getUrl( MWNamespace::getCanonicalName( NS_USER ) . ':' . $user->getTitleKey() );
		return true;
	}

	/**
	 * Creates a link to the global lock log
	 * @param array $msg Message with a link to the global block log
	 * @param string $user The username to be checked
	 * @return boolean true
	 */
	static function getBlockLogLink( &$msg, $user ) {
		if ( IP::isIPAddress( $user ) ) {
			return true; // Return if it is an IP as only usernames can be locked.
		}

		$caUser = new CentralAuthUser( $user );
		if ( $caUser->isLocked() && in_array( wfWikiID(), $caUser->listAttached() ) ) {
			$msg[] = Html::rawElement(
				'span',
				array( 'class' => 'mw-centralauth-lock-loglink plainlinks' ),
				wfMessage( 'centralauth-block-already-locked', $user )->parse()
			);
		}
		return true;
	}

	/**
	 * Computes the global_user_groups variable
	 * @param string $method
	 * @param AbuseFilterVariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
	static function abuseFilterComputeVariable( $method, $vars, $parameters, &$result ) {
		if ( $method == 'global-user-groups' ) {
			$user = CentralAuthUser::getInstance( $parameters['user'] );
			if ( $user->exists() && $user->isAttached() ) {
				$result = $user->getGlobalGroups();
			} else {
				$result = array();
			}
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Load our global_user_groups variable
	 * @param AbuseFilterVariableHolder $vars
	 * @param User $user
	 * @return bool
	 */
	static function abuseFilterGenerateUserVars( $vars, $user ) {
		$vars->setLazyLoadVar( 'global_user_groups', 'global-user-groups', array( 'user' => $user ) );
		return true;
	}

	/**
	 * Tell AbuseFilter about our global_user_groups variable
	 * @param array &$builderValues
	 * @return bool
	 */
	static function abuseFilterBuilder( &$builderValues ) {
		// Uses: 'abusefilter-edit-builder-vars-global-user-groups'
		$builderValues['vars']['global_user_groups'] = 'global-user-groups';
		return true;
	}

	/**
	 * Check whether the user's preferences are such that a UI reload is
	 * recommended.
	 * @param User $user User
	 * @return bool
	 */
	public static function isUIReloadRecommended( User $user ) {
		global $wgCentralAuthPrefsForUIReload;

		foreach ( $wgCentralAuthPrefsForUIReload as $pref ) {
			if ( $user->getOption( $pref ) !== User::getDefaultOption( $pref ) ) {
				return true;
			}
		}

		$recommendReload = false;
		Hooks::run( 'CentralAuthIsUIReloadRecommended', array( $user, &$recommendReload ) );
		return $recommendReload;
	}

	/**
	 * Get the username from CentralAuth for a list of CentralAuth user id's. Sets the name
	 * to false if the userid doesn't exist, or the username is hidden.
	 * @param string $wgMWOAuthCentralWiki
	 * @param array &$namesById array of userIds=>names to associate
	 * @param bool|User $audience show hidden names based on this user, or false for public
	 * @param string $wgMWOAuthSharedUserSource the authoritative extension
	 */
	public static function onOAuthGetUserNamesFromCentralIds( $wgMWOAuthCentralWiki, &$namesById, $audience, $wgMWOAuthSharedUserSource ) {
		if ( $wgMWOAuthSharedUserSource !== 'CentralAuth' ) {
			// We aren't supposed to handle this
			return true;
		}
		$dbr = CentralAuthUser::getCentralSlaveDB();
		foreach ( $namesById as $userid => $name ) {
			$name = $dbr->selectField(
				'globaluser',
				'gu_name',
				array( 'gu_id' => $userid ),
				__METHOD__
			);
			$namesById[$userid] = $name;
			$centralUser = new CentralAuthUser( $name );
			if ( $centralUser->getHiddenLevel() !== CentralAuthUser::HIDDEN_NONE
				&& !( $audience instanceof User
				&& $audience->isAllowed( 'centralauth-oversight' ) )
			) {
				$namesById[$userid] = '';
			}
		}
		return true;
	}

	/**
	 * Check that the local user object is part of a global account, and the account is
	 * attached on this wiki, and the central OAuth wiki, so we know that the same username
	 * on both wikis references the same user. Set the user object to false if they are not.
	 * @param int $userId the central OAuth wiki user_id for this username
	 * @param string $wgMWOAuthCentralWiki
	 * @param User &$user the loca user object
	 * @param string $wgMWOAuthSharedUserSource the authoritative extension
	 */
	public static function onOAuthGetLocalUserFromCentralId( $userId, $wgMWOAuthCentralWiki, &$user, $wgMWOAuthSharedUserSource ) {
		if ( $wgMWOAuthSharedUserSource !== 'CentralAuth' ) {
			// We aren't supposed to handle this
			return true;
		}
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$user_name = $dbr->selectField(
			'globaluser',
			'gu_name',
			array( 'gu_id' => $userId ),
			__METHOD__
		);

		if ( $user_name === false ) {
			wfDebugLog( 'CentralAuth', __METHOD__ . ": invalid userId ($userId) passed to CentralAuth by OAuth" );
			$user = false;
			return false;
		}

		$centralUser = new CentralAuthUser( $user_name );

		if ( $centralUser->isLocked()
			|| !$centralUser->isAttached()
			|| !$centralUser->attachedOn( $wgMWOAuthCentralWiki )
		) {
			wfDebugLog( 'CentralAuth', __METHOD__ . ": user '{$user_name}' cannot use OAuth on " . wfWikiID() );
			$user = false;
			return false;
		}

		$user = User::newFromName( $user_name );
		// One last sanity check
		if ( $user->getId() == 0 ) {
			throw new Exception( "Attached user couldn't be loaded from name" );
		}
		return true;
	}

	/**
	 * Set the user_id to false if the user is not a global user, or if the user is not
	 * attached on both the local wiki, and the central OAuth wiki, where user grants
	 * are tracked. This prevents OAuth from assuming the identity of a user on the local
	 * wiki is the same as the user on the central wiki, even if they have the same username.
	 * @param User $user the local user object
	 * @param string $wgMWOAuthCentralWiki
	 * @param int &$id the user_id of the matching name on the central wiki
	 * @param string $wgMWOAuthSharedUserSource the authoritative extension
	 */
	public static function onOAuthGetCentralIdFromLocalUser( $user, $wgMWOAuthCentralWiki, &$id, $wgMWOAuthSharedUserSource ) {
		if ( $wgMWOAuthSharedUserSource !== 'CentralAuth' ) {
			// We aren't supposed to handle this
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->getId() == 0
			|| !$centralUser->isAttached()
			|| !$centralUser->attachedOn( $wgMWOAuthCentralWiki )
		) {
			wfDebugLog( 'CentralAuth', __METHOD__ . ": user '{$user->getName()}' cannot use OAuth on " . wfWikiID() );
			$id = false;
			return false;
		}

		$id = $centralUser->getId();
		return true;
	}

	/**
	 * Get the id for the CentralAuth username. This hook should not be used
	 * to determine attachment, so as long as the CentralAuth account exists,
	 * we return the id.
	 * @param string $username the username
	 * @param string $wgMWOAuthCentralWiki
	 * @param int &$id the user_id of the matching name on the central wiki
	 * @param string $wgMWOAuthSharedUserSource the authoritative extension
	 */
	public static function onOAuthGetCentralIdFromUserName( $username, $wgMWOAuthCentralWiki, &$id, $wgMWOAuthSharedUserSource ) {
		if ( $wgMWOAuthSharedUserSource !== 'CentralAuth' ) {
			// We aren't supposed to handle this
			return true;
		}
		$centralUser = new CentralAuthUser( $username );
		if ( $centralUser->getId() == 0 ) {
			$id = false;
			return false;
		}

		$id = $centralUser->getId();
		return true;
	}

	/**
	 * Prevent "canonicalization" of Special:CentralAutoLogin to a localized
	 * Special namespace name. See bug 54195.
	 * @param WebRequest $request
	 * @param Title $title
	 * @param OutputPage $output
	 * @return boolean
	 */
	public static function onTestCanonicalRedirect( $request, $title, $output ) {
		return $title->getNamespace() !== NS_SPECIAL ||
			strncmp( $request->getVal( 'title', '' ), 'Special:CentralAutoLogin/', 25 ) !== 0;
	}

	/**
	 * @param User $user
	 * @param string $global wikiID of global wiki
	 * @param string $local wikiID of wiki user is currently on
	 * @return bool
	 * @see https://www.mediawiki.org/wiki/Extension:GlobalCssJs/LoadGlobalCssJs
	 */
	public static function onLoadGlobalCssJs( User $user, $global, $local ) {
		return self::isUserTheSameOn( $user, $global, $local );
	}

	/**
	 * @param User $user
	 * @param string $global wikiID of global wiki
	 * @param string $local wikiID of wiki user is currently on
	 * @return bool
	 * @see https://www.mediawiki.org/wiki/Extension:GlobalUserPage/LoadGlobalUserPage
	 */
	public static function onLoadGlobalUserPage( User $user, $global, $local ) {
		return self::isUserTheSameOn( $user, $global, $local );
	}

	/**
	 * Helper for LoadGlobalCssJs and LoadGlobalUserPage
	 * hooks.
	 *
	 * @param User $user
	 * @param string $global
	 * @param string $local
	 * @return bool
	 */
	private static function isUserTheSameOn( User $user, $global, $local ) {
		$caUser = CentralAuthUser::getInstance( $user );

		return $caUser->attachedOn( $global ) && $caUser->attachedOn( $local );
	}

	/**
	 * Hook for UserMerge extension after an account is deleted
	 * @param User &$user account that was just deleted
	 * @return bool
	 */
	public static function onDeleteAccount( User &$user ) {
		$caUser = CentralAuthUser::getInstance( $user );

		if ( $caUser->isAttached() ) {
			// Clean up localuser table.
			$caUser->adminUnattach( array( wfWikiID() ) );
		}

		// Clean up localnames table.
		$caUser->removeLocalName( wfWikiID() );

		return true;
	}

	/*
	 * Handler for UserGetReservedNames
	 * @param array $reservedUsernames
	 * @return bool
	 */
	public static function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = 'Global rename script';
	}

	/**
	 * Add phpunit tests
	 */
	public static function onUnitTestsList( array &$files ) {
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/../tests/phpunit/' );
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$files[] = $fileInfo->getPathname();
			}
		}
		return true;
	}

	public static function onApiQueryTokensRegisterTypes( &$salts ) {
		$salts += array(
			'setglobalaccountstatus' => 'setglobalaccountstatus',
			'deleteglobalaccount' => 'deleteglobalaccount',
		);
		return true;
	}

	/**
	 * Handler for PostLoginRedirect
	 * @param string $returnTo The page to return to
	 * @param array $returnToQuery Url parameters
	 * @param string $type Type of login redirect
	 */
	public static function onPostLoginRedirect(
		&$returnTo, &$returnToQuery, &$type
	) {
		global $wgCentralAuthCheckSULMigration, $wgUser;
		if ( $wgCentralAuthCheckSULMigration &&
			$wgUser->getRequest()->getSessionData( 'CentralAuthForcedRename' ) === true &&
			( $type == 'success' || $type == 'successredirect' )
		) {
			wfDebugLog( 'SUL', 'Redirecting user to Special:SulRenameWarning' );
			// Store current redirect target in session so we can provide a link
			// later.
			$wgUser->getRequest()->setSessionData( 'SulRenameWarning', array(
				'returnTo' => $returnTo,
				'returnToQuery' => $returnToQuery,
			) );
			$returnTo = SpecialPageFactory::getLocalNameFor( 'Special:SulRenameWarning' );
			$returnToQuery = array();
			return false;
		}
		return true;
	}

	/**
	 * @param $type
	 * @param WebRequest $request
	 * @param array $qc
	 * @return bool
	 */
	public static function onSpecialLogAddLogSearchRelations( $type, WebRequest $request, array &$qc ) {
		if ( $type === 'gblrename' ) {
			$oldname = trim( $request->getText( 'oldname' ) );
			$canonicalOldname = User::getCanonicalName( $oldname );
			if ( $oldname !== '' ) {
				$qc = array( 'ls_field' => 'oldname', 'ls_value' => $canonicalOldname );
			}
		}

		return true;
	}

	public static function onLogEventsListGetExtraInputs( $type, LogEventsList $list, &$input ) {
		if ( $type === 'gblrename' ) {
			$value = $list->getRequest()->getVal( 'oldname' );
			if ( $value !== null ) {
				$name = User::getCanonicalName( $value );
				$value = $name !== false ? $name : '';
			}
			$input = Xml::inputLabel(
				$list->msg( 'centralauth-log-gblrename-oldname' )->text(),
				'oldname',
				'mw-log-gblrename-oldname',
				20,
				$value
			);
		}
	}

	public static function onResourceLoaderForeignApiModules( array &$dependencies, ResourceLoaderContext $context = null ) {
		$dependencies[] = 'ext.centralauth.ForeignApi';
		return true;
	}

	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['ext.centralauth.ForeignApi.test'] = array(
			'scripts' => array( 'tests/qunit/ext.centralauth.ForeignApi.test.js' ),
			'dependencies' => array( 'ext.centralauth.ForeignApi' ),
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'CentralAuth',
		);
		return true;
	}

	/**
	 * Apply global password policies when calculating the effective policy for
	 * a user.
	 * @param User $user
	 * @param array $effectivePolicy
	 */
	public static function onPasswordPoliciesForUser( User $user, array &$effectivePolicy ) {
		global $wgCentralAuthGlobalPasswordPolicies;
		$central = CentralAuthUser::getInstance( $user );

		if ( $central->exists() ) {
			try {
				$localPolicyGroups = array_intersect(
					array_keys( $wgCentralAuthGlobalPasswordPolicies ),
					$central->getLocalGroups()
				);
			} catch ( Exception $e ) {
				// T104615 - race condition in attaching user and creating local
				// wiki account can cause this Exception from
				// CentralAuthUser::localUserData. Allow the password for now, and
				// we'll catch them next login if their password isn't valid.
				if ( $user->idForName() === 0
					&& substr( $e->getMessage(), 0 , 34 ) === 'Could not find local user data for'
				) {
					wfDebugLog(
						'CentralAuth',
						sprintf( 'Bug T104615 hit for %s@%s',
							$user->getName(),
							wfWikiId()
						)
					);
					return true;
				}

				throw $e;
			}

			$effectivePolicy = UserPasswordPolicy::getPoliciesForGroups(
				$wgCentralAuthGlobalPasswordPolicies,
				array_merge( $central->getGlobalGroups(), $localPolicyGroups ),
				$effectivePolicy
			);
		}
		return true;
	}

	/**
	 * Hook function to prevent logged-in sessions when a user is being
	 * renamed.
	 * @param string &$reason Failure reason to log
	 * @param MediaWiki\\Session\\SessionInfo $info
	 * @param WebRequest $request
	 * @param array|false $metadata
	 * @param array|false $data
	 * @return bool
	 */
	public static function onSessionCheckInfo( &$reason, $info ) {
		$centralUser = new CentralAuthUser( $info->getUser()->getName() );
		if ( $centralUser->renameInProgress() ) {
			$reason = 'CentralAuth rename in progress';
			return false;
		}
		return true;
	}
}
