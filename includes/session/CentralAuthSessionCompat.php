<?php

// Sanity check: This file should not be referenced when we have SessionManager
if ( class_exists( '\\MediaWiki\\Session\\SessionManager' ) ) {
	throw new RuntimeException( __FILE__ . ' loaded when SessionManager exists!' );
}

class CentralAuthSessionCompat {

	/**
	 * @param $name
	 * @param $value
	 * @param $exp
	 * @param bool $secure
	 *  true: Force setting the secure attribute when setting the cookie
	 *  false: Force NOT setting the secure attribute when setting the cookie
	 *  null (default): Use the default ($wgCookieSecure) to set the secure attribute
	 * @param string|bool $prefix cookie prefix; false to use $wgCentralAuthCookiePrefix
	 * @throws Exception
	 * @return void
	 */
	public static function setCookie( $name, $value, $exp = -1, $secure = null, $prefix = false ) {
		global $wgCentralAuthCookiePrefix, $wgCentralAuthCookieDomain,
			$wgCookieExpiration, $wgCentralAuthCookiePath, $wgExtendedLoginCookieExpiration;

		if ( self::hasApiToken() ) {
			throw new Exception( "Cannot set cookies when API 'centralauthtoken' parameter is given" );
		}

		CentralAuthUtils::setP3P();

		if ( $exp == -1 ) {
			$exp = time();

			if ( $wgExtendedLoginCookieExpiration !== null ) {
				$exp += $wgExtendedLoginCookieExpiration;
			} else {
				$exp += $wgCookieExpiration;
			}
		} elseif ( $exp == 0 ) {
			// Session cookie
			$exp = null;
		} elseif ( $exp < 3.16e7 ) {
			// Relative expiry
			$exp += time();
		}

		if ( $prefix === false ) {
			 $prefix = $wgCentralAuthCookiePrefix;
		}

		RequestContext::getMain()->getRequest()->response()->setcookie(
			$name, $value, $exp, array(
				'prefix' => $prefix,
				'path' => $wgCentralAuthCookiePath,
				'domain' => $wgCentralAuthCookieDomain,
				'secure' => $secure,
			)
		);
	}

	/**
	 * @param  $name
	 * @return void
	 */
	protected static function clearCookie( $name ) {
		self::setCookie( $name, '', - 86400 );
	}

	/**
	 * Set data in the central session, and set the cookie
	 * @param array $data
	 * @param bool|string $refreshId Reset the session ID. If a string, this is the new ID.
	 * @param bool $secure
	 * @return string|null Session ID
	 */
	public static function setCentralSession( array $data, $reset = false, $secure = null ) {
		$id = CentralAuthUtils::setCentralSession( $data, $reset );
		if ( $id !== null ) {
			CentralAuthSessionCompat::setCookie( 'Session', $id, 0, $secure );
		}
		return $id;
	}

	/**
	 * Set a global cookie that auto-authenticates the user on other wikis.
	 * This also destroys and "pending_name"/"pending_guid" keys in the session,
	 * which exist when a partially authenticated stub session is created.
	 *
	 * Called on login.
	 *
	 * $refreshId can have three values:
	 *   - True   : refresh the SessionID when setting the cookie to a new random ID.
	 *   - String : refresh the SessionID when setting the cookie to the given ID.
	 *   - False  : use the SessionID of the client cookie (make a new one if there is none).
	 *
	 * @param CentralAuthUser $user
	 * @param bool $remember
	 * @param bool|string $refreshId
	 * @param bool $secure
	 *  true: Force setting the secure attribute when setting the cookie
	 *  false: Force NOT setting the secure attribute when setting the cookie
	 *  null (default): Use the default ($wgCookieSecure) to set the secure attribute
	 * @param array $sessionData Extra key-value pairs to include in the session
	 * @return string Session ID
	 */
	public static function setGlobalCookies(
		CentralAuthUser $user, $remember = false, $refreshId = false, $secure = null,
		$sessionData = array()
	) {
		global $wgCookieSecure;

		$session = array();
		$session['user'] = $user->getName();
		self::setCookie( 'User', $user->getName(), -1, $secure );
		$session['token'] = $user->getAuthToken();
		$session['expiry'] = time() + 86400;
		$session['auto-create-blacklist'] = array();
		$session += $sessionData;

		if ( $remember ) {
			self::setCookie( 'Token', $user->getAuthToken(), -1, $secure );
		} else {
			self::clearCookie( 'Token' );
		}

		$id = CentralAuthSessionCompat::setCentralSession( $session, $refreshId, $secure );

		if ( $secure || ( $secure === null && $wgCookieSecure ) ) {
			$forceTime = ( $remember ? -1 : 0 );

			// Core set a forceHTTPS cookie with a different domain. Delete
			// that one, and set our own.
			RequestContext::getMain()->getRequest()->response()->setcookie(
				'forceHTTPS', '', -86400, array(
					'prefix' => '',
					'secure' => false,
				)
			);
			self::setCookie( 'forceHTTPS', '1', $forceTime, false, '' );
		} else {
			// Bug 54626: Explcitly clear forceHTTPS cookie when it's not wanted
			self::setCookie( 'forceHTTPS', '', -86400, false, '' );
		}

		return $id;
	}

