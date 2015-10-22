<?php

use MediaWiki\Session\SessionManager;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\UserInfo;
use MediaWiki\Session\Session;

/**
 * CentralAuth cookie-based sessions.
 *
 * This is intended to completely replace the core CookieSessionProvider.
 *
 * @warning Due to the complicated way CentralAuth has historically interacted with core
 *  sessions, this is somewhat complicated and is probably not a good example to
 *  copy if you're writing your own SessionProvider.
 */
class CentralAuthSessionProvider extends MediaWiki\Session\CookieSessionProvider {

	protected $enable = false;

	protected $centralCookieOptions = array();

	/**
	 * @param array $params In addition to the parameters for
	 * \\MediaWiki\\Session\\CookieSessionProvider, the following are
	 * recognized:
	 *  - enable: Whether to set CentralAuth-specific features. Defaults to
	 *    $wgCentralAuthCookies.
	 *  - centralSessionName: Session cookie name. Defaults to
	 *    centralCookieOptions['prefix'] . 'Session'.
	 *  - centralCookieOptions: Settings for central cookies
	 *     - prefix: Cookie prefix, defaults to $wgCentralAuthCookiePrefix
	 *     - path: Cookie path, defaults to $wgCentralAuthCookiePath
	 *     - domain: Cookie domain, defaults to $wgCentralAuthCookieDomain
	 *     - secure: Cookie secure flag, defaults to $wgCookieSecure
	 *     - httpOnly: Cookie httpOnly flag, defaults to $wgCookieHttpOnly
	 */
	public function __construct( $params = array() ) {
		$params += array(
			'centralCookieOptions' => array(),
		);

		if ( !is_array( $params['centralCookieOptions'] ) ) {
			throw new \InvalidArgumentException( __METHOD__ . ': centralCookieOptions must be an array' );
		}

		$this->centralCookieOptions = $params['centralCookieOptions'];
		unset( $params['centralCookieOptions'] );

		parent::__construct( $params );
	}

	public function setConfig( Config $config ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix, $wgCentralAuthCookiePath,
			$wgCentralAuthCookieDomain;

		parent::setConfig( $config );

		$this->centralCookieOptions += array(
			'prefix' => $wgCentralAuthCookiePrefix,
			'path' => $wgCentralAuthCookiePath,
			'domain' => $wgCentralAuthCookieDomain,
			'secure' => $config->get( 'CookieSecure' ),
			'httpOnly' => $config->get( 'CookieHttpOnly' ),
		);

		$this->params += array(
			'enable' => $wgCentralAuthCookies,
			'centralSessionName' => $this->centralCookieOptions['prefix'] . 'Session',
		);

		$this->enable = (bool)$this->params['enable'];
	}

