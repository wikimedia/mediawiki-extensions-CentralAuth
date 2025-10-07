<?php

use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Json\JwtCodec;
use MediaWiki\Json\JwtException;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Password\InvalidPassword;
use MediaWiki\Password\PasswordError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\CookieSessionProvider;
use MediaWiki\Session\SessionBackend;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\UserInfo;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserRigorOptions;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * CentralAuth cookie-based sessions.
 *
 * This is intended to completely replace the core CookieSessionProvider.
 *
 * @warning Due to the complicated way CentralAuth has historically interacted with core
 *  sessions, this is somewhat complicated and is probably not a good example to
 *  copy if you're writing your own SessionProvider.
 */
class CentralAuthSessionProvider extends CookieSessionProvider {

	/** @var bool */
	protected $enable = false;

	/** @var array */
	protected $centralCookieOptions = [];

	/**
	 * @param JwtCodec $jwtCodec
	 * @param TempUserConfig $tempUserConfig
	 * @param UrlUtils $urlUtils
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param CentralAuthSessionManager $sessionManager
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
		JwtCodec $jwtCodec,
		private readonly TempUserConfig $tempUserConfig,
		UrlUtils $urlUtils,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly CentralAuthSessionManager $sessionManager,
		array $params = []
	) {
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

		parent::__construct( $jwtCodec, $urlUtils, $params );
	}

	protected function postInitSetup() {
		parent::postInitSetup();

		$this->centralCookieOptions += [
			'prefix' => $this->getConfig()->get( CAMainConfigNames::CentralAuthCookiePrefix ),
			'path' => $this->getConfig()->get( CAMainConfigNames::CentralAuthCookiePath ),
			'domain' => $this->getConfig()->get( CAMainConfigNames::CentralAuthCookieDomain ),
			'secure' => $this->getConfig()->get( MainConfigNames::CookieSecure ) ||
				$this->getConfig()->get( MainConfigNames::ForceHTTPS ),
			'httpOnly' => $this->getConfig()->get( MainConfigNames::CookieHttpOnly ),
			'sameSite' => $this->getConfig()->get( MainConfigNames::CookieSameSite )
		];

		$params = [
			'enable' => $this->getConfig()->get( CAMainConfigNames::CentralAuthCookies ),
			'centralSessionName' => $this->centralCookieOptions['prefix'] . 'Session',
		];
		$this->params += $params;

		$this->enable = (bool)$params['enable'];
	}

	/**
	 * Get the local session info, with CentralAuthSource metadata.
	 *
	 * @param WebRequest $request
	 * @param bool $forceEmptyPersist If this is true and there is no local
	 *   session, return a persistent empty local session to override the
	 *   non-functional CentralAuth one. This prevents the deletion of global
	 *   cookies, allowing the user to retain their central login elsewhere
	 *   in the same cookie domain. It makes sense to do this for problems with
	 *   the central session that are specific to the local wiki. (T342475)
	 * @return SessionInfo|null
	 */
	private function returnParentSessionInfo( WebRequest $request, $forceEmptyPersist = false ) {
		$info = parent::provideSessionInfo( $request );
		if ( $info ) {
			return new SessionInfo( $info->getPriority(), [
				'copyFrom' => $info,
				'metadata' => [
					'CentralAuthSource' => 'Local',
				],
			] );
		}

		if ( $forceEmptyPersist ) {
			return new SessionInfo( $this->priority, [
				'id' => null,
				'provider' => $this,
				'idIsSafe' => true,
				'persisted' => true,
				'metadata' => [
					'CentralAuthSource' => 'Local',
				],
			] );
		}

		return null;
	}