	/**
	 * Delete global cookies which auto-authenticate the user on other wikis.
	 * Called on logout.
	 */
	public static function deleteGlobalCookies() {
		self::clearCookie( 'User' );
		self::clearCookie( 'Token' );
		self::clearCookie( 'Session' );
		self::setCookie( 'forceHTTPS', '', -86400, false, '' );

		// Logged-out cookie -to fix caching.
		self::setCookie( 'LoggedOut', time() );

		CentralAuthUtils::deleteCentralSession();
	}

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
	 * @see self::hasApiToken()
	 * @param bool $invalidateToken If true, invalidate the passed token
	 * @return CentralAuthUser|null
	 */
	private static function getApiCentralUser( $invalidateToken ) {
		if ( !self::hasApiToken() ) {
			return null;
		}

		global $wgRequest;
		static $cachedUser = false;

		$cache = CentralAuthUtils::getSessionCache();
		if ( $cachedUser === false ) {
			$loginToken = $wgRequest->getVal( 'centralauthtoken' );
			$key = CentralAuthUtils::memcKey( 'api-token', $loginToken );
			$cachedUser = null;

			$data = $cache->get( $key );
			if ( $invalidateToken ) {
				$cache->delete( $key );
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
			$key = CentralAuthUtils::memcKey( 'api-token', $loginToken );
			$cache->delete( $key );
		}

		return $cachedUser;
	}

	/**
	 * @return bool
	 */
	public static function onSetupAfterCache() {
		if ( self::hasApiToken() ) {
			// If the API 'centralauthtoken' parameter is set, we don't want to
			// be setting cookies. So disable setting of the session cookie
			// and clear $_COOKIE.
			// We can't try to load the saved session cookies from memcached
			// yet, because getting the central user requires that $wgContLang
			// is set up.
			// @see self::hasApiToken()
			ini_set( 'session.use_cookies', 0 );
			$_COOKIE = array();
		}
		return true;
	}

	/**
	 * @param $auth
	 * @return bool
	 */
	public static function onAuthPluginSetup( &$auth ) {
		// If the API 'centralauthtoken' parameter is set, we don't use the
		// browser-supplied cookies. Now that $wgContLang is set up, we can
		// replace them with the cookies passed via memcached instead.
		// @see self::hasApiToken()
		$centralUser = self::getApiCentralUser( false );
		if ( $centralUser ) {
			$cache = CentralAuthUtils::getSessionCache();
			$key = CentralAuthUtils::memcKey( 'api-cookies', md5( $centralUser->getName() ), wfWikiID() );
			$cookies = $cache->get( $key );
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
			$cache->set( $key, $cookies, 86400 );
			$_COOKIE = $cookies;
			wfSetupSession( $cookies[session_name()] );
		}

		return true;
	}

	/**
	 * @param $user User
	 * @param $inject_html string
	 * @return bool
	 */
	public static function onUserLoginComplete( &$user, &$inject_html ) {
		global $wgCentralAuthCookies;

		if ( $wgCentralAuthCookies ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
				CentralAuthSessionCompat::deleteGlobalCookies();
			}
		}

		return true;
	}