	public function provideSessionInfo( WebRequest $request ) {
		if ( !$this->enable ) {
			$this->logger->debug( __METHOD__ . ': Not enabled, falling back to core sessions' );
			return parent::provideSessionInfo( $request );
		}

		$info = array(
			'id' => $request->getCookie( $this->params['sessionName'], '' )
		);
		if ( !SessionManager::validateSessionId( $info['id'] ) ) {
			unset( $info['id'] );
		}

		$userName = null;
		$token = null;

		$prefix = $this->centralCookieOptions['prefix'];
		$userCookie = $request->getCookie( 'User', $prefix );
		$tokenCookie = $request->getCookie( 'Token', $prefix );
		if ( $userCookie !== null && $tokenCookie !== null ) {
			$userName = $userCookie;
			$token = $tokenCookie;
		} else {
			$id = $request->getCookie( $this->params['centralSessionName'], '' );
			if ( $id !== null ) {
				$data = CentralAuthUtils::getCentralSessionById( $id );
				if ( isset( $data['pending_name'] ) || isset( $data['pending_guid'] ) ) {
					$this->logger->debug( __METHOD__ . ': unintialized session' );
				} elseif ( isset( $data['token'] ) && isset( $data['user'] ) ) {
					$token = $data['token'];
					$userName = $data['user'];
				} else {
					$this->logger->debug( __METHOD__ . ': unintialized session' );
				}
			}
		}
		if ( $userName === null || $token === null ) {
			return parent::provideSessionInfo( $request );
		}

		// Sanity check to avoid session ID collisions, as reported on bug 19158
		if ( $userCookie === null ) {
			$this->logger->debug(
				__METHOD__ . ': no User cookie, so unable to check for session mismatch'
			);
			return parent::provideSessionInfo( $request );
		} elseif ( $userCookie != $userName ) {
			$this->logger->debug(
				__METHOD__ . ': Session ID/User mismatch. Possible session collision. ' .
					"Expected: $userName; actual: $userCookie"
			);
			return parent::provideSessionInfo( $request );
		}

		// Clean up username
		$userName = User::getCanonicalName( $userName, 'valid' );
		if ( !$userName ) {
			$this->logger->debug( __METHOD__ . ': invalid username' );
			return parent::provideSessionInfo( $request );
		}

		// Try the central user
		// Don't use CentralAuthUser::getInstance, we don't want to cache it on failure.
		$centralUser = new CentralAuthUser( $userName );

		// Skip if they're being renamed
		if ( $centralUser->renameInProgress() ) {
			$this->logger->debug( __METHOD__ . ': rename in progress' );
			// No fallback here, just fail it because our SessionCheckMetadata
			// hook will do so anyway.
			return null;
		}

		if ( !$centralUser->exists() ) {
			$this->logger->debug( __METHOD__ . ': global account doesn\'t exist' );
			return parent::provideSessionInfo( $request );
		}
		if ( !$centralUser->isAttached() && User::idFromName( $userName ) ) {
			$this->logger->debug( __METHOD__ . ': not attached and local account exists' );
			return parent::provideSessionInfo( $request );
		}
		if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
			$this->logger->debug( __METHOD__ . ': token mismatch' );
			return parent::provideSessionInfo( $request );
		}

		$this->logger->debug( __METHOD__ . ': logged in from session' );

		$info += array(
			'user' => UserInfo::newFromName( $userName, true ),
			'provider' => $this,
			'persisted' => isset( $info['id'] ),
			'forceHTTPS' => $request->getCookie( 'forceHTTPS', '', false )
		);