	/**
	 * Determine whether $request has a valid CentralAuth session.
	 *
	 * The following requests are considered to have a valid CentralAuth session:
	 * - A request with a centralauth_User and centralauth_Token cookie (prefix is configurable)
	 *   that match the data in the globaluser table.
	 * - A request with a centralauth_Session cookie where the referenced data exists in the
	 *   central session store, and the username and token in the central session object match
	 *   the data in the globaluser table.
	 *
	 * The following requests are considered to have a valid local session (marked with
	 * CentralAuthSource => Local in the session metadata):
	 * - A request with a centralauth_Session cookie where the referenced data exists in the
	 *   central session store, but is a stub (has pending_name or pending_guid fields).
	 * - Anything that CookieSessionProvider considers valid, if the session is anonymous or for
	 *   a local user that's not attached to the global CentralAuth user.
	 * - When $wgCentralAuthCookies is disabled, falls back to CookieSessionProvider entirely.
	 *
	 * @return SessionInfo|null
	 */
	public function provideSessionInfo( WebRequest $request ) {
		if ( !$this->enable ) {
			$this->logger->debug( __METHOD__ . ': Not enabled, falling back to core sessions' );
			return $this->returnParentSessionInfo( $request );
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
			// "Keep me logged in" route, can authenticate against gu_auth_token.
			$userName = $userCookie;
			$token = $tokenCookie;
			$from = 'cookies';
		} else {
			$id = $this->getCookie( $request, $this->params['centralSessionName'], '' );
			if ( $id !== null ) {
				$data = $this->sessionManager->getCentralSessionById( $id );
				if ( isset( $data['pending_name'] ) || isset( $data['pending_guid'] ) ) {
					// Stub session, should be treated as an anonymous session. See
					// SpecialCentralLogin::doLoginStart().
					$this->logger->info( __METHOD__ . ': stub session for {username}',
						[ 'username' => $data['pending_name'] ?? '', 'gu_id' => $data['pending_guid'] ?? '' ] );
				} elseif ( isset( $data['token'] ) && isset( $data['user'] ) ) {
					$token = $data['token'];
					$userName = $data['user'];
					$from = 'session';
					$this->logger->debug( __METHOD__ . ': loaded central session for {username}',
						[ 'username' => $userName ] );
				} else {
					// Central session not found in store, probably expired. Since there is no
					// token cookie, treat it as an anonymous session.
					$this->logger->debug( __METHOD__ . ': central session not found' );
				}
			}
		}
		if ( $userName === null || $token === null ) {
			return $this->returnParentSessionInfo( $request );
		}

		// Check to avoid session ID collisions, as reported on T21158
		if ( $userCookie === null ) {
			$this->logger->debug(
				__METHOD__ . ': no User cookie for {username}, so unable to check for session mismatch',
				[ 'username' => $userName ]
			);
			return $this->returnParentSessionInfo( $request );
		}
		if ( $userCookie != $userName ) {
			$this->logger->warning(
				__METHOD__ . ': Session ID and username cookies mismatch. Possible session collision. ' .
					'Username from cookie: {username_cookie}; from session data: {username_session}',
				[ 'username_cookie' => $userCookie, 'username_session' => $userName ]
			);
			return $this->returnParentSessionInfo( $request );
		}

		// Clean up username
		$userName = $this->userNameUtils->getCanonical( $userName );
		if ( !$userName ) {
			$this->logger->info( __METHOD__ . ': invalid username: {username}', [ 'username' => $userName ] );
			return $this->returnParentSessionInfo( $request );
		}
		if ( !$this->userNameUtils->isUsable( $userName ) ) {
			// Log a warning if the username is not usable, except if the name is reserved by the temporary account
			// system to avoid spamming the logs (T373827).
			if ( !$this->tempUserConfig->isReservedName( $userName ) ) {
				$this->logger->warning(
					__METHOD__ . ': username {username} is not usable on this wiki', [
						'username' => $userName,
					]
				);
			}
			return $this->returnParentSessionInfo( $request, true );
		}

		// Try the central user
		$centralUser = CentralAuthUser::getInstanceByName( $userName );

		// Skip if they're being renamed
		if ( $centralUser->renameInProgress() ) {
			$this->logger->info( __METHOD__ . ': rename in progress for {username}', [ 'username' => $userName ] );
			// No fallback here, just fail it because our SessionCheckMetadata
			// hook will do so anyway.
			return null;
		}

		// If the claimed user account is not migrated to CentralAuth, leave handling it to the parent class.
		if ( !$centralUser->exists() ) {
			$this->logger->debug( __METHOD__ . ': global account doesn\'t exist for {username}',
				[ 'username' => $userName ] );
			return $this->returnParentSessionInfo( $request );
		}
		if ( !$centralUser->isAttached() ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $userName );
			if ( $userIdentity && $userIdentity->isRegistered() ) {
				$this->logger->debug( __METHOD__ . ': unattached local account exists for {username}',
					[ 'username' => $userName ] );
				return $this->returnParentSessionInfo( $request, true );
			}
		}

