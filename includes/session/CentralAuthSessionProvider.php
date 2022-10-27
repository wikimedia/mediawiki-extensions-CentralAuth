<?php

use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Session\SessionBackend;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\UserInfo;
use Mediawiki\User\UserNameUtils;

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

	/** @var bool */
	protected $enable = false;

	/** @var array */
	protected $centralCookieOptions = [];

	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/**
	 * @param CentralAuthSessionManager $sessionManager
	 * @param CentralAuthUtilityService $utilityService
	 * @param array $params In addition to the parameters for
	 * CookieSessionProvider, the following are
	 * recognized:
	 *  - enable: Whether to set CentralAuth-specific features. Defaults to
	 *    $wgCentralAuthCookies.
	 *  - centralSessionName: Central session cookie name. Defaults to
	 *    centralCookieOptions['prefix'] . 'Session'. Note this does not
	 *    replace the parent class's 'sessionName', it's a different cookie.
	 *  - centralCookieOptions: Settings for central cookies
	 *     - prefix: Cookie prefix, defaults to $wgCentralAuthCookiePrefix
	 *     - path: Cookie path, defaults to $wgCentralAuthCookiePath
	 *     - domain: Cookie domain, defaults to $wgCentralAuthCookieDomain
	 *     - secure: Cookie secure flag, defaults to $wgCookieSecure
	 *     - httpOnly: Cookie httpOnly flag, defaults to $wgCookieHttpOnly
	 *     - sameSite: Cookie SameSite attribute, defaults to $wgCookieSameSite
	 */
	public function __construct(
		CentralAuthSessionManager $sessionManager,
		CentralAuthUtilityService $utilityService,
		$params = []
	) {
		$this->sessionManager = $sessionManager;
		$this->utilityService = $utilityService;

		$params += [
			'centralCookieOptions' => [],
		];

		if ( !is_array( $params['centralCookieOptions'] ) ) {
			throw new \InvalidArgumentException(
				__METHOD__ . ': centralCookieOptions must be an array'
			);
		}

		$this->centralCookieOptions = $params['centralCookieOptions'];
		unset( $params['centralCookieOptions'] );

		parent::__construct( $params );
	}

	protected function postInitSetup() {
		parent::postInitSetup();

		$this->centralCookieOptions += [
			'prefix' => $this->getConfig()->get( 'CentralAuthCookiePrefix' ),
			'path' => $this->getConfig()->get( 'CentralAuthCookiePath' ),
			'domain' => $this->getConfig()->get( 'CentralAuthCookieDomain' ),
			'secure' => $this->getConfig()->get( 'CookieSecure' ) || $this->getConfig()->get( 'ForceHTTPS' ),
			'httpOnly' => $this->getConfig()->get( 'CookieHttpOnly' ),
			'sameSite' => $this->getConfig()->get( 'CookieSameSite' )
		];

		$params = [
			'enable' => $this->getConfig()->get( 'CentralAuthCookies' ),
			'centralSessionName' => $this->centralCookieOptions['prefix'] . 'Session',
		];
		$this->params += $params;

		$this->enable = (bool)$params['enable'];
	}

	/**
	 * @param WebRequest $request
	 * @return SessionInfo|null
	 */
	private function returnParentSessionInfo( WebRequest $request ) {
		$info = parent::provideSessionInfo( $request );
		if ( $info ) {
			return new SessionInfo( $info->getPriority(), [
				'copyFrom' => $info,
				'metadata' => [
					'CentralAuthSource' => 'Local',
				],
			] );
		} else {
			return null;
		}
	}

	/**
	 * @param WebRequest $request
	 * @return SessionInfo|null
	 */
	public function provideSessionInfo( WebRequest $request ) {
		if ( !$this->enable ) {
			$this->logger->debug( __METHOD__ . ': Not enabled, falling back to core sessions' );
			return self::returnParentSessionInfo( $request );
		}

		$info = [
			'id' => $this->getCookie( $request, $this->params['sessionName'], '' )
		];
		if ( !SessionManager::validateSessionId( $info['id'] ) ) {
			unset( $info['id'] );
		}

		$userName = null;
		$token = null;
		$from = null;

		$prefix = $this->centralCookieOptions['prefix'];
		$userCookie = $this->getCookie( $request, 'User', $prefix );
		$tokenCookie = $this->getCookie( $request, 'Token', $prefix );
		if ( $userCookie !== null && $tokenCookie !== null ) {
			$userName = $userCookie;
			$token = $tokenCookie;
			$from = 'cookies';
		} else {
			$id = $this->getCookie( $request, $this->params['centralSessionName'], '' );
			if ( $id !== null ) {
				$data = $this->sessionManager->getCentralSessionById( $id );
				if ( isset( $data['pending_name'] ) || isset( $data['pending_guid'] ) ) {
					$this->logger->debug( __METHOD__ . ': uninitialized session' );
				} elseif ( isset( $data['token'] ) && isset( $data['user'] ) ) {
					$token = $data['token'];
					$userName = $data['user'];
					$from = 'session';
				} else {
					$this->logger->debug( __METHOD__ . ': uninitialized session' );
				}
			}
		}
		if ( $userName === null || $token === null ) {
			return self::returnParentSessionInfo( $request );
		}

		// Sanity check to avoid session ID collisions, as reported on T21158
		if ( $userCookie === null ) {
			$this->logger->debug(
				__METHOD__ . ': no User cookie, so unable to check for session mismatch'
			);
			return self::returnParentSessionInfo( $request );
		} elseif ( $userCookie != $userName ) {
			$this->logger->debug(
				__METHOD__ . ': Session ID/User mismatch. Possible session collision. ' .
					"Expected: $userName; actual: $userCookie"
			);
			return self::returnParentSessionInfo( $request );
		}

		// Clean up username
		$userName = $this->userNameUtils->getCanonical( $userName, UserNameUtils::RIGOR_VALID );
		if ( !$userName ) {
			$this->logger->debug( __METHOD__ . ': invalid username' );
			return self::returnParentSessionInfo( $request );
		}
		if ( !$this->userNameUtils->isUsable( $userName ) ) {
			$this->logger->warning(
				__METHOD__ . ': username {username} is not usable on this wiki', [
					'username' => $userName,
				]
			);
			return self::returnParentSessionInfo( $request );
		}

		// Try the central user
		$centralUser = CentralAuthUser::getInstanceByName( $userName );

		// Skip if they're being renamed
		if ( $centralUser->renameInProgress() ) {
			$this->logger->debug( __METHOD__ . ': rename in progress' );
			// No fallback here, just fail it because our SessionCheckMetadata
			// hook will do so anyway.
			return null;
		}

		if ( !$centralUser->exists() ) {
			$this->logger->debug( __METHOD__ . ': global account doesn\'t exist' );
			return self::returnParentSessionInfo( $request );
		}
		if ( !$centralUser->isAttached() && User::idFromName( $userName ) ) {
			$this->logger->debug( __METHOD__ . ': not attached and local account exists' );
			return self::returnParentSessionInfo( $request );
		}
		if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
			$this->logger->debug( __METHOD__ . ': token mismatch' );
			// At this point, don't log in with a local session anymore
			return null;
		}

		$this->logger->debug( __METHOD__ . ": logged in from $from" );

		$info += [
			'userInfo' => UserInfo::newFromName( $userName, true ),
			'provider' => $this,
			'persisted' => true, // CA sessions are always persistent
			'remembered' => $tokenCookie !== null,
			'forceHTTPS' => $this->getCookie( $request, 'forceHTTPS', '', false ),
			'metadata' => [
				'CentralAuthSource' => 'CentralAuth',
			],
		];

		return new SessionInfo( $this->priority, $info );
	}

	public function refreshSessionInfo( SessionInfo $info, WebRequest $request, &$metadata ) {
		// Sanity check on the metadata, to avoid T124409
		if ( isset( $metadata['CentralAuthSource'] ) ) {
			$name = $info->getUserInfo()->getName();
			if ( $name !== null ) {
				if ( !$this->enable ) {
					$source = 'Local';
				} else {
					$centralUser = CentralAuthUser::getInstanceByName( $name );
					if ( $centralUser->exists() &&
						( $centralUser->isAttached() ||
							!User::idFromName( $name, User::READ_LATEST ) )
					) {
						$source = 'CentralAuth';
					} else {
						$source = 'Local';
					}
				}
				if ( $metadata['CentralAuthSource'] !== $source ) {
					$this->logger->warning(
						'Session "{session}": CentralAuth saved source {saved} ' .
							'!= expected source {expected}',
						[
							'session' => $info->__toString(),
							'saved' => $metadata['CentralAuthSource'],
							'expected' => $source,
						]
					);
					return false;
				}
			}
		}

		return true;
	}

	public function sessionIdWasReset( SessionBackend $session, $oldId ) {
		if ( !$this->enable ) {
			return;
		}

		// We need a Session to pass to CentralAuthSessionManager::setCentralSession()
		// to reset the session ID, so create one on a new FauxRequest.
		$s = $session->getSession( new FauxRequest() );

		// We also need to fetch the current central data to pass to
		// CentralAuthSessionManager::setCentralSession() when resetting the ID.
		$data = $this->sessionManager->getCentralSession( $s );

		$this->sessionManager->setCentralSession( $data, true, $s );
	}

	protected function sessionDataToExport( $user ) {
		$data = parent::sessionDataToExport( $user );

		// CentralAuth needs to prevent core login-from-session to
		// avoid bugs like T124409
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->isAttached() ) {
			unset( $data['wsToken'] );
		}

		return $data;
	}

	protected function cookieDataToExport( $user, $remember ) {
		// If we're going to set CA cookies, don't remember in core cookies.
		if ( $remember ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			$remember = !$centralUser->isAttached();
		}

		return parent::cookieDataToExport( $user, $remember );
	}

	/**
	 * @param SessionBackend $session
	 * @param WebRequest $request
	 */
	public function persistSession( SessionBackend $session, WebRequest $request ) {
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

		$user = $session->getUser();
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( $centralUser->exists() && ( $centralUser->isAttached() || !$user->getId() ) ) {
			// CentralAuth needs to prevent core login-from-session to
			// avoid bugs like T124409
			$data = &$session->getData();
			if ( array_key_exists( 'wsToken', $data ) ) {
				unset( $data['wsToken'] );
				$session->dirty();
			}
			unset( $data );

			$metadata = $session->getProviderMetadata();
			$metadata['CentralAuthSource'] = 'CentralAuth';
			$session->setProviderMetadata( $metadata );

			$remember = $session->shouldRememberUser();

			$options = $this->centralCookieOptions;
			if ( $session->shouldForceHTTPS() || $user->requiresHTTPS() ) {
				// Don't set the secure flag if the request came in
				// over "http", for backwards compat.
				// @todo Break that backwards compat properly.
				$options['secure'] = $this->getConfig()->get( 'ForceHTTPS' )
					|| $this->getConfig()->get( 'CookieSecure' );
			}

			// We only save the user into the central session if it's not a
			// "pending" session, but we still need the ID to set the cookie.
			$data = $this->sessionManager->getCentralSession( $s );
			if ( isset( $data['pending_name'] ) ) {
				$remember = false;
			} else {
				$data['user'] = $centralUser->getName();
				$data['token'] = $centralUser->getAuthToken();
			}
			$centralSessionId = $this->sessionManager->setCentralSession( $data, false, $s );

			$cookies = [
				'User' => (string)$centralUser->getName(),
				'Token' => $remember ? (string)$centralUser->getAuthToken() : false,
			];
			foreach ( $cookies as $name => $value ) {
				if ( $value === false ) {
					$response->clearCookie( $name, $options );
				} else {
					$expirationDuration = $this->getLoginCookieExpiration( $name, $remember );
					$expiration = $expirationDuration ? $expirationDuration + time() : null;
					$response->setCookie( $name, (string)$value, $expiration, $options );
				}
			}

			$response->setCookie( $this->params['centralSessionName'], $centralSessionId, null,
				[ 'prefix' => '' ] + $options );
		} else {
			$metadata = $session->getProviderMetadata();
			$metadata['CentralAuthSource'] = 'Local';
			$session->setProviderMetadata( $metadata );

			$response->clearCookie( 'User', $this->centralCookieOptions );
			$response->clearCookie( 'Token', $this->centralCookieOptions );
			$response->clearCookie( $this->params['centralSessionName'],
				[ 'prefix' => '' ] + $this->centralCookieOptions );
		}
	}

	/**
	 * @param WebRequest $request
	 */
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
		$response->clearCookie( 'User', $this->centralCookieOptions );
		$response->clearCookie( 'Token', $this->centralCookieOptions );
		$response->clearCookie( $this->params['centralSessionName'],
			[ 'prefix' => '' ] + $this->centralCookieOptions );
	}

	public function invalidateSessionsForUser( User $user ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( $centralUser->exists() && ( $centralUser->isAttached() || !$user->isRegistered() ) ) {
			$centralUser->resetAuthToken();
		}
	}

	public function preventSessionsForUser( $username ) {
		$username = $this->userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_VALID );
		if ( !$username ) {
			return;
		}

		$centralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
		if ( !$centralUser->exists() ) {
			return;
		}

		// Reset the user's password to something invalid and reset the token,
		// if it's not already invalid.
		$passwordFactory = new PasswordFactory();
		$passwordFactory->init( RequestContext::getMain()->getConfig() );
		try {
			$password = $passwordFactory->newFromCiphertext( $centralUser->getPassword() );
		} catch ( PasswordError $e ) {
			return;
		}
		if ( !$password instanceof InvalidPassword ) {
			$centralUser->setPassword( null, true );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setForceHTTPSCookie( $set, ?SessionBackend $backend, WebRequest $request ) {
		if ( $this->getConfig()->get( 'ForceHTTPS' ) ) {
			// No need to send a cookie if the wiki is always HTTPS (T256095)
			return;
		}
		$response = $request->response();
		$central = $backend
			? CentralAuthUser::getInstance( $backend->getUser() )->isAttached()
			: false;
		$sameCookie = (
			$this->cookieOptions['path'] === $this->centralCookieOptions['path'] &&
			$this->cookieOptions['domain'] === $this->centralCookieOptions['domain']
		);

		// If the account is centralized, have the parent clear its cookie and
		// set the central cookie. If it's not centralized, clear the central
		// cookie and have the parent set its cookie as it usually would.
		if ( $set && $central ) {
			if ( !$sameCookie ) {
				parent::setForceHTTPSCookie( false, $backend, $request );
			}

			$shouldRemember = $backend->shouldRememberUser();
			if ( $shouldRemember ) {
				$expirationDuration = $this->getLoginCookieExpiration(
					'forceHTTPS',
					/* $shouldRememberUser */ true
				);
				$expiration = $expirationDuration ? $expirationDuration + time() : null;
			} else {
				$expiration = null;
			}
			$response->setCookie( 'forceHTTPS', 'true', $expiration,
				[ 'prefix' => '', 'secure' => false ] + $this->centralCookieOptions );
		} else {
			if ( !$sameCookie ) {
				$response->clearCookie( 'forceHTTPS',
					[ 'prefix' => '', 'secure' => false ] + $this->centralCookieOptions );
			}
			parent::setForceHTTPSCookie( $set, $backend, $request );
		}
	}

	protected function setLoggedOutCookie( $loggedOut, WebRequest $request ) {
		if ( $loggedOut + 86400 > time() &&
			$loggedOut !== (int)$this->getCookie(
				$request, 'LoggedOut', $this->centralCookieOptions['prefix'] )
		) {
			$request->response()->setCookie( 'LoggedOut', (string)$loggedOut, $loggedOut + 86400,
				$this->centralCookieOptions );
		}
	}

	/**
	 * @return string[]
	 */
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
		$name = $this->getCookie( $request, 'User', $this->centralCookieOptions['prefix'] );
		if ( $name !== null ) {
			if ( $this->userNameUtils->isTemp( $name ) ) {
				$name = false;
			} else {
				$name = $this->userNameUtils->getCanonical( $name, UserNameUtils::RIGOR_USABLE );
			}
		}
		return ( $name === false || $name === null )
			? parent::suggestLoginUsername( $request )
			: $name;
	}

	/**
	 * Fetch the central cookie domain
	 * @return string
	 */
	public function getCentralCookieDomain() {
		return $this->centralCookieOptions['domain'];
	}

	protected function getExtendedLoginCookies() {
		$cookies = parent::getExtendedLoginCookies();
		$cookies[] = 'User';
		return $cookies;
	}

	public function getRememberUserDuration() {
		// CentralAuth needs User and Token cookies to remember the user. The fallback to
		// sessions needs UserID as well, so if that one has shorter expiration, the remember
		// duration will depend on whether the account is attached; let's return the shorter
		// duration in that case.

		return min(
			$this->getLoginCookieExpiration( 'User', /* $shouldRememberUser */ true ),
			$this->getLoginCookieExpiration( 'Token', /* $shouldRememberUser */ true ),
			$this->getLoginCookieExpiration( 'UserID', /* $shouldRememberUser */ true )
		) ?: null;
	}
}
