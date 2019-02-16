<?php

use MediaWiki\Session\Session;
use Wikimedia\ScopedCallback;

/**
 * Unlisted Special page to set requisite cookies for being logged into this wiki.
 *
 * @ingroup Extensions
 */
class SpecialCentralAutoLogin extends UnlistedSpecialPage {
	/** @var string */
	private $loginWiki;

	/** @var Session|null */
	protected $session = null;

	public function __construct() {
		parent::__construct( 'CentralAutoLogin' );
	}

	/**
	 * Get contents of a javascript file for inline use.
	 *
	 * Roughly based MediaWiki core methods:
	 * - ResourceLoader::filter()
	 * - ResourceLoaderFileModule::readScriptFiles()
	 *
	 * @param string $name Path to file relative to /modules/inline/
	 * @return string Minified script
	 * @throws Exception If file doesn't exist
	 */
	protected static function getInlineScript( $name ) {
		// Get file
		$filePath = __DIR__ . '/../../modules/inline/' . $name;
		if ( !file_exists( $filePath ) ) {
			throw new Exception( __METHOD__ . ": file not found: \"$filePath\"" );
		}
		$contents = file_get_contents( $filePath );

		// Try minified from cache
		$cache = wfGetCache( wfIsHHVM() ? CACHE_ACCEL : CACHE_ANYTHING );
		$key = $cache->makeGlobalKey( 'centralauth', 'minify-js', md5( $contents ) );
		$cacheEntry = $cache->get( $key );
		if ( is_string( $cacheEntry ) ) {
			return $cacheEntry;
		}

		// Compute new value
		try {
			$result = JavaScriptMinifier::minify( $contents );
			$cache->set( $key, $result );
		} catch ( Exception $e ) {
			MWExceptionHandler::logException( $e );
			wfDebugLog( 'CentralAuth', __METHOD__ . ": minification failed for $name: $e" );
			$result = ResourceLoader::formatException( $e ) . "\n" . $contents;
		}

		return $result;
	}

	/**
	 * Check the session (if applicable) and fill in $this->session
	 * @param string $body
	 * @param string|bool $type
	 * @return bool
	 */
	protected function checkSession( $body = '', $type = false ) {
		$session = $this->getRequest()->getSession();
		if ( !$session->getProvider() instanceof CentralAuthSessionProvider ) {
			$this->doFinalOutput(
				false,
				'Cannot operate when using ' .
					$session->getProvider()->describe( Language::factory( 'en' ) ),
				$body,
				$type
			);
			return false;
		}
		$this->session = $session;
		return true;
	}