		if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
			$this->logger->warning( __METHOD__ . ': token mismatch for {username}', [ 'username' => $userName ] );
			// At this point, don't log in with a local session anymore
			return null;
		}

		$this->logger->debug( __METHOD__ . ": logged in from $from" );

		$info += [
			'userInfo' => UserInfo::newFromName( $userName, true ),
			'provider' => $this,
			// CA sessions are always persistent
			'persisted' => true,
			'remembered' => $tokenCookie !== null,
			'metadata' => [
				'CentralAuthSource' => 'CentralAuth',
			],
		];
		$sessionInfo = new SessionInfo( $this->priority, $info );

		if ( $this->useJwtCookie() ) {
			try {
				$this->verifyJwtCookie( $request, $sessionInfo );
			} catch ( JwtException $e ) {
				$this->logger->info( 'JWT validation failed: ' . $e->getNormalizedMessage(), $e->getMessageContext() );
				return null;
			}
		}

		return $sessionInfo;
	}

	/** @inheritDoc */
	public function refreshSessionInfo( SessionInfo $info, WebRequest $request, &$metadata ) {
		// Check on the metadata, to avoid T124409
		if ( isset( $metadata['CentralAuthSource'] ) ) {
			$name = $info->getUserInfo()->getName();
			if ( $name === null ) {
				return true;
			}

			$source = 'Local';
			if ( $this->enable ) {
				$centralUser = CentralAuthUser::getInstanceByName( $name );
				$centralUserExists = $centralUser->exists();
				if ( $centralUserExists && $centralUser->isAttached() ) {
					$source = 'CentralAuth';
				} elseif ( $centralUserExists ) {
					$userIdentity = $this->userIdentityLookup->getUserIdentityByName(
						$name,
						IDBAccessObject::READ_LATEST
					);
					if ( !$userIdentity || !$userIdentity->isRegistered() ) {
						$source = 'CentralAuth';
					}
				}
			}
			if ( $metadata['CentralAuthSource'] !== $source ) {
				// A CentralAuth session but the user is not actually owned by CentralAuth, or
				// an authenticated non-CA session but the user is owned by CA.
				$this->logger->warning(
					'Session "{session}": CentralAuth saved source {saved} != expected source {expected}', [
						'session' => $info->__toString(),
						'saved' => $metadata['CentralAuthSource'],
						'expected' => $source,
						'username' => $name,
					]
				);

				return false;
			}
		}

		return true;
	}

	/** @inheritDoc */
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

	/** @inheritDoc */
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

	/** @inheritDoc */
	protected function cookieDataToExport( $user, $remember ) {
		// If we're going to set CA cookies, don't remember in core cookies.
		if ( $this->enable && $remember ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			$remember = !$centralUser->isAttached();
		}

		return parent::cookieDataToExport( $user, $remember );
	}

	/**
	 * Sets CentralAuth and core authentication cookies.
	 *
	 * - For authenticated sessions where the local account is owned by the corresponding central
	 *   account, the core <wiki>UserToken cookie is not set, the centralauth_Session,
	 *   centralauth_User, and (if the session is remembered) centralauth_Token cookies are set,
	 *   and a new central session object is created in the central session store if there wasn't
	 *   already a centralauth_Session cookie pointing at a valid object.
	 * - Otherwise (including stub central sessions), behavior is identical to CookieSessionProvider.
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

			// We only save the user into the central session if it's not a
			// "pending" session, but we still need the ID to set the cookie.
			$centralSessionId = $s->get( 'CentralAuth::centralSessionId' )
				?: $this->getCookie( $request, $this->params['centralSessionName'], '' )
				?: false;
			$data = $centralSessionId ? $this->sessionManager->getCentralSessionById( $centralSessionId ) : [];
			if ( isset( $data['pending_name'] ) ) {
				$remember = false;
			} else {
				$data['user'] = $centralUser->getName();
				$data['token'] = $centralUser->getAuthToken();
				$data['remember'] = $remember;
			}
			$centralSessionId = $this->sessionManager->setCentralSession( $data, $centralSessionId, $s );

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
		}
	}

	/**
	 * Deletes CentralAuth and core authentication cookies.
	 */
	public function unpersistSession( WebRequest $request ) {
		parent::unpersistSession( $request );

		if ( !$this->enable ) {
			return;
		}

		$response = $request->response();
		if ( $response->headersSent() ) {
			// Can't do anything now
			$this->logger->warning( __METHOD__ . ': Headers already sent' );
			return;
		}

		$response->clearCookie( 'User', $this->centralCookieOptions );
		$response->clearCookie( 'Token', $this->centralCookieOptions );
		$response->clearCookie( $this->params['centralSessionName'],
			[ 'prefix' => '' ] + $this->centralCookieOptions );
	}

	/** @inheritDoc */
	public function invalidateSessionsForUser( User $user ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( $centralUser->exists() && ( $centralUser->isAttached() || !$user->isRegistered() ) ) {
			$centralUser->resetAuthToken();
		}
	}

	/** @inheritDoc */
	public function preventSessionsForUser( $username ) {
		$username = $this->userNameUtils->getCanonical( $username );
		if ( !$username ) {
			return;
		}

		$centralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
		if ( !$centralUser->exists() ) {
			return;
		}

		// Reset the user's password to something invalid and reset the token
		// if it's not already invalid.
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		try {
			$password = $passwordFactory->newFromCiphertext( $centralUser->getPassword() );
		} catch ( PasswordError ) {
			return;
		}
		if ( !$password instanceof InvalidPassword ) {
			$centralUser->setPassword( null );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function setForceHTTPSCookie( $set, ?SessionBackend $backend, WebRequest $request ) {
		// Do nothing. We don't support mixed-protocol HTTP/HTTPS wikis in CentralAuth,
		// so this cookie is not needed.
	}

	/** @inheritDoc */
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

	/** @inheritDoc */
	public function suggestLoginUsername( WebRequest $request ) {
		$name = $this->getCookie( $request, 'User', $this->centralCookieOptions['prefix'] );
		if ( $name !== null ) {
			if ( $this->userNameUtils->isTemp( $name ) ) {
				$name = false;
			} else {
				$name = $this->userNameUtils->getCanonical( $name, UserRigorOptions::RIGOR_USABLE );
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

	/** @inheritDoc */
	protected function getExtendedLoginCookies() {
		$cookies = parent::getExtendedLoginCookies();
		$cookies[] = 'User';
		return $cookies;
	}

	/** @inheritDoc */
	public function getRememberUserDuration() {
		// CentralAuth needs User and Token cookies to remember the user. The fallback to
		// sessions needs UserID as well, so if that one has shorter expiration, the remember
		// duration will depend on whether the account is attached; let's return the shorter
		// duration in that case.

		return min(
			$this->getLoginCookieExpiration( 'User', true ),
			$this->getLoginCookieExpiration( 'Token', true ),
			$this->getLoginCookieExpiration( 'UserID', true )
		) ?: null;
	}

	protected function getJwtClaimOverrides( int $expirationDuration ): array {
		$issuerWiki = $this->config->get( CAMainConfigNames::CentralAuthCentralWiki );
		if ( !$issuerWiki ) {
			return parent::getJwtClaimOverrides( $expirationDuration );
		}
		$issuer = WikiMap::getWiki( $issuerWiki )->getCanonicalServer();
		return [
			'iss' => $issuer,
		] + parent::getJwtClaimOverrides( $expirationDuration );
	}

}
