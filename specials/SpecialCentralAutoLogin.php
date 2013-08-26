<?php

/**
 * Unlisted Special page to set requisite cookies for being logged into this wiki.
 *
 * @ingroup Extensions
 */
class SpecialCentralAutoLogin extends UnlistedSpecialPage {
	private $loginWiki;

	function __construct() {
		parent::__construct( 'CentralAutoLogin' );
	}

	function execute( $par ) {
		global $wgMemc, $wgUser, $wgCentralAuthLoginWiki;

		$notLoggedInScript = "var t = new Date();" .
			"t.setTime( t.getTime() + 86400000 );" .
			"if ( 'localStorage' in window ) {" .
			"localStorage.setItem( 'CentralAuthAnon', t.getTime() );" .
			"} else {" .
			"document.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';" .
			"}";

		$request = $this->getRequest();

		$this->loginWiki = $wgCentralAuthLoginWiki;
		if ( !$this->loginWiki ) {
			// Ugh, no central wiki. If we're coming from an edge login, make
			// the logged-into wiki the de-facto central wiki for this request
			// so auto-login still works.
			$fromwiki = $request->getVal( 'from', $request->getVal( 'notifywiki' ) );
			if ( $fromwiki !== null && WikiMap::getWiki( $fromwiki ) ) {
				$this->loginWiki = $fromwiki;
			}
		} elseif( $request->getVal( 'from' ) === wfWikiId() && $wgCentralAuthLoginWiki !== wfWikiId() ) {
			// Remote wiki must not have wgCentralAuthLoginWiki set, but we do. Redirect them.
			$this->do302Redirect( $wgCentralAuthLoginWiki, $par, $request->getValues() );
			return;
		}

		$params = $request->getValues(
			'type',
			'from',
			'return',
			'returnto',
			'returnquery',
			'proto'
		);

		switch ( strval( $par ) ) {
		case 'P3P': // Explain the bogus P3P header
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-p3p-explanation' );
			return;

		case 'refreshCookies': // Refresh central cookies (e.g. in case 'remember me' was set)
			// Do not cache this, we need to reset the cookies every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$wgCentralAuthLoginWiki || !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}

			CentralAuthUser::setP3P();
			$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			if ( $centralUser && $centralUser->getId() ) {
				$centralSession = $this->getCentralSession( $centralUser );

				// Refresh 'remember me' preference
				$remember = (bool)$centralSession['remember'];
				if ( $remember != $this->getUser()->getBoolOption( 'rememberpassword' ) ) {
					$this->getUser()->setOption( 'rememberpassword', $remember ? 1 : 0 );
					$this->getUser()->saveSettings();
				}

				$secureCookie = $centralSession['secureCookies'];
				$centralUser->setGlobalCookies( $remember, false, $secureCookie, $centralSession );
				$this->doFinalOutput( true, 'success' );
			} else {
				$this->doFinalOutput( false, 'Not logged in' );
			}
			return;

		case 'start': // Main entry point
			// Note this is safe to cache, because the cache already varies on
			// the session cookies.

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}

			CentralAuthUser::setP3P();
			$this->do302Redirect( $this->loginWiki, 'checkLoggedIn', array(
				'wikiid' => wfWikiID(),
				'proto' => $request->detectProtocol(),
			) + $params );
			return;

		case 'checkLoggedIn': // Check if we're logged in centrally
			// Note this is safe to cache, because the cache already varies on
			// the session cookies.

			if ( !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}

			CentralAuthUser::setP3P();
			$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			$this->do302Redirect( $wikiid, 'createSession', array(
				'gu_id' => $centralUser ? $centralUser->getId() : 0,
			) + $params );
			return;

		case 'createSession': // Create the local session and shared memcache token
			// The initial checks here are safe to cache. Once we start setting
			// cookies and memc, we can no longer cache it.

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}

			CentralAuthUser::setP3P();
			$gu_id = +$request->getVal( 'gu_id', 0 );
			if ( $gu_id <= 0 ) {
				$this->doFinalOutput( false, 'Not centrally logged in', $notLoggedInScript );
				return;
			}

			// At this point we can't cache anymore because we need to set
			// cookies and memc each time.
			$this->getOutput()->enableClientCache( false );

			// Ensure that a session exists
			if ( session_id() == '' ) {
				wfSetupSession();
			}

			// Create memc token
			$wikiid = wfWikiID();
			$memcData = array(
				'gu_id' => $gu_id,
				'wikiid' => $wikiid,
			);
			$token = MWCryptRand::generateHex( 32 ) . dechex( $gu_id );
			$key = CentralAuthUser::memcKey( 'centralautologin-token', $token, $wikiid );
			$wgMemc->set( $key, $memcData, 60 );

			// Save memc token for the 'setCookies' step
			$request->setSessionData( 'centralautologin-token', $token );

			$this->do302Redirect( $this->loginWiki, 'validateSession', array(
				'token' => $token,
				'wikiid' => $wikiid,
			) + $params );
			return;

