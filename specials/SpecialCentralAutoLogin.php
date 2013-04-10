<?php

/**
 * Unlisted Special page to set requisite cookies for being logged into this wiki.
 *
 * @ingroup Extensions
 */
class SpecialCentralAutoLogin extends UnlistedSpecialPage {
	private $isForm = false;

	function __construct() {
		parent::__construct( 'CentralAutoLogin' );
	}

	function execute( $par ) {
		global $wgMemc, $wgUser;

		switch ( strval( $par ) ) {
		case '': // also null and false
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-desc' );
			return;

		case 'P3P': // Explain the bogus P3P header
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-p3p-explanation' );
			return;

		case 'L0': // Output form for C1
			$this->isForm = 1;
			if ( !$wgUser->isAnon() ) {
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-alreadyloggedinlocally' ),
				);
				break;
			}
			$data = array(
				'status' => 'ok',
				'nextState' => 'C1',
				'params' => array(
					'wikiid' => wfWikiID(),
				),
			);
			break;

		case 'C1': // Query gu_id
			$data = $this->checkInputState( $par, true );
			if ( $data ) {
				break;
			}

			global $wgUser;
			$centralUser = CentralAuthUser::getInstance( $wgUser );
			$data = array(
				'status' => 'ok',
				'nextState' => 'L1',
				'params' => array(
					'gu_id' => $centralUser ? $centralUser->getId() : 0,
				)
			);
			break;

		case 'L1': // Start session for gu_id
			$data = $this->checkInputState( $par, false );
			if ( $data ) {
				break;
			}

			$gu_id = +$this->getRequest()->getVal( 'gu_id', 0 );
			if ( $gu_id <= 0 ) {
				// Should only get here for iframe mode
				$script = "var t = new Date();\n" .
					"t.setTime( t.getTime() + 86400000 );\n" .
					"if ( 'localStorage' in window ) {\n" .
					"\tlocalStorage.setItem( 'CentralAuthAnon', t.getTime() );\n" .
					"} else {\n" .
					"\tdocument.cookie = 'CentralAuthAnon=1; expires=' + t.toGMTString() + '; path=/';\n" .
					"}\n";
				$data = array(
					'status' => 'script',
					'script' => $script,
				);
				break;
			}

			$script = "if ( 'localStorage' in window ) {\n" .
				"\tlocalStorage.removeItem( 'CentralAuthAnon' );\n" .
				"}\n" .
				"if ( /(^|; )CentralAuthAnon=/.test( document.cookie ) ) {\n" .
				"\tdocument.cookie = 'CentralAuthAnon=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';\n" .
				"}\n";

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
			do {
				$token = MWCryptRand::generateHex( 32 );
				$key = CentralAuthUser::memcKey( 'centralautologin-token', $token, $wikiid );
			} while ( !$wgMemc->add( $key, $memcData, 10 ) );

			// Save memc token for L2
			$this->getRequest()->setSessionData( 'centralautologin-token', $token );

			$data = array(
				'status' => 'ok',
				'nextState' => 'C2',
				'script' => $script,
				'params' => array(
					'token' => $token,
					'wikiid' => wfWikiID(),
				)
			);
			break;

		case 'C2': // Complete session for memc token
			$data = $this->checkInputState( $par, true );
			if ( $data ) {
				break;
			}

			// Validate params
			$wikiid = $this->getRequest()->getVal( 'wikiid', '' );
			$token = $this->getRequest()->getVal( 'token', '' );
			if ( $token === '' || $wikiid === '' ) {
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badparams' )
				);
				break;
			}

			// Load memc data
			$key = CentralAuthUser::memcKey( 'centralautologin-token', $token, $wikiid );
			$memcData = $wgMemc->get( $key );
			$wgMemc->delete( $key );