		return new SessionInfo( $this->priority, $info );
	}

	public function sessionIdWasReset( MediaWiki\Session\SessionBackend $session, $oldId ) {
		if ( !$this->enable ) {
			return;
		}

		$s = $session->getSession( new FauxRequest() );
		$data = CentralAuthUtils::getCentralSession( $s );
		CentralAuthUtils::setCentralSession( $data, true, $s );
	}

	protected function sessionDataToExport( $user ) {
		$data = parent::sessionDataToExport( $user );
		unset( $data['wsToken'] );
		return $data;
	}

	public function persistSession( MediaWiki\Session\SessionBackend $session, WebRequest $request ) {
		parent::persistSession( $session, $request );

		if ( !$this->enable ) {
			return;
		}

		$response = $request->response();
		if ( $response->headersSent() ) {
			// Can't do anything now
			return;
		}

		$s = $session->getSession( $request );

		$centralUser = CentralAuthUser::getInstance( $session->getUser() );
		if ( $centralUser->isAttached() ) {
			// Not sure why, but apparently CentralAuth wants to prevent core
			// login-from-session.
			$data = &$session->getData();
			if ( array_key_exists( 'wsToken', $data ) ) {
				unset( $data['wsToken'] );
				$session->dirty();
			}
			unset( $data );

			$remember = $session->shouldRememberUser();

			$options = $this->centralCookieOptions;
			if ( $session->shouldForceHTTPS() || $session->getUser()->requiresHTTPS() ) {
				$options['secure'] = true;
			}

			// We only save the user into the central session if it's not a
			// "pending" session, but we still need the ID to set the cookie.
			$data = CentralAuthUtils::getCentralSession( $s );
			if ( isset( $data['pending_name'] ) ) {
				$remember = false;
			} else {
				$data['user'] = $centralUser->getName();
				$data['token'] = $centralUser->getAuthToken();
				$data['expiry'] = time() + 86400;
			}
			$centralSessionId = CentralAuthUtils::setCentralSession( $data, false, $s );

			$extendedCookies = $this->config->get( 'ExtendedLoginCookies' );
			$extendedExpiry = $this->config->get( 'ExtendedLoginCookieExpiration' );
			if ( $extendedExpiry !== null && in_array( $key, $extendedCookies ) ) {
				$expiry = time() + (int)$extendedExpiry;
			} else {
				$expiry = 0;
			}

			$response->setCookie( 'User', (string)$centralUser->getName(), $expiry, $options );
			if ( $remember ) {
				$response->setCookie( 'Token', (string)$centralUser->getAuthToken(), $expiry, $options );
			} else {
				$response->setCookie( 'Token', '', time() - 86400, $options );
			}
			$response->setCookie( $this->params['centralSessionName'], $centralSessionId, null,
				array( 'prefix' => '' ) + $options );
		} else {
			CentralAuthUtils::deleteCentralSession( $s );

			$expiry = time() - 86400;
			$response->setCookie( 'User', '', $expiry, $this->centralCookieOptions );
			$response->setCookie( 'Token', '', $expiry, $this->centralCookieOptions );
			$response->setCookie( $this->params['centralSessionName'], '', $expiry,
				array( 'prefix' => '' ) + $this->centralCookieOptions );
		}

		if ( $session->shouldForceHTTPS() || $session->getUser()->requiresHTTPS() ) {
			// Delete the core cookie and set our own
			$response->setCookie( 'forceHTTPS', '', time() - 86400,
				array( 'prefix' => '', 'secure' => false ) + $this->cookieOptions );
			$response->setCookie( 'forceHTTPS', 'true', $session->shouldRememberUser() ? 0 : null,
				array( 'prefix' => '', 'secure' => false ) + $this->centralCookieOptions );
		} else {
			// Bug 54626: Explcitly clear forceHTTPS cookie when it's not wanted
			$response->setCookie( 'forceHTTPS', '', time() - 86400,
				array( 'prefix' => '', 'secure' => false ) + $this->centralCookieOptions );
		}

	}

	public function unpersistSession( WebRequest $request ) {
		parent::unpersistSession( $request );

		if ( !$this->enable ) {
			return;
		}

		$response = $request->response();
		if ( $response->headersSent() ) {
			// Can't do anything now
			$this->logger->debug( __METHOD__ . ': Headers already sent' );
			return;
		}

		$expiry = time() - 86400;
		$response->setCookie( 'User', '', $expiry, $this->centralCookieOptions );
		$response->setCookie( 'Token', '', $expiry, $this->centralCookieOptions );
		$response->setCookie( $this->params['centralSessionName'], '', $expiry,
			array( 'prefix' => '' ) + $this->centralCookieOptions );
		$response->setCookie( 'forceHTTPS', '', $expiry,
			array( 'prefix' => '', 'secure' => false ) + $this->centralCookieOptions );
	}

	protected function setLoggedOutCookie( $loggedOut, WebRequest $request ) {
		if ( $loggedOut + 86400 > time() &&
			$loggedOut !== (int)$request->getCookie( 'LoggedOut', $this->centralCookieOptions['prefix'] )
		) {
			$request->response()->setCookie( 'LoggedOut', $loggedOut, $loggedOut + 86400,
				$this->centralCookieOptions );
		}
	}

	public function getVaryCookies() {
		$cookies = parent::getVaryCookies();

		if ( $this->enable ) {
			$prefix = $this->centralCookieOptions['prefix'];
			$cookies[] = $prefix . 'Token';
			$cookies[] = $prefix . 'LoggedOut';
			$cookies[] = $this->params['centralSessionName'];
		}

		return $cookies;
	}

	public function suggestLoginUsername( WebRequest $request ) {
		 $name = $request->getCookie( 'User', $this->centralCookieOptions['prefix'] );
		 if ( $name === null ) {
			 $name = User::getCanonicalName( $name, 'usable' );
		 }
		 return $name === false ? parent::suggestLoginUsername( $request ) : $name;
	}

	/**
	 * Fetch the central cookie domain
	 * @return string
	 */
	public function getCentralCookieDomain() {
		return $this->centralCookieOptions['domain'];
	}

}