		case 'validateSession': // Validate the shared memcached token
			// Do not cache this, we need to reset the cookies and memc every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkIsCentralWiki( $wikiid ) ) {
				return;
			}

			CentralAuthUser::setP3P();
			// Validate params
			$token = $request->getVal( 'token', '' );
			if ( $token === '' ) {
				$this->doFinalOutput( false, 'Invalid parameters' );
				return;
			}

			// Load memc data
			$key = CentralAuthUser::memcKey( 'centralautologin-token', $token, $wikiid );
			$memcData = $wgMemc->get( $key );
			$wgMemc->delete( $key );

			// Check memc data
			$centralUser = CentralAuthUser::getInstance( $this->getUser() );
			if ( !$memcData ||
				$memcData['wikiid'] !== $wikiid ||
				!$centralUser ||
				!$centralUser->getId() ||
				$memcData['gu_id'] != $centralUser->getId()
			) {
				$this->doFinalOutput( false, 'Invalid parameters' );
				return;
			}

			// Write info for session creation into memc
			$centralSession = $this->getCentralSession( $centralUser );
			$memcData += array(
				'userName' => $centralUser->getName(),
				'token' => $centralUser->getAuthToken(),
				'finalProto' => $centralSession['finalProto'],
				'secureCookies' => $centralSession['secureCookies'],
				'remember' => $centralSession['remember'],
				'sessionId' => $centralSession['sessionId'],
			);
			$wgMemc->set( $key, $memcData, 60 );

			$this->do302Redirect( $wikiid, 'setCookies', $params );
			return;

		case 'setCookies': // Check that memcached is validated, and set cookies
			// Do not cache this, we need to reset the cookies and memc every time.
			$this->getOutput()->enableClientCache( false );

			if ( !$this->checkIsLocalWiki() ) {
				return;
			}

			CentralAuthUser::setP3P();
			// Check saved memc token
			$token = $this->getRequest()->getSessionData( 'centralautologin-token' );
			if ( $token === null ) {
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			// Load memc data
			$wikiid = wfWikiID();
			$key = CentralAuthUser::memcKey( 'centralautologin-token', $token, $wikiid );
			$memcData = $wgMemc->get( $key );
			$wgMemc->delete( $key );

			// Check memc data
			if ( !$memcData ||
				$memcData['wikiid'] !== $wikiid ||
				!isset( $memcData['userName'] ) ||
				!isset( $memcData['token'] )
			) {
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			// Load and check CentralAuthUser
			$centralUser = new CentralAuthUser( $memcData['userName'] );
			if ( !$centralUser->getId() || $centralUser->getId() != $memcData['gu_id'] ) {
				$msg = "Wrong user: expected {$memcData['gu_id']}, got {$centralUser->getId()}";
				wfDebug( __METHOD__ . ": $msg\n" );
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}
			if ( !$centralUser->isAttached() ) {
				$this->doFinalOutput( false, 'Local user is not attached', $notLoggedInScript );
				return;
			}
			$loginResult = $centralUser->authenticateWithToken( $memcData['token'] );
			if ( $loginResult != 'ok' ) {
				$msg = "Bad token: $loginResult";
				wfDebug( __METHOD__ . ": $msg\n" );
				$this->doFinalOutput( false, 'Lost session' );
				return;
			}

			// Set a new session cookie, Just In Case™
			wfResetSessionID();

			// Set central cookies too, with a refreshed sessionid. Also, check if we
			// need to override the default cookie security policy
			$secureCookie = $memcData['secureCookies'];

			$centralUser->setGlobalCookies(
				$memcData['remember'], $memcData['sessionId'], $secureCookie, array(
					'finalProto' => $memcData['finalProto'],
					'secureCookies' => $memcData['secureCookies'],
					'remember' => $memcData['remember'],
				)
			);

			// Now, figure out how to report this back to the user.

			// If it's not a script callback, just go for it.
			if ( $request->getVal( 'type' ) !== 'script' ) {
				$this->doFinalOutput( true, 'success' );
				return;
			}

			$script = "if ( 'localStorage' in window ) {" .
				"localStorage.removeItem( 'CentralAuthAnon' );" .
				"}" .
				"if ( /(^|; )CentralAuthAnon=/.test( document.cookie ) ) {" .
				"document.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';" .
				"}\n";

			// If we're returning to returnto, do that
			if ( $request->getCheck( 'return' ) ) {
				global $wgRedirectOnLogin;

				if ( $wgRedirectOnLogin !== null ) {
					$returnTo = $wgRedirectOnLogin;
					$returnToQuery = array();
				} else {
					$returnTo = $request->getVal( 'returnto', '' );
					$returnToQuery = wfCgiToArray( $request->getVal( 'returntoquery', '' ) );
				}

				$returnToTitle = Title::newFromText( $returnTo );
				if ( !$returnToTitle ) {
					$returnToTitle = Title::newMainPage();
					$returnToQuery = array();
				}

				$redirectUrl = $returnToTitle->getFullURL( $returnToQuery );

				$script .= "window.location.href = " . Xml::encodeJsVar( $redirectUrl ) . ';';

				$this->doFinalOutput( true, 'success', $script );
				return;
			}

			// Otherwise, we need to rewrite p-personal and maybe notify the user too
			global $wgCentralAuthUseEventLogging;
			if ( $wgCentralAuthUseEventLogging ) {
				efLogServerSideEvent( 'CentralAuth', 5690875,
					array( 'version' => 1,
						'userId' => $centralUser->getId(),
						'action' => 'sul2-autologin-fallbacklogin'
					)
				);
			}

			// This is hacky, and may not work right with all skins and
			// other extensions.
			$this->getContext()->setUser( User::newFromName( $centralUser->getName() ) );
			$wgUser = $this->getUser();
			$skin = $this->getSkin();
			$skin->getContext()->setUser( $this->getUser() ); // Just in case
			$html = $skin->getPersonalToolsList();

			// This is also hacky.
			$script .= Xml::encodeJsCall(
				"jQuery( '#p-personal ul' ).html",
				array( $html )
			);

			// Sigh.
			$script .= "jQuery( '#p-personal' ).addClass( 'centralAuthPPersonalAnimation' );";

			// Fire a hook for other extensions to listen for
			$script .= "mediaWiki.hook( 'centralauth-p-personal-reset' ).fire();";

			if ( CentralAuthHooks::isUIReloadRecommended( $this->getUser() ) ) {
				$gender = $this->getUser()->getOption( 'gender' );
				if ( strval( $gender ) === '' ) {
					$gender = 'unknown';
				}

				// We want the message to be in the user's language, so set it
				// from here now that we have an actual user.
				// @todo RequestContext should automatically clear its internal
				//     $lang when a new user is set.
				$code = $this->getUser()->getOption( 'language' );
				$code = RequestContext::sanitizeLangCode( $code );
				wfRunHooks( 'UserGetLanguageObject', array( $this->getUser(), &$code, $this->getContext() ) );
				$script .= Xml::encodeJsCall( 'mediaWiki.messages.set', array(
					'centralauth-centralautologin-logged-in',
					wfMessage( 'centralauth-centralautologin-logged-in' )
						->inLanguage( $code )->plain()
				) );

				$script .= Xml::encodeJsCall( 'mediaWiki.notify', array(
					new XmlJsCode( trim( Xml::encodeJsCall( 'mediaWiki.message', array(
						'centralauth-centralautologin-logged-in',
						$this->getUser()->getName(),
						$gender
					) ), ';' ) ),
					array(
						'title' => wfMessage( 'centralautologin' )->plain(),
						'autoHide' => false,
						'tag' => 'CentralAutoLogin',
					),
				) );
			}

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
		if ( $url === false ) {
			$this->doFinalOutput( false, 'Invalid target wiki' );
		} else {
			$this->getOutput()->redirect( // expands to PROTO_CURRENT
				wfAppendQuery( $url, $params )
			);
		}
	}

	private function doFinalOutput( $ok, $status, $script = '' ) {
		$this->getOutput()->disable();
		wfResetOutputBuffers();
		$this->getOutput()->sendCacheControl();

		$type = $this->getRequest()->getVal( 'type', 'script' );
		if ( $type === 'icon' || $type === '1x1' ) {
			header( 'Content-Type: image/png' );
			header( "X-CentralAuth-Status: $status" );

			global $wgCentralAuthLoginIcon;
			if ( $ok && $wgCentralAuthLoginIcon && $type === 'icon' ) {
				readfile( $wgCentralAuthLoginIcon );
			} else {
				readfile( __DIR__ . '/../1x1.png' );
			}
		} else {
			header( 'Content-Type: text/javascript' );
			echo "/* $status */\n$script";
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

		if ( !$this->getUser()->isAnon() ) {
			$this->doFinalOutput( true, 'Already logged in, nothing to do' );
			return false;
		}

		return true;
	}

	private function getCentralSession( $centralUser ) {
		$centralSession = $centralUser->getSession();

		// If there's no "finalProto", check if one was passed, and otherwise
		// assume the current.
		if ( !isset( $centralSession['finalProto'] ) ) {
			$request = $this->getRequest();
			$centralSession['finalProto'] = $request->getVal( 'proto', $request->detectProtocol() );
		}

		// If there's no "remember", pull from the user preference.
		if ( !isset( $centralSession['remember'] ) ) {
			$user = User::newFromName( $centralUser->getName() );
			$centralSession['remember'] = $user->getBoolOption( 'rememberpassword' );
		}

		// Make sure there's a session id by creating a session if necessary.
		if ( !isset( $centralSession['sessionId'] ) ) {
			$centralSession['sessionId'] = $centralUser->setSession( $centralSession );
		}

		return $centralSession;
	}
}