	function execute( $par ) {
		global $wgCentralAuthLoginWiki;

		if (
			in_array( $par, [ 'refreshCookies', 'deleteCookies', 'start', 'checkLoggedIn',
			'createSession', 'validateSession', 'setCookies' ], true )
		) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'authevents' )->info(
				'Autologin ' . $par, [
					'event' => 'autologin',
					'eventType' => $par,
				]
			);
		}

		$request = $this->getRequest();
		$cache = CentralAuthUtils::getSessionCache();

		$this->loginWiki = $wgCentralAuthLoginWiki;
		if ( !$this->loginWiki ) {
			// Ugh, no central wiki. If we're coming from an edge login, make
			// the logged-into wiki the de-facto central wiki for this request
			// so auto-login still works.
			$fromwiki = $request->getVal( 'from', $request->getVal( 'notifywiki' ) );
			if ( $fromwiki !== null && WikiMap::getWiki( $fromwiki ) ) {
				$this->loginWiki = $fromwiki;
			}
		} elseif ( $request->getVal( 'from' ) === wfWikiId() &&
			$wgCentralAuthLoginWiki !== wfWikiId()
		) {
			// Remote wiki must not have wgCentralAuthLoginWiki set, but we do. Redirect them.
			$this->do302Redirect( $wgCentralAuthLoginWiki, $par, $request->getValues() );
			return;
		}

		$params = $request->getValues(
			'type',
			'from',
			'return',
			'returnto',
			'returntoquery',
			'proto',
			'mobile'
		);
		switch ( strval( $par ) ) {
		case 'P3P': // Explain the bogus P3P header
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-p3p-explanation' );
			return;

		case 'toolslist':
			// Do not cache this, we want updated Echo numbers and such.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkSession( '', 'json' ) ) {
				return;
			}

			$user = $this->getUser();
			if ( !$user->isAnon() ) {
				if ( !CentralAuthHooks::isUIReloadRecommended( $user ) ) {
					$html = $this->getSkin()->getPersonalToolsList();
					$json = FormatJson::encode( [ 'toolslist' => $html ] );
				} else {
					$gender = $this->getUser()->getOption( 'gender' );
					if ( strval( $gender ) === '' ) {
						$gender = 'unknown';
					}
					$json = FormatJson::encode( [
						'notify' => [
							'username' => $user->getName(),
							'gender' => $gender
						]
					] );
				}
				$this->doFinalOutput( true, 'OK', $json, 'json' );
			} else {
				$this->doFinalOutput( false, 'Not logged in', '', 'json' );
			}
			return;

		case 'refreshCookies': // Refresh central cookies (e.g. in case 'remember me' was set)
			// Do not cache this, we need to reset the cookies every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$wgCentralAuthLoginWiki || !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			CentralAuthUtils::setP3P();
			$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			if ( $centralUser && $centralUser->getId() && $centralUser->isAttached() ) {
				$centralSession = $this->getCentralSession( $centralUser, $this->getUser() );

				// Refresh 'remember me' preference
				$user = $this->getUser();
				$remember = (bool)$centralSession['remember'];
				if ( $remember != $user->getBoolOption( 'rememberpassword' ) ) {
					$user->setOption( 'rememberpassword', $remember ? 1 : 0 );
					DeferredUpdates::addCallableUpdate( function () use ( $user ) {
						if ( wfReadOnly() ) {
							return; // not possible to save
						}

						$user->saveSettings();
					} );
				}

				$delay = $this->session->delaySave();
				$this->session->setRememberUser( $remember );
				if ( $centralSession['secureCookies'] !== null ) {
					$this->session->setForceHTTPS( $centralSession['secureCookies'] );
				}
				$this->session->persist();
				ScopedCallback::consume( $delay );
				$this->doFinalOutput( true, 'success' );
			} else {
				$this->doFinalOutput( false, 'Not logged in' );
			}
			return;

		case 'deleteCookies': // Delete central cookies
			// Do not cache this, we need to reset the cookies every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkSession() ) {
				return;
			}

			if ( $this->getUser()->isLoggedIn() ) {
				$this->doFinalOutput( false, 'Cannot delete cookies while still logged in' );
				return;
			}

			CentralAuthUtils::setP3P();
			$this->session->setUser( new User );
			$this->session->persist();
			$this->doFinalOutput( true, 'success' );
			return;

		case 'start': // Main entry point
			// Note this is safe to cache, because the cache already varies on
			// the session cookies.
			$this->getOutput()->setCdnMaxage( 1200 );

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			CentralAuthUtils::setP3P();
			$this->do302Redirect( $this->loginWiki, 'checkLoggedIn', [
				'wikiid' => wfWikiID(),
				'proto' => $request->detectProtocol(),
			] + $params );
			return;

		case 'checkLoggedIn': // Check if we're logged in centrally
			// Note this is safe to cache, because the cache already varies on
			// the session cookies.
			$this->getOutput()->setCdnMaxage( 1200 );

			if ( !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			CentralAuthUtils::setP3P();
			if ( $this->getUser()->isLoggedIn() ) {
				$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			} else {
				$this->doFinalOutput( false, 'Not centrally logged in',
					self::getInlineScript( 'anon-set.js' ) );
				return;
			}

			// We're pretty sure this user is logged in, so pass back
			// headers to prevent caching, just in case
			$this->getOutput()->enableClientCache( false );

			// Sanity check: If the loginwiki account isn't attached, things are broken (T137551)
			if ( !$centralUser->isAttached() ) {
				$this->doFinalOutput( false,
					'Account on central wiki is not attached (this shouldn\'t happen)',
					self::getInlineScript( 'anon-set.js' )
				);
				return;
			}

			$memcData = [ 'gu_id' => $centralUser->getId() ];
			$token = MWCryptRand::generateHex( 32 );
			$key = CentralAuthUtils::memcKey( 'centralautologin-token', $token );
			$cache->set( $key, $memcData, 60 );

			$this->do302Redirect( $wikiid, 'createSession', [
				'token' => $token,
			] + $params );
			return;

		case 'createSession': // Create the local session and shared memcache token

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			CentralAuthUtils::setP3P();

			$token = $request->getVal( 'token', '' );
			$gid = $request->getVal( 'gu_id', '' );
			if ( $token !== '' ) {
				// Load memc data
				$key = CentralAuthUtils::memcKey( 'centralautologin-token', $token );
				$casToken = null;
				$memcData = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
				$cache->delete( $key );

				if ( !$memcData || !isset( $memcData['gu_id'] ) ) {
					$this->doFinalOutput( false, 'Invalid parameters' );
					return;
				}
				$gu_id = intval( $memcData['gu_id'] );
			} elseif ( $gid !== '' ) {
				// Cached, or was logging in as we switched from gu_id to token
				$gu_id = intval( $gid );
			} else {
				$this->doFinalOutput( false, 'Invalid parameters' );
				return;
			}

			if ( $gu_id <= 0 ) {
				$this->doFinalOutput( false, 'Not centrally logged in',
					self::getInlineScript( 'anon-set.js' ) );
				return;
			}

			// At this point we can't cache anymore because we need to set
			// cookies and memc each time.
			$this->getOutput()->enableClientCache( false );

			// Ensure that a session exists
			$this->session->persist();

			// Create memc token
			$wikiid = wfWikiID();
			$memcData = [
				'gu_id' => $gu_id,
				'wikiid' => $wikiid,
			];
			$token = MWCryptRand::generateHex( 32 );
			$key = CentralAuthUtils::memcKey( 'centralautologin-token', $token, $wikiid );
			$cache->set( $key, $memcData, 60 );

			// Save memc token for the 'setCookies' step
			$request->setSessionData( 'centralautologin-token', $token );

			$this->do302Redirect( $this->loginWiki, 'validateSession', [
				'token' => $token,
				'wikiid' => $wikiid,
			] + $params );
			return;

		case 'validateSession': // Validate the shared memcached token
			// Do not cache this, we need to reset the cookies and memc every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			if ( !$this->getUser()->isLoggedIn() ) {
				$this->doFinalOutput( false, 'Not logged in' );
				return;
			}

			CentralAuthUtils::setP3P();
			// Validate params
			$token = $request->getVal( 'token', '' );
			if ( $token === '' ) {
				$this->doFinalOutput( false, 'Invalid parameters' );
				return;
			}

			// Load memc data
			$key = CentralAuthUtils::memcKey( 'centralautologin-token', $token, $wikiid );
			$casToken = null;
			$memcData = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
			$cache->delete( $key );

			// Check memc data
			$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			if ( !$memcData ||
				$memcData['wikiid'] !== $wikiid ||
				!$centralUser ||
				!$centralUser->getId() ||
				!$centralUser->isAttached() ||
				$memcData['gu_id'] != $centralUser->getId()
			) {
				$this->doFinalOutput( false, 'Invalid parameters' );
				return;
			}

			// Write info for session creation into memc
			$centralSession = $this->getCentralSession( $centralUser, $this->getUser() );
			$memcData += [
				'userName' => $centralUser->getName(),
				'token' => $centralUser->getAuthToken(),
				'finalProto' => $centralSession['finalProto'],
				'secureCookies' => $centralSession['secureCookies'],
				'remember' => $centralSession['remember'],
				'sessionId' => $centralSession['sessionId'],
			];
			$cache->set( $key, $memcData, 60 );

			$this->do302Redirect( $wikiid, 'setCookies', $params );
			return;

		case 'setCookies': // Check that memcached is validated, and set cookies
			// Do not cache this, we need to reset the cookies and memc every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}
			if ( !$this->checkSession() ) {
				return;
			}

			CentralAuthUtils::setP3P();
			// Check saved memc token
			$token = $this->getRequest()->getSessionData( 'centralautologin-token' );
			if ( $token === null ) {
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			// Load memc data
			$wikiid = wfWikiID();
			$key = CentralAuthUtils::memcKey( 'centralautologin-token', $token, $wikiid );
			$casToken = null;
			$memcData = $cache->get( $key, $casToken, BagOStuff::READ_LATEST );
			$cache->delete( $key );

			// Check memc data
			if ( !$memcData ||
				$memcData['wikiid'] !== $wikiid ||
				!isset( $memcData['userName'] ) ||
				!isset( $memcData['token'] )
			) {
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			// Load and check CentralAuthUser. But don't check if it's
			// attached, because then if the user is missing en.site they
			// won't be auto logged in to any of the non-en versions either.
			$centralUser = CentralAuthUser::getInstanceByName( $memcData['userName'] );
			if ( !$centralUser->getId() || $centralUser->getId() != $memcData['gu_id'] ) {
				$msg = "Wrong user: expected {$memcData['gu_id']}, got {$centralUser->getId()}";
				wfDebug( __METHOD__ . ": $msg\n" );
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}
			$loginResult = $centralUser->authenticateWithToken( $memcData['token'] );
			if ( $loginResult != 'ok' ) {
				$msg = "Bad token: $loginResult";
				wfDebug( __METHOD__ . ": $msg\n" );
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			/** @var ScopedCallback|null $delay */
			$delay = null;

			$delay = $this->session->delaySave();
			$this->session->resetId();
			CentralAuthUtils::setCentralSession( [
				'finalProto' => $memcData['finalProto'],
				'secureCookies' => $memcData['secureCookies'],
				'remember' => $memcData['remember'],
			], $memcData['sessionId'], $this->session );
			if ( $centralUser->isAttached() ) {
				// Set the user on the session, if the user is already attached.
				$this->session->setUser( User::newFromName( $centralUser->getName() ) );
			}
			$this->session->setRememberUser( $memcData['remember'] );
			if ( $memcData['secureCookies'] !== null ) {
				$this->session->setForceHTTPS( $memcData['secureCookies'] );
			}
			$this->session->persist();

			// Now, figure out how to report this back to the user.

			// First, set to redo the edge login on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );

			// If it's not a script callback, just go for it.
			if ( $request->getVal( 'type' ) !== 'script' ) {
				$this->doFinalOutput( true, 'success' );
				ScopedCallback::consume( $delay );
				return;
			}

			// If it is a script callback, then we do want to create the user
			// if it doesn't already exist locally (and fail if that can't be
			// done).
			if ( !User::idFromName( $centralUser->getName() ) ) {
				$user = new User;
				$user->setName( $centralUser->getName() );
				if ( CentralAuthUtils::autoCreateUser( $user )->isGood() ) {
					$centralUser->invalidateCache();
				}
			}
			if ( !$centralUser->isAttached() ) {
				$this->doFinalOutput(
					false, 'Local user is not attached', self::getInlineScript( 'anon-set.js' ) );
				ScopedCallback::consume( $delay );
				return;
			}
			// Set the user on the session now that we know it exists.
			$this->session->setUser( User::newFromName( $centralUser->getName() ) );
			ScopedCallback::consume( $delay );

			\MediaWiki\Logger\LoggerFactory::getInstance( 'authevents' )->info(
				'Autologin success',
				[
					'event' => 'autologin',
					'eventType' => 'success',
				]
			);

			$script = self::getInlineScript( 'anon-remove.js' );

			// If we're returning to returnto, do that
			if ( $request->getCheck( 'return' ) ) {
				global $wgRedirectOnLogin;

				if ( $wgRedirectOnLogin !== null ) {
					$returnTo = $wgRedirectOnLogin;
					$returnToQuery = [];
				} else {
					$returnTo = $request->getVal( 'returnto', '' );
					$returnToQuery = wfCgiToArray( $request->getVal( 'returntoquery', '' ) );
				}

				$returnToTitle = Title::newFromText( $returnTo );
				if ( !$returnToTitle ) {
					$returnToTitle = Title::newMainPage();
					$returnToQuery = [];
				}

				$redirectUrl = $returnToTitle->getFullUrlForRedirect( $returnToQuery );

				$script .= "\n" . 'location.href = ' . Xml::encodeJsVar( $redirectUrl ) . ';';

				$this->doFinalOutput( true, 'success', $script );
				return;
			}

			// Otherwise, we need to rewrite p-personal and maybe notify the user too
			global $wgCentralAuthUseEventLogging;
			if ( $wgCentralAuthUseEventLogging ) {
				EventLogging::logEvent( 'CentralAuth', 5690875,
					[ 'version' => 1,
						'userId' => $centralUser->getId(),
						'action' => 'sul2-autologin-fallbacklogin'
					]
				);
			}

			// Add a script to the page that will pull in the user's toolslist
			// via ajax, and update the UI. Don't write out the tools here (bug 57081).
			$code = $this->getUser()->getOption( 'language' );
			$code = RequestContext::sanitizeLangCode( $code );
			Hooks::run( 'UserGetLanguageObject',
				[ $this->getUser(), &$code, $this->getContext() ]
			);
			$script .= "\n" . Xml::encodeJsCall( 'mediaWiki.messages.set', [
				[
					'centralauth-centralautologin-logged-in' =>
						wfMessage( 'centralauth-centralautologin-logged-in' )
							->inLanguage( $code )->plain(),

					'centralauth-centralautologin-logged-in-nouser' =>
						wfMessage( 'centralauth-centralautologin-logged-in-nouser' )
							->inLanguage( $code )->plain(),

					'centralautologin' =>
						wfMessage( 'centralautologin' )
							->inLanguage( $code )->plain(),
				]
			] );

			$script .= "\n" . self::getInlineScript( 'autologin.js' );

			// And for good measure, add the edge login HTML images to the page.
			$script .= "\n" . Xml::encodeJsCall( "jQuery( 'body' ).append", [
				CentralAuthHooks::getEdgeLoginHTML()
			] );

			$this->doFinalOutput( true, 'success', $script );
			return;

		default:
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-desc' );
			return;
		}
	}

	private function do302Redirect( $target, $state, $params ) {
		$url = WikiMap::getForeignURL( $target, "Special:CentralAutoLogin/$state" );
		if ( wfWikiID() == $this->loginWiki
			&& class_exists( MobileContext::class )
			&& isset( $params['mobile'] )
			&& $params['mobile']
		) {
			$url = MobileContext::singleton()->getMobileUrl( $url );
		}

		if ( $url === false ) {
			$this->doFinalOutput( false, 'Invalid target wiki' );
		} else {
			$this->getOutput()->redirect( // expands to PROTO_CURRENT
				wfAppendQuery( $url, $params )
			);
		}
	}

	private function doFinalOutput( $ok, $status, $body = '', $type = false ) {
		$this->getOutput()->disable();
		wfResetOutputBuffers();
		$this->getOutput()->sendCacheControl();

		$type = $type ?: $this->getRequest()->getVal( 'type', 'script' );
		if ( $type === 'icon' || $type === '1x1' ) {
			header( 'Content-Type: image/png' );
			header( "X-CentralAuth-Status: $status" );

			global $wgCentralAuthLoginIcon;
			if ( $ok && $wgCentralAuthLoginIcon && $type === 'icon' ) {
				readfile( $wgCentralAuthLoginIcon );
			} else {
				readfile( __DIR__ . '/../../1x1.png' );
			}
		} elseif ( $type === 'json' ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( "X-CentralAuth-Status: $status" );
			echo $body;
		} else {
			header( 'Content-Type: text/javascript; charset=utf-8' );
			echo "/* $status */\n$body";
		}
	}

	private function checkIsCentralWiki( &$wikiId ) {
		if ( wfWikiID() !== $this->loginWiki ) {
			$this->doFinalOutput( false, 'Not central wiki' );
			return false;
		}

		$wikiId = $this->getRequest()->getVal( 'wikiid' );
		if ( $wikiId === $this->loginWiki ) {
			$this->doFinalOutput( false, 'Specified local wiki is the central wiki' );
			return false;
		}
		$wiki = WikiMap::getWiki( $wikiId );
		if ( !$wiki ) {
			$this->doFinalOutput( false, 'Specified local wiki not found' );
			return false;
		}

		return true;
	}

	private function checkIsLocalWiki() {
		if ( wfWikiID() === $this->loginWiki ) {
			$this->doFinalOutput( false, 'Is central wiki, should be local' );
			return false;
		}

		return true;
	}

	/**
	 * @param CentralAuthUser $centralUser
	 * @param User $user
	 * @return array
	 */
	private function getCentralSession( $centralUser, $user ) {
		$centralSession = CentralAuthUtils::getCentralSession( $this->session );
		$request = $this->getRequest();

		// If there's no "finalProto", check if one was passed, and otherwise
		// assume the current.
		if ( !isset( $centralSession['finalProto'] ) ) {
			$centralSession['finalProto'] = $request->getVal( 'proto', $request->detectProtocol() );
		}

		// If there's no "remember", pull from the user preference.
		if ( !isset( $centralSession['remember'] ) ) {
			$centralSession['remember'] = $user->getBoolOption( 'rememberpassword' );
		}

		// Make sure there's a value for secureCookies
		if ( !isset( $centralSession['secureCookies'] ) ) {
			$centralSession['secureCookies'] = (
				$user->getBoolOption( 'prefershttps' ) && wfCanIPUseHTTPS( $request->getIP() )
			);
		}

		// Make sure there's a session id by creating a session if necessary.
		if ( !isset( $centralSession['sessionId'] ) ) {
			$centralSession['sessionId'] = CentralAuthUtils::setCentralSession(
				$centralSession, false, $this->session );
		}

		return $centralSession;
	}
}