	/**
	 * @param $user User
	 * @param $result
	 * @return bool
	 */
	public static function onUserLoadFromSession( $user, &$result ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;
		if ( !$wgCentralAuthCookies ) {
			// Check if the user is being renamed
			// At this point, the User object will not have been populated yet
			// so we need to look directly at the request.
			$req = $user->getRequest();
			$name = $req->getSessionData( 'wsUserName' ) ?: $req->getCookie( 'UserName' );
			if ( $name ) {
				// Log the user out if they're being renamed. We'll give them an error message
				// when they try logging in
				// Also don't use CentralAuthUser::getInstance, we don't want to cache it on failure.
				$centralUser = new CentralAuthUser( $name );
				if ( $centralUser->renameInProgress() ) {
					$result = false;
					return false;
				}
			}

			return true;
		}


		if ( self::hasApiToken() ) {
			$centralUser = self::getApiCentralUser( false );
			if ( !$centralUser ) {
				return true;
			}
			$userName = $centralUser->getName();
			$token = $centralUser->getAuthToken();
		} else {
			$prefix = $wgCentralAuthCookiePrefix;

			if ( isset( $_COOKIE["{$prefix}User"] ) && isset( $_COOKIE["{$prefix}Token"] ) ) {
				$userName = $_COOKIE["{$prefix}User"];
				$token = $_COOKIE["{$prefix}Token"];
			} elseif ( (bool)( $session = CentralAuthUtils::getCentralSession() ) ) {
				if ( isset( $session['pending_name'] ) || isset( $session['pending_guid'] ) ) {
					wfDebug( __METHOD__ . ": unintialized session\n" );
					return true;
				} elseif ( isset( $session['token'] ) && isset( $session['user'] ) ) {
					$token = $session['token'];
					$userName = $session['user'];
				} else {
					wfDebug( __METHOD__ . ": no token or session\n" );
					return true;
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

			// Try the central user
			// Don't use CentralAuthUser::getInstance, we don't want to cache it on failure.
			$centralUser = new CentralAuthUser( $userName );

			// Log the user out if they're being renamed
			if ( $centralUser->renameInProgress() ) {
				$result = false;
				return false;
			}

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
			$user->setName( $userName );
			if ( !self::attemptAddUser( $user ) ) {
				// Can't create user, give up now
				$user->setName( false );
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
		if ( $wgCentralAuthCookies ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $centralUser->exists() ) {
				CentralAuthSessionCompat::deleteGlobalCookies();
			}
		}

		return true;
	}

	/**
	 * @param $out OutputPage
	 * @param $cookies array
	 * @return bool
	 */
	public static function onGetCacheVaryCookies( $out, &$cookies ) {
		global $wgCentralAuthCookiePrefix;
		$cookies[] = $wgCentralAuthCookiePrefix . 'Token';
		$cookies[] = $wgCentralAuthCookiePrefix . 'Session';
		$cookies[] = $wgCentralAuthCookiePrefix . 'LoggedOut';
		return true;
	}

	/**
	 * Helper function for onUserLoadFromSession
	 * @param $user User
	 * @param $token
	 */
	private static function initSession( $user, $token ) {
		$userName = $user->getName();
		wfSetupSession();
		if ( $token != @$_SESSION['globalloggedin'] ) { // FIXME: Usage of @
			$_SESSION['globalloggedin'] = $token;
			$user->touch();
			wfDebug( __METHOD__ . ": Initialising session for $userName with token $token.\n" );
		} else {
			wfDebug( __METHOD__ . ": Session already initialised for $userName with token $token.\n" );
		}
	}

	/**
	 * Attempt to add a user to the database
	 * Does the required authentication checks and updates for auto-creation
	 * @param $user User
	 * @throws Exception
	 * @return bool Success
	 */
	public static function attemptAddUser( $user ) {
		global $wgAuth, $wgCentralAuthCreateOnView, $wgMemc;

		// Denied by configuration?
		if ( !$wgAuth->autoCreate() ) {
			wfDebug( __METHOD__ . ": denied by configuration\n" );
			return false;
		} elseif ( !$wgCentralAuthCreateOnView ) {
			// Only create local accounts when we perform an active login...
			// Don't freak people out on every page view
			wfDebug( __METHOD__ . ": denied by \$wgCentralAuthCreateOnView\n" );
			return false;
		} elseif ( CentralAuthUtils::isReadOnly() ) {
			wfDebug( __METHOD__ . ": denied by wfReadOnly()\n" );
			return false;
		}

		$userName = $user->getName();

		// Is the user blacklisted by the session?
		// This is just a cache to avoid expensive DB queries in $user->isAllowedToCreateAccount().
		// The user can log in via Special:UserLogin to bypass the blacklist and get a proper
		// error message.
		$session = CentralAuthUtils::getCentralSession();
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
			CentralAuthSessionCompat::setCentralSession( $session );
			return false;
		}

		// Check for validity of username
		if ( !User::isCreatableName( $userName ) ) {
			wfDebug( __METHOD__ . ": Invalid username\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthSessionCompat::setCentralSession( $session );
			return false;
		}

		// Give other extensions a chance to stop auto creation.
		$user->loadDefaults( $userName );
		$abortMessage = '';
		if ( !Hooks::run( 'AbortAutoAccount', array( $user, &$abortMessage ) ) ) {
			// In this case we have no way to return the message to the user,
			// but we can log it.
			wfDebug( __METHOD__ . ": denied by other extension: $abortMessage\n" );
			$session['auto-create-blacklist'][] = wfWikiID();
			CentralAuthSessionCompat::setCentralSession( $session );
			return false;
		}

		// Make sure the name has not been changed
		if ( $user->getName() !== $userName ) {
			throw new Exception( "AbortAutoAccount hook tried to change the user name" );
		}

		// Ignore warnings about master connections/writes...hard to avoid here
		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$backoffKey = wfMemcKey( 'CentralAuth', 'autocreate-failed', md5( $userName ) );
		if ( $wgMemc->get( $backoffKey ) ) {
			wfDebug( __METHOD__ . ": denied by prior creation attempt failures" );
			return false;
		}

		// Checks passed, create the user...
		$from = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'CLI';
		wfDebugLog( 'CentralAuth-Bug39996', __METHOD__ .
			": creating new user ($userName) - from: $from\n" );
		try {
			// Make sure the central DB master is availabe
			CentralAuthUtils::getCentralDB();
			// Insert the user into the local DB master
			$status = $user->addToDatabase();
			if ( $status === null ) {
				// MW before 1.21 -- ok, continue
			} elseif ( !$status->isOK() ) {
				wfDebugLog( 'CentralAuth-Bug39996', __METHOD__ .
					": failed with message " . $status->getWikiText() . "\n" );
				return false;
			}
			// Attach the user to the central user and update prefs
			$wgAuth->initUser( $user, true );
		} catch ( Exception $e ) {
			wfDebugLog( 'CentralAuth-Bug39996', __METHOD__ .
				" User::addToDatabase for \"$userName\" threw an exception:"
				. " {$e->getMessage()}" );
			// Do not keep throwing errors for a while
			$wgMemc->set( $backoffKey, 1, 60 * 10 );
			// Bubble up error; which should normally trigger DB rollbacks
			throw $e;
		}

		# Notify hooks (e.g. Newuserlog)
		Hooks::run( 'AuthPluginAutoCreate', array( $user ) );

		# Update user count
		DeferredUpdates::addUpdate( new SiteStatsUpdate( 0, 0, 0, 0, 1 ) );

		return true;
	}

	/**
	 * Destroy local login cookies so that remote logout works
	 * @param $user User
	 * @param $session
	 * @param $cookies
	 * @throws Exception
	 * @return bool
	 */
	public static function onUserSetCookies( $user, &$session, &$cookies ) {
		if ( self::hasApiToken() ) {
			throw new Exception( "Cannot set cookies when API 'centralauthtoken' parameter is given" );
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
		$secureCookie = $user->getBoolOption( 'prefershttps' ) ? null : false;
		CentralAuthSessionCompat::setGlobalCookies( $centralUser, $remember, true, $secureCookie );
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
		if ( !Hooks::run( 'CentralAuthAbortCentralAuthToken' ) ) {
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
				$loginToken = MWCryptRand::generateHex( 32 ) . dechex( $centralUser->getId() );

				$data = array(
					'userName' => $wgUser->getName(),
					'token' => $centralUser->getAuthToken(),
				);

				$key = CentralAuthUtils::memcKey( 'api-token', $loginToken );
				CentralAuthUtils::getSessionCache()->set( $key, $data, 60 );

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
				'Use action=centralauthtoken on this wiki to retrieve the token, before making the CORS request. Each token may only be used once, and expires after 10 seconds.',
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

}
