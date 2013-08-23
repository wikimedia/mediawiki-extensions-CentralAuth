<?php

class CentralAuthHooks {

	/**
	 * Check whether we're in API mode and the "centralauthtoken" parameter was
	 * sent.
	 *
	 * Modern browsers disable third-party cookies in various cases, which
	 * prevents the standard MediaWiki mechanism for CentralAuth logins from
	 * working. And, for that matter, for storing of session data such as the
	 * edit token.
	 *
	 * The solution is to pass the CentralAuth data and the session token via
	 * memcached keyed by a "centralauthtoken" parameter in the request. And
	 * for good measure, we will also ignore any standard cookies that might be
	 * set on the request, replacing them with the data we got from memcached.
	 *
	 * This function checks whether "centralauthtoken" was validly supplied, so
	 * the code below doing all of the above can know when to activate.
	 *
	 * @return bool
	 */
	// Only public because it needs to be called from CentralAuthUser
	public static function hasApiToken() {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return false;
		}

		if ( defined( 'MW_API' ) ) {
			global $wgRequest;
			if ( strlen( $wgRequest->getVal( 'centralauthtoken' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the CentralAuthUser for the API token.
	 *
	 * Authenticates based on the data in memcached keyed by the
	 * 'centralauthtoken' parameter.
	 *
	 * @see CentralAuthHooks::hasApiToken()
	 * @param bool $invalidateToken If true, invalidate the passed token
	 * @return CentralAuthUser|null
	 */
	private static function getApiCentralUser( $invalidateToken ) {
		if ( !self::hasApiToken() ) {
			return null;
		}

		global $wgRequest, $wgMemc;
		static $cachedUser = false;

		if ( $cachedUser === false ) {
			$loginToken = $wgRequest->getVal( 'centralauthtoken' );
			$key = CentralAuthUser::memcKey( 'api-token', $loginToken );
			$cachedUser = null;

			$data = $wgMemc->get( $key );
			if ( $invalidateToken ) {
				$wgMemc->delete( $key );
			}
			if ( !is_array( $data ) ) {
				return null;
			}
			$userName = $data['userName'];
			$token = $data['token'];

			// Clean up username
			$userName = User::getCanonicalName( $userName, 'valid' );
			if ( !$userName ) {
				wfDebug( __METHOD__ . ": invalid username\n" );
				return null;
			}

			// Try the central user
			$centralUser = new CentralAuthUser( $userName );
			if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
				wfDebug( __METHOD__ . ": token mismatch\n" );
				return null;
			}
			if ( !$centralUser->exists() ) {
				return null;
			}
			if ( !$centralUser->isAttached() && User::idFromName( $userName ) ) {
				// User exists locally and is not attached. Fail!
				return null;
			}
			$cachedUser = $centralUser;
		} elseif ( $invalidateToken ) {
			$loginToken = $wgRequest->getVal( 'centralauthtoken' );
			$key = CentralAuthUser::memcKey( 'api-token', $loginToken );
			$wgMemc->delete( $key );
		}

		return $cachedUser;
	}

	/**
	 * @return bool
	 */
	static function onSetupAfterCache() {
		if ( self::hasApiToken() ) {
			// If the API 'centralauthtoken' parameter is set, we don't want to
			// be setting cookies. So disable setting of the session cookie
			// and clear $_COOKIE.
			// We can't try to load the saved session cookies from memcached
			// yet, because getting the central user requires that $wgContLang
			// is set up.
			// @see CentralAuthHooks::hasApiToken()
			ini_set( 'session.use_cookies', 0 );
			$_COOKIE = array();
		}
		return true;
	}

	/**
	 * @param $auth
	 * @return bool
	 */
	static function onAuthPluginSetup( &$auth ) {
		$auth = new StubObject( 'wgAuth', 'CentralAuthPlugin' );

		// If the API 'centralauthtoken' parameter is set, we don't use the
		// browser-supplied cookies. Now that $wgContLang is set up, we can
		// replace them with the cookies passed via memcached instead.
		// @see CentralAuthHooks::hasApiToken()
		$centralUser = self::getApiCentralUser( false );
		if ( $centralUser ) {
			global $wgMemc;
			$key = CentralAuthUser::memcKey( 'api-cookies', md5( $centralUser->getName() ), wfWikiID() );
			$cookies = $wgMemc->get( $key );
			if ( !is_array( $cookies ) ) {
				$cookies = array();
			}
			if ( !isset( $cookies[session_name()] ) ) {
				$cookies[session_name()] = MWCryptRand::generateHex( 32 );
			}
			global $wgCentralAuthCookiePrefix;
			if ( !isset( $cookies[$wgCentralAuthCookiePrefix . 'Session'] ) ) {
				$cookies[$wgCentralAuthCookiePrefix . 'Session'] = MWCryptRand::generateHex( 32 );
			}
			$wgMemc->set( $key, $cookies, 86400 );
			$_COOKIE = $cookies;
			wfSetupSession( $cookies[session_name()] );
		}

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

		if ( !$user->isAllowed( 'centralauth-merge' ) ) {
			// Not allowed to merge, don't display merge information
			return true;
		}

		// Possible states:
		// - account not merged at all
		// - global accounts exists, but this local account is unattached
		// - this local account is attached, but migration incomplete
		// - all local accounts are attached

		$global = CentralAuthUser::getInstance( $user );
		if ( $global->exists() ) {
			if ( $global->isAttached() ) {
				// Local is attached...
				$attached = count( $global->listAttached() );
				$unattached = count( $global->listUnattached() );
				if ( $unattached ) {
					// Migration incomplete
					$message = '<strong>' . wfMessage( 'centralauth-prefs-migration' )->parse() . '</strong>' .
						'<br />' .
						wfMessage( 'centralauth-prefs-count-attached' )->numParams( $attached )->parse() .
						'<br />' .
						wfMessage( 'centralauth-prefs-count-unattached' )->numParams( $unattached )->parse();
				} else {
					// Migration complete
					$message = '<strong>' . wfMessage( 'centralauth-prefs-complete' )->parse() . '</strong>' .
						'<br />' .
						wfMessage( 'centralauth-prefs-count-attached' )->numParams( $attached )->parse();
				}
			} else {
				// Account is in migration, but the local account is not attached
				$message = '<strong>' . wfMessage( 'centralauth-prefs-unattached' )->parse() . '</strong>' .
					'<br />' .
					wfMessage( 'centralauth-prefs-detail-unattached' )->parse();
			}
		} else {
			// Not migrated.
			$message = wfMessage( 'centralauth-prefs-not-managed' )->parse();
		}

		$manageLinks = array();
		$manageLinks[] = Linker::linkKnown( SpecialPage::getTitleFor( 'MergeAccount' ),
			wfMessage( 'centralauth-prefs-manage' )->parse() );
		$manageLinks[] = Linker::linkKnown( SpecialPage::getTitleFor( 'CentralAuth', $user->getName() ),
			wfMessage( 'centralauth-prefs-view' )->parse() );
		$manageLinkList = wfMessage( 'parentheses', $wgLang->pipeList( $manageLinks ) )->text();

		$prefInsert =
			array( 'globalaccountstatus' =>
				array(
					'section' => 'personal/info',
					'label-message' => 'centralauth-prefs-status',
					'type' => 'info',
					'raw' => true,
					'default' => "$message<br />$manageLinkList"
				),
			);

		$after = array_key_exists( 'registrationdate', $preferences ) ? 'registrationdate' : 'editcount';
		$preferences = wfArrayInsertAfter( $preferences, $prefInsert, $after );

		return true;
	}

	/**
	 * @param $user User
	 * @param $abortError
	 * @return bool
	 */
	static function onAbortNewAccount( $user, &$abortError ) {
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists() ) {
			$abortError = wfMessage( 'centralauth-account-exists' )->text();
			return false;
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
		global $wgCentralAuthSilentLogin;

		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			$centralUser->deleteGlobalCookies();
			return true;
		}

		if ( $wgCentralAuthSilentLogin ) {
			// Redirect to the central wiki and back to complete login, if necessary
			self::doCentralLoginRedirect( $user, $centralUser, $inject_html );
		} else {
			if ( $wgCentralAuthLoginWiki ) {
				// Set $inject_html to some text to bypass the LoginForm redirection
				$inject_html .= wfMessage( 'centralauth-login-no-others' )->text();
				// Redirect to the central wiki and back to complete login
				$dummy = '';
				self::doCentralLoginRedirect( $user, $centralUser, $dummy );
			} else {
				// Show HTML to create cross-domain cookies
				$inject_html .= self::getDomainAutoLoginHtml( $user, $centralUser );
			}
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @return String
	 */
	public static function getDomainAutoLoginHtml( User $user, CentralAuthUser $centralUser ) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis, $wgMemc;

		// No other domains
		if ( !$wgCentralAuthAutoLoginWikis ) {
			$inject_html = wfMessage( 'centralauth-login-no-others' )->text();
		} else {
			$inject_html = '<div class="centralauth-login-box"><p>' .
				wfMessage( 'centralauth-login-progress', $user->getName() )->text() . "</p>\n<p>";
			foreach ( $wgCentralAuthAutoLoginWikis as $alt => $wikiID ) {
				$wiki = WikiMap::getWiki( $wikiID );

				global $wgCentralAuthUseOldAutoLogin;
				if ( $wgCentralAuthUseOldAutoLogin ) {
					// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
					$data = array(
						'userName' => $user->getName(),
						'token' => $centralUser->getAuthToken(),
						'remember' => $user->getOption( 'rememberpassword' ),
						'wiki' => $wikiID
					);

					$loginToken = MWCryptRand::generateHex( 32 );
					$wgMemc->set( CentralAuthUser::memcKey( 'login-token', $loginToken ), $data, 600 );
					$url = wfAppendQuery( $wiki->getFullUrl( 'Special:AutoLogin' ), "token=$loginToken" );
				} else {
					// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
					$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/start' ), array(
						'type' => 'icon',
						'from' => wfWikiID(),
					) );
				}
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
		global $wgCentralAuthLoginWiki, $wgMemc, $wgSecureLogin;

		$context = RequestContext::getMain();
		$request = $context->getRequest();

		if ( !$wgCentralAuthLoginWiki || defined( 'MW_API' ) ) {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
			return true;
		}

		// Check that this is actually for a special login page view
		if ( $context->getTitle()->isSpecial( 'Userlogin' ) ) {
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
			if ( $wgSecureLogin ) {
				$finalProto = $user->getBoolOption( 'prefershttps' ) ? 'https' : 'http';
			}

			// When POSTs triggered from Special:CentralLogin/start are sent back to
			// this wiki, the token will be checked to see if it was signed with this.
			// This is needed as Special:CentralLogin/start only takes a token argument
			// and we need to make sure an agent requesting such a URL actually initiated
			// the login request that spawned that token server-side.
			$secret = MWCryptRand::generateHex( 32 );
			$_SESSION['CentralAuth:autologin:current-attempt'] = array(
				'secret'        => $secret,
				'remember'      => $request->getCheck( 'wpRemember' ),
				'returnTo'      => $returnTo,
				'returnToQuery' => $returnToQuery,
				'stickHTTPS'    => $user->getBoolOption( 'prefershttps' ),
				'finalProto'    => $finalProto,
				'type'          => $request->getText( 'type' )
			);

			// Create a new token to pass to Special:CentralLogin/start (central wiki)
			$token = MWCryptRand::generateHex( 32 );
			$key = CentralAuthUser::memcKey( 'central-login-start-token', $token );
			$data = array(
				'secret'        => $secret,
				'name'          => $centralUser->getName(),
				'guid'          => $centralUser->getId(),
				'wikiId'        => wfWikiId(),
				'finalProto'    => $finalProto,
				'currentProto'  => $request->detectProtocol()
			);
			wfRunHooks( 'CentralAuthLoginRedirectData', array( $centralUser, &$data ) );
			$wgMemc->set( $key, $data, 60 );

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

	/**
	 * @param $user User
	 * @param $result
	 * @return bool
	 */
	static function onUserLoadFromSession( $user, &$result ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;
		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		if ( $user->isLoggedIn() ) {
			// Already logged in; don't worry about the global session.
			return true;
		}

		if ( self::hasApiToken() ) {
			$centralUser = self::getApiCentralUser( false );
			if ( !$centralUser ) {
				return true;
			}
			$userName = $centralUser->getName();
			$user->setName( $userName );
			$token = $centralUser->getAuthToken();
		} else {
			$prefix = $wgCentralAuthCookiePrefix;

			if ( isset( $_COOKIE["{$prefix}User"] ) && isset( $_COOKIE["{$prefix}Token"] ) ) {
				$userName = $_COOKIE["{$prefix}User"];
				$token = $_COOKIE["{$prefix}Token"];
			} elseif ( (bool)( $session = CentralAuthUser::getSession() ) ) {
				if ( isset( $session['pending_name'] ) || isset( $session['pending_guid'] ) ) {
					wfDebug( __METHOD__ . ": unintialized session\n" );
					return true;
				} else {
					$token = $session['token'];
					$userName = $session['user'];
				}
			} else {
				wfDebug( __METHOD__ . ": no token or session\n" );
				return true;
			}

			// Sanity check to avoid session ID collisions, as reported on bug 19158
			if ( !isset( $_COOKIE["{$prefix}User"] ) ) {
				wfDebug( __METHOD__ . ": no User cookie, so unable to check for session mismatch\n" );
				return true;
			} elseif ( $_COOKIE["{$prefix}User"] != $userName ) {
				wfDebug( __METHOD__ . ": Session ID/User mismatch. Possible session collision. " .
					"Expected: $userName; actual: " .
					$_COOKIE["{$prefix}User"] . "\n" );
				return true;
			}

			// Clean up username
			$userName = User::getCanonicalName( $userName, 'valid' );
			if ( !$userName ) {
				wfDebug( __METHOD__ . ": invalid username\n" );
				return true;
			}
			$user->setName( $userName );

			// Try the central user
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( !$centralUser->exists() ) {
				wfDebug( __METHOD__ . ": global account doesn't exist\n" );
				return true;
			}
			if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
				wfDebug( __METHOD__ . ": token mismatch\n" );
				return true;
			}
		}

		// Try the local user from the slave DB
		$localId = User::idFromName( $userName );

		// Fetch the user ID from the master, so that we don't try to create the user
		// when they already exist, due to replication lag
		if ( !$localId && wfGetLB()->getReaderIndex() != 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$localId = $dbw->selectField( 'user', 'user_id',
				array( 'user_name' => $userName ), __METHOD__ );
		}

		if ( !$centralUser->isAttached() && $localId ) {
			wfDebug( __METHOD__ . ": exists, and not attached\n" );
			return true;
		}

		if ( !$localId ) {
			// User does not exist locally, attempt to create it
			if ( !self::attemptAddUser( $user ) ) {
				// Can't create user, give up now
				return true;
			}
		} else {
			$user->setID( $localId );
			$user->loadFromId();
		}

		// Auth OK.
		wfDebug( __METHOD__ . ": logged in from session\n" );
		self::initSession( $user, $token );
		$user->centralAuthObj = $centralUser;
		$result = true;

		return true;
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
			$centralUser->deleteGlobalCookies();
			$centralUser->resetAuthToken();
		}

		return true;
	}

	/**
	 * @param $out OutputPage
	 * @param $cookies array
	 * @return bool
	 */
	static function onGetCacheVaryCookies( $out, &$cookies ) {
		global $wgCentralAuthCookiePrefix;
		$cookies[] = $wgCentralAuthCookiePrefix . 'Token';
		$cookies[] = $wgCentralAuthCookiePrefix . 'Session';
		$cookies[] = $wgCentralAuthCookiePrefix . 'LoggedOut';
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
		$newCentral = new CentralAuthUser( $newName );
		if ( $newCentral->exists() && !$newCentral->isAttached() ) {
			$warnings[] = array( 'centralauth-renameuser-reserved', $oldName, $newName );
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
		$oldCentral->removeLocalName( wfWikiID() );

		$newCentral = new CentralAuthUser( $newName );
		$newCentral->addLocalName( wfWikiID() );

		return true;
	}

	/**
	 * Helper function for onUserLoadFromSession
	 * @param $user User
	 * @param $token
	 */
	static function initSession( $user, $token ) {
		$userName = $user->getName();
		wfSetupSession();
		if ( $token != @$_SESSION['globalloggedin'] ) { // FIXME: Usage of @
			$_SESSION['globalloggedin'] = $token;
			if ( !wfReadOnly() ) {
				$user->invalidateCache();
			}
			wfDebug( __METHOD__ . ": Initialising session for $userName with token $token.\n" );
		} else {
			wfDebug( __METHOD__ . ": Session already initialised for $userName with token $token.\n" );
		}
	}

	/**
	 * Attempt to add a user to the database
	 * Does the required authentication checks and updates for auto-creation
	 * @param $user User
	 * @throws MWException
	 * @return bool Success
	 */
	static function attemptAddUser( $user ) {
		global $wgAuth, $wgCentralAuthCreateOnView;

		$userName = $user->getName();
		// Denied by configuration?
		if ( !$wgAuth->autoCreate() ) {
			wfDebug( __METHOD__ . ": denied by configuration\n" );
			return false;
		}

		if ( !$wgCentralAuthCreateOnView ) {
			// Only create local accounts when we perform an active login...
			// Don't freak people out on every page view
			wfDebug( __METHOD__ . ": denied by \$wgCentralAuthCreateOnView\n" );
			return false;
		}

		// Is the user blacklisted by the session?
		// This is just a cache to avoid expensive DB queries in $user->isAllowedToCreateAccount().
		// The user can log in via Special:UserLogin to bypass the blacklist and get a proper
		// error message.
		$session = CentralAuthUser::getSession();
		if ( isset( $session['auto-create-blacklist'] )
			&& in_array( wfWikiID(), (array)$session['auto-create-blacklist'] ) )
		{
			wfDebug( __METHOD__ . ": blacklisted by session\n" );
			return false;
		}

		// Is the user blocked?
		$anon = new User;
		if ( !$anon->isAllowedAny( 'createaccount', 'centralauth-autoaccount' )
			|| $anon->isBlockedFromCreateAccount() )
		{
			// Blacklist the user to avoid repeated DB queries subsequently
			// First load the session again in case it changed while the above DB query was in progress
			wfDebug( __METHOD__ . ": user is blocked from this wiki, blacklisting\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthUser::setSession( $session );
			return false;
		}

		// Check for validity of username
		if ( !User::isCreatableName( $userName ) ) {
			wfDebug( __METHOD__ . ": Invalid username\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthUser::setSession( $session );
			return false;
		}

		// Give other extensions a chance to stop auto creation, but they cannot
		// change $userName, because CentralAuth expects user names on all wikis
		// are the same.
		//
		// * $user (and usually $wgUser) is the half-created User object and
		//   should not be accessed in any way since calling any User methods
		//   in its half-initialised state will give incorrect results.
		//
		// * $userName is the new user name
		//
		// * $anon is an anonymous user object which can be safely used for
		//   permissions checks.
		//
		// NOTE! This hook is deprecated, please use AbortAutoAccount.
		//
		if ( !wfRunHooks( 'CentralAuthAutoCreate', array( $user, $userName, $anon ) ) ) {
			wfDebug( __METHOD__ . ": denied by other extensions\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthUser::setSession( $session );
			return false;
		}

		// Give other extensions a chance to stop auto creation.
		$user->loadDefaults( $userName );
		$abortMessage = '';
		if ( !wfRunHooks( 'AbortAutoAccount', array( $user, &$abortMessage ) ) ) {
			// In this case we have no way to return the message to the user,
			// but we can log it.
			wfDebug( __METHOD__ . ": denied by other extension: $abortMessage\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthUser::setSession( $session );
			return false;
		}
		// Make sure the name has not been changed
		if ( $user->getName() !== $userName ) {
			throw new MWException( "AbortAutoAccount hook tried to change the user name" );
		}

		// Checks passed, create the user
		wfDebug( __METHOD__ . ": creating new user\n" );
		$status = $user->addToDatabase();
		if ( $status === null ) {
			// MW before 1.21 -- ok, continue
		} elseif ( !$status->isOK() ) {
			wfDebug( __METHOD__.": failed with message " . $status->getWikiText() . "\n" );
			return false;
		}

		$wgAuth->initUser( $user, true );

		# Notify hooks (e.g. Newuserlog)
		wfRunHooks( 'AuthPluginAutoCreate', array( $user ) );

		# Update user count
		DeferredUpdates::addUpdate( new SiteStatsUpdate( 0, 0, 0, 0, 1 ) );

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
			$timestamp = $ca->getEmailAuthenticationTimestamp();
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
	 * @param $groups
	 * @return bool
	 */
	static function onMakeGlobalVariablesScript( $groups ) {
		global $wgUser;
		if ( !$wgUser->isAnon() ) {
			$centralUser = CentralAuthUser::getInstance( $wgUser );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$groups['wgGlobalGroups'] = $centralUser->getGlobalGroups();
			}
		}
		return true;
	}

	/**
	 * Destroy local login cookies so that remote logout works
	 * @param $user User
	 * @param $session
	 * @param $cookies
	 * @throws MWException
	 * @return bool
	 */
	static function onUserSetCookies( $user, &$session, &$cookies ) {
		if ( self::hasApiToken() ) {
			throw new MWException( "Cannot set cookies when API 'centralauthtoken' parameter is given" );
		}

		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies || $user->isAnon() ) {
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->isAttached() ) {
			return true;
		}

		unset( $session['wsToken'] );
		if ( !empty( $cookies['Token'] ) ) {
			unset( $cookies['Token'] );
			$remember = true;
		} else {
			$remember = false;
		}
		// Regenerate SessionID when setting central cookie (bug 40962)
		$centralUser->setGlobalCookies( $remember, true );
		return true;
	}

	/**
	 * Use the central LoggedOut cookie just like the local one
	 * @param $user User
	 * @param $name
	 * @return bool
	 */
	static function onUserLoadDefaults( $user, $name ) {
		global $wgCentralAuthCookiePrefix;
		if ( isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'LoggedOut'] ) ) {
			$user->mTouched = wfTimestamp( TS_MW, $_COOKIE[$wgCentralAuthCookiePrefix . 'LoggedOut'] );
		}
		return true;
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
			&& $action === 'edit'
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
	 */
	static function onBeforePageDisplay( &$out, &$skin ) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthUseEventLogging;
		if ( $out->getUser()->isAnon() ) {
			if ( $wgCentralAuthLoginWiki && wfWikiID() !== $wgCentralAuthLoginWiki ) {
				$out->addModules( 'ext.centralauth.centralautologin' );

				// For non-JS clients
				$url = Title::newFromText( 'Special:CentralAutoLogin/start' )->getFullURL( array(
					'type' => '1x1',
				) );
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
			if ( $out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' ) ) {
				$out->getRequest()->setSessionData( 'CentralAuthDoEdgeLogin', null );
				global $wgCentralAuthSilentLogin;
				if ( $wgCentralAuthSilentLogin ) {
					global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;
					foreach ( $wgCentralAuthAutoLoginWikis as $wiki ) {
						$wiki = WikiMap::getWiki( $wiki );
						// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
						$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/start' ), array(
							'type' => '1x1',
							'from' => wfWikiID(),
						) );
						$out->addHTML( Xml::element( 'img',
							array(
								'src' => $url,
								'alt' => '',
								'title' => '',
								'width' => 1,
								'height' => 1,
								'style' => 'border: none; position: absolute;',
							)
						) );
					}
					if ( $wgCentralAuthLoginWiki ) {
						$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
						// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
						$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/refreshCookies' ), array(
							'type' => '1x1',
							'wikiid' => wfWikiID(),
							'proto' => RequestContext::getMain()->getRequest()->detectProtocol(),
						) );
						$out->addHTML( Xml::element( 'img',
							array(
								'src' => $url,
								'alt' => '',
								'title' => '',
								'width' => 1,
								'height' => 1,
								'style' => 'border: none; position: absolute;',
							)
						) );
					}

					if ( $wgCentralAuthUseEventLogging ) {
						// Need to correlate user_id across wikis
						$centralUser = CentralAuthUser::getInstance( $out->getUser() );
						efLogServerSideEvent( 'CentralAuth', 5690875,
							array( 'version' => 1,
								'userId' => $centralUser->getId(),
								'action' => 'sul2-autologin-login'
							)
						);
					}
				}
			}
		}
		return true;
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
	 * Tell the API's action=tokens about the centralauth token
	 * @param array &$types
	 * @return bool
	 */
	static function onApiTokensGetTokenTypes( &$types ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		// Allow other extensions (like OAuth) to temporarily prevent CentralAuth tokens.
		// This is meant to be a temporary hack, until we establish a more unified Authz
		// stack in core.
		if ( !wfRunHooks( 'CentralAuthAbortCentralAuthToken' ) ) {
			return true;
		}

		$types['centralauth'] = array( 'CentralAuthHooks', 'getApiCentralAuthToken' );
		return true;
	}

	/**
	 * Create an API centralauth token
	 * @return string|bool Token
	 */
	static function getApiCentralAuthToken() {
		global $wgUser;
		if ( !$wgUser->isAnon() && !self::hasApiToken() ) {
			$centralUser = CentralAuthUser::getInstance( $wgUser );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$data = array(
					'userName' => $wgUser->getName(),
					'token' => $centralUser->getAuthToken(),
				);
				global $wgMemc;
				$loginToken = MWCryptRand::generateHex( 32 ) . dechex( $centralUser->getId() );
				$key = CentralAuthUser::memcKey( 'api-token', $loginToken );
				$wgMemc->add( $key, $data, 60 );
				return $loginToken;
			}
		}
		return false;
	}

	/**
	 * Inject the "centralauthtoken" parameter into the API
	 * @param ApiBase &$module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags Flags (omitted before 1.21)
	 * @return bool
	 */
	static function onAPIGetAllowedParams( &$module, &$params, $flags = 1 ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( $module instanceof ApiMain && $flags ) {
			$params['centralauthtoken'] = array(
				ApiBase::PARAM_TYPE => 'string',
			);
		}
		return true;
	}

	/**
	 * Inject the "centralauthtoken" parameter description into the API
	 * @param ApiBase &$module API module
	 * @param array &$desc Array of parameter descriptions
	 * @return bool
	 */
	static function onAPIGetParamDescription( &$module, &$desc ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( $module instanceof ApiMain ) {
			$desc['centralauthtoken'] = array(
				'When accessing the API using a cross-domain AJAX request (CORS), use this to authenticate as the current SUL user.',
				'Use action=tokens&type=centralauth on this wiki to retrieve the token, before making the CORS request. Each token may only be used once, and expires after 10 seconds.',
				'This should be included in any pre-flight request, and therefore should be included in the request URI (not the POST body).',
			);
		}
		return true;
	}

	/**
	 * Validate "centralauthtoken", and disable certain modules that make no
	 * sense with "centralauthtoken".
	 * @param ApiBase $module API module
	 * @param User $user User
	 * @param array &$message Error message key and params
	 * @return bool
	 */
	static function onApiCheckCanExecute( $module, $user, &$message ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( self::hasApiToken() ) {
			$module->getMain()->getVal( 'centralauthtoken' ); # Mark used
			$apiCentralUser = self::getApiCentralUser( true );
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( !$apiCentralUser || !$centralUser ||
				$apiCentralUser->getId() !== $centralUser->getId()
			) {
				// Bad design, API.
				ApiBase::$messageMap['centralauth-api-badtoken'] = array(
					'code' => 'badtoken',
					'info' => 'The centralauthtoken is not valid',
				);
				$message = array( 'centralauth-api-badtoken' );
				return false;
			}

			if ( $module instanceof ApiLogin || $module instanceof ApiLogout ) {
				// Bad design, API.
				ApiBase::$messageMap['centralauth-api-blacklistedmodule'] = array(
					'code' => 'badparams',
					'info' => 'The module "$1" may not be used with centralauthtoken',
				);
				$message = array( 'centralauth-api-blacklistedmodule', $module->getModuleName() );
				return false;
			}
		}
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
		wfRunHooks( 'CentralAuthIsUIReloadRecommended', array( $user, &$recommendReload ) );
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
			throw new MWException( "Attached user couldn't be loaded from name" );
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
}