			// Check memc data
			global $wgUser;
			$centralUser = CentralAuthUser::getInstance( $wgUser );
			if ( !$memcData ||
				$memcData['wikiid'] !== $wikiid ||
				!$centralUser ||
				!$centralUser->getId() ||
				$memcData['gu_id'] != $centralUser->getId()
			) {
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badparams' )
				);
				break;
			}

			// Write info for session creation into memc
			$memcData += array(
				'userName' => $centralUser->getName(),
				'token' => $centralUser->getAuthToken(),
			);
			$wgMemc->set( $key, $memcData, 10 );

			$data = array(
				'status' => 'ok',
				'nextState' => 'L2',
				'params' => array(),
			);
			break;

		case 'L2': // Set cookies for session in memc
			$data = $this->checkInputState( $par, false );
			if ( $data ) {
				break;
			}

			// Check saved memc token
			$token = $this->getRequest()->getSessionData( 'centralautologin-token' );
			if ( $token === null ) {
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-lostsession' ),
				);
				break;
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
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-lostsession' ),
				);
				break;
			}

			// Load and check CentralAuthUser
			$centralUser = new CentralAuthUser( $memcData['userName'] );
			if ( !$centralUser->getId() || $centralUser->getId() != $memcData['gu_id'] ) {
				$msg = "Wrong user: expected {$memcData['gu_id']}, got {$centralUser->getId()}";
				wfDebug( __METHOD__ . ": $msg\n" );
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-lostsession' ),
				);
				break;
			}
			$loginResult = $centralUser->authenticateWithToken( $memcData['token'] );
			if ( $loginResult != 'ok' ) {
				$msg = "Bad token: $loginResult";
				wfDebug( __METHOD__ . ": $msg\n" );
				$data = array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-lostsession' ),
				);
				break;
			}

			// Set a new session cookie, Just In Case™
			// If either we don't trust PHP's entropy, or if we need to change
			// cookie settings when logging in, then change the session ID
			// manually.
			global $wgCookieSecure;
			$cookieParams = session_get_cookie_params();
			if ( wfCheckEntropy() && $wgCookieSecure == $cookieParams['secure'] ) {
				session_regenerate_id( false );
			} else {
				$tmp = $_SESSION;
				session_destroy();
				wfSetupSession( MWCryptRand::generateHex( 32 ) );
				$_SESSION = $tmp;
			}

			// Set central cookies too
			$centralUser->setGlobalCookies( false );

			$data = array(
				'status' => 'ok',
				'params' => array(),
			);

			switch ( $this->getRequest()->getVal( 'oncomplete', 'mw.notify' ) ) {
			case 'mw.notify':
				$user = User::newFromName( $centralUser->getName() );
				$gender = $user->getOption( 'gender' );
				if ( strval( $gender ) === '' ) {
					$gender = 'unknown';
				}
				$data['script'] = Xml::encodeJsCall( 'top.mw.notify', array(
					new XmlJsCode( trim( Xml::encodeJsCall( 'top.mw.message', array(
						'centralauth-centralautologin-logged-in',
						$user->getName(),
						$gender
					) ), ';' ) ),
					array(
						'title' => wfMessage( 'centralautologin' )->plain(),
						'autoHide' => false,
						'tag' => 'CentralAutoLogin',
					),
				) );
				$data['params']['userName'] = $user->getName();
				$data['params']['userGender'] = $gender;
				break;

			case 'status':
				$request = $this->getRequest();
				$url = Title::newFromText( 'Special:CentralLogin/Status' )->getFullUrl( array(
					'returnto' => $request->getVal( 'returnto' ),
					'returntoquery' => $request->getVal( 'returntoquery' ),
				) );
				$data['script'] = 'top.location.href = ' . Xml::encodeJsVar( $url ) . ';';
				break;
			}
			break;

		default:
			$data = array(
				'status' => 'error',
				'msg' => array( 'centralauth-centralautologin-badstate', $par ),
			);
			break;
		}

		$this->outputData( $data );
	}

	private function checkInputState( $par, $central ) {
		global $wgCentralAuthLoginWiki;

		$request = $this->getRequest();
		$this->isForm = $request->getBool( 'form' );

		// Make sure it was posted.
		if ( !$request->wasPosted() && $request->getMethod() !== 'OPTIONS' ) {
			return array(
				'status' => 'error',
				'msg' => array( 'centralauth-centralautologin-notposted' ),
			);
		}

		// Validate the state for this wiki
		if ( $central ) {
			if ( wfWikiID() !== $wgCentralAuthLoginWiki ) {
				return array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badstate-central', $par ),
				);
			}

			$wikiId = $request->getVal( 'wikiid' );
			if ( $wikiId === $wgCentralAuthLoginWiki ) {
				return array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badwiki', $wikiId ),
				);
			}
			$wiki = WikiMap::getWiki( $wikiId );
			if ( !$wiki ) {
				return array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badwiki', $wikiId ),
				);
			}

			// CORS request, validate origin and set CORS headers
			if ( !$this->isForm ) {
				$response = $request->response();

				$originHeader = $request->getHeader( 'Origin' );
				if ( $originHeader === false ) {
					$origins = array();
				} else {
					$origins = explode( ' ', $originHeader );
				}

				$wikiOrigin = 'http://' . strtolower( $wiki->getHostname() );
				$ok = false;
				foreach ( $origins as $origin ) {
					if ( $wikiOrigin === str_replace( 'https://', 'http://', strtolower( $origin ) ) ) {
						$wikiOrigin = $origin;
						$ok = true;
						break;
					}
				}
				if ( !$ok ) {
					$message = HttpStatus::getMessage( 403 );
					$response->header( "HTTP/1.1 403 $message", true, 403 );
					return array(
						'status' => 'error',
						'msg' => array( 'centralauth-centralautologin-corsfail' )
					);
				}

				$response->header( "Access-Control-Allow-Origin: $wikiOrigin" );
				$response->header( 'Access-Control-Allow-Credentials: true' );
				$this->getOutput()->addVaryHeader( 'Origin' );
			}
		} else {
			if ( wfWikiID() === $wgCentralAuthLoginWiki ) {
				return array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-badstate-local', $par ),
				);
			}

			global $wgUser;
			if ( !$wgUser->isAnon() ) {
				return array(
					'status' => 'error',
					'msg' => array( 'centralauth-centralautologin-alreadyloggedinlocally' ),
				);
			}
		}

		if ( $request->getMethod() === 'OPTIONS' ) {
			return array(
				'status' => 'cors'
			);
		}

		return null;
	}

	private function outputData( $data ) {
		global $wgMimeType, $wgLanguageCode;

		$output = $this->getOutput();
		$output->enableClientCache( false );
		$output->sendCacheControl();
		$output->disable();
		$response = $this->getRequest()->response();

		if ( !$this->isForm ) {
			$frameOptions = $output->getFrameOptions();
			if ( $frameOptions ) {
				$response->header( "X-Frame-Options: $frameOptions" );
			}
		}

		if ( $this->isForm ) {
			$script='';
			$bodyParams = array();
			$body='';
			switch ( $data['status'] ) {
			case 'script':
				$script = $data['script'];
				break;

			case 'ok':
				if ( isset( $data['script'] ) ) {
					$script = $data['script'];
				}

				if ( isset( $data['nextState'] ) ) {
					$script .= "\n\nfunction doSubmit() {\n" .
						"\tif ( document.forms[0] ) {\n" .
						"\t\tdocument.forms[0].submit();\n" .
						"\t}\n" .
						"}";
					$bodyParams['onload'] = 'doSubmit()';

					switch ( substr( $data['nextState'], 0, 1 ) ) {
					case 'C':
						global $wgCentralAuthLoginWiki;
						$target = $wgCentralAuthLoginWiki;

					default:
						$target = $this->getRequest()->getVal( 'wikiid', wfWikiID() );
						break;
					}
					$body .= "\n" . Html::openElement( 'form', array(
						'method' => 'POST',
						'action' => WikiMap::getForeignURL( $target, 'Special:CentralAutoLogin/' . $data['nextState'] ),
					) ) . "\n";
					$body .= Html::hidden( 'form', '1' ) . "\n";
					foreach ( array( 'oncomplete', 'returnto', 'returntoquery' ) as $k ) {
						$v = $this->getRequest()->getVal( $k );
						if ( $v !== null ) {
							$body .= Html::hidden( $k, $v ) . "\n";
						}
					}
					foreach ( $data['params'] as $k => $v ) {
						$body .= Html::hidden( $k, $v ) . "\n";
					}
					$body .= Html::closeElement( 'form' );
				}
				break;

			case 'error':
				$params = $data['msg'];
				$key = array_shift( $params );
				$body =  wfMessage( $key, $params )->escaped();
				break;
			}

			$response->header( "Content-type: $wgMimeType; charset=UTF-8" );
			$response->header( 'Content-language: ' . $wgLanguageCode );
			print Html::htmlHeader();
			print Html::openElement( 'head' );
			print Html::element( 'title', null, $output->getHTMLTitle() );
			if ( $script !== '' ) {
				print Html::inlineScript( $script );
			}
			print Html::closeElement( 'head' );
			print Html::openElement( 'body', $bodyParams );
			print $body;
			print Html::closeElement( 'body' );
			print Html::closeElement( 'html' );
		} else {
			$response->header( "Content-type: application/json; charset=UTF-8" );
			$response->header( 'Content-language: ' . $wgLanguageCode );
			switch ( $data['status'] ) {
			case 'cors':
				break;

			case 'ok':
				$data['params']['status'] = 'ok';
				print FormatJson::encode( $data['params'] );
				break;

			default:
				print FormatJson::encode( $data );
				break;
			}
		}
	}
}
