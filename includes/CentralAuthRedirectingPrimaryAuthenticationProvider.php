<?php

namespace MediaWiki\Extension\CentralAuth;

use LogicException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\RedirectingLoginHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserNameUtils;
use MobileContext;
use MWCryptRand;
use StatusValue;
use Wikimedia\NormalizedException\NormalizedException;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Redirect-based provider which sends the user to another domain, assumed to be
 * served by the same wiki farm, to log in, and expects to receive the result of
 * that authentication process when the user returns.
 *
 * @see RedirectingLoginHookHandler
 */
class CentralAuthRedirectingPrimaryAuthenticationProvider
	extends AbstractPrimaryAuthenticationProvider
{

	public const NON_LOGIN_WIKI_BUTTONREQUEST_NAME = 'nonloginwiki';
	public const START_TOKEN_KEY_PREFIX = 'centralauth-sul3-start';
	public const COMPLETE_TOKEN_KEY_PREFIX = 'centralauth-sul3-complete';

	private const SESSION_DATA_FLAG = 'centralauth-login-method-was-sul3';

	private HookContainer $hookContainer;
	private CentralAuthTokenManager $tokenManager;
	private SharedDomainUtils $sharedDomainUtils;
	private ?MobileContext $mobileContext;

	public function __construct(
		HookContainer $hookContainer,
		CentralAuthTokenManager $tokenManager,
		SharedDomainUtils $sharedDomainUtils,
		?MobileContext $mobileContext
	) {
		$this->hookContainer = $hookContainer;
		$this->tokenManager = $tokenManager;
		$this->sharedDomainUtils = $sharedDomainUtils;
		$this->mobileContext = $mobileContext;
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $this->sharedDomainUtils->isSul3Enabled( $this->manager->getRequest() )
			&& !$this->sharedDomainUtils->isSharedDomain()
		) {
			switch ( $action ) {
				case AuthManager::ACTION_LOGIN:
				case AuthManager::ACTION_CREATE:
					return [ new CentralAuthRedirectingAuthenticationRequest() ];
				default:
					return [];
			}
		}
		return [];
	}

	/**
	 * Store a random secret in the session and redirect the user to the central login wiki,
	 * passing the secret and the return URL via the token store. The secret will be used on
	 * return to prevent a session fixation attack.
	 *
	 * @inheritDoc
	 */
	public function beginPrimaryAuthentication( array $reqs ) {
		$req = CentralAuthRedirectingAuthenticationRequest::getRequestByName(
			$reqs,
			self::NON_LOGIN_WIKI_BUTTONREQUEST_NAME
		);

		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}

		$this->sharedDomainUtils->assertSul3Enabled( $this->manager->getRequest() );
		$this->sharedDomainUtils->assertIsNotSharedDomain();

		$secret = MWCryptRand::generateHex( 32 );
		$this->manager->setAuthenticationSessionData( 'CentralAuth:sul3-login:pending', [
			'secret' => $secret,
		] );

		// For the most part, MediaWiki is not aware of mobile domains and uses the standard
		// domain in all URLs it generates; they must be adjusted manually.
		$returnToUrl = $req->returnToUrl;
		if ( $this->mobileContext && $this->mobileContext->usingMobileDomain() ) {
			$returnToUrl = $this->mobileContext->getMobileUrl( $returnToUrl );
		}

		// Make sure SUL3 opt-in state is preserved, e.g. in case of the user changing networks
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$returnToUrl = wfAppendQuery( $returnToUrl, [ 'usesul3' => 1 ] );

		$data = [
			'secret' => $secret,
			'returnUrl' => $returnToUrl,
		];
		// ObjectCacheSessionExpiry will limit how long the local login process, which relies
		// on the session, can be finished. Sync the expiry of this token (which will be used
		// when central login ends) with that.
		$expiry = $this->config->get( MainConfigNames::ObjectCacheSessionExpiry );
		$token = $this->tokenManager->tokenize(
			$data, self::START_TOKEN_KEY_PREFIX, [ 'expiry' => $expiry ]
		);

		$isSignup = $this->manager->getRequest()->getRawVal( 'sul3-action' ) === 'signup';
		$url = wfAppendQuery(
			$this->sharedDomainUtils->getUrlForSharedDomainAction(
				$isSignup ? 'signup' : 'login',
				$this->manager->getRequest()
			),
			[ 'centralauthLoginToken' => $token ]
		);

		return AuthenticationResponse::newRedirect( [ new CentralAuthReturnRequest() ], $url );
	}

	/**
	 * Verify the secret and log the user in.
	 *
	 * @inheritDoc
	 */
	public function continuePrimaryAuthentication( array $reqs ) {
		$this->sharedDomainUtils->assertSul3Enabled( $this->manager->getRequest() );
		$this->sharedDomainUtils->assertIsNotSharedDomain();

		$req = AuthenticationRequest::getRequestByClass(
			$reqs, CentralAuthReturnRequest::class
		);

		if ( !$req ) {
			throw new LogicException( 'CentralAuthReturnRequest not found' );
		}

		// Suppress the AuthManagerLoginAuthenticateAudit hook from running.
		$session = $this->manager->getRequest()->getSession();
		$session->set( RedirectingLoginHookHandler::SUPPRESS_HOOK_SESSION_FLAG, true );

		$data = $this->tokenManager->detokenizeAndDelete(
			$req->centralauthLoginToken, self::COMPLETE_TOKEN_KEY_PREFIX
		);
		$sessionData = $this->manager->getAuthenticationSessionData( 'CentralAuth:sul3-login:pending' );
		if ( $data === false || $sessionData === false ) {
			// Central login/signup was succesful but the local login session has expired.
			// TODO maybe just do a top-level autologin?
			return AuthenticationResponse::newFail( wfMessage( 'centralauth-error-sul3-expired' ) );
		}
		foreach ( [ 'secret', 'username' ] as $key ) {
			if ( !isset( $data[$key] ) ) {
				throw new LogicException( "$key not found in return data" );
			}
		}
		if ( !isset( $sessionData['secret'] ) ) {
			throw new LogicException( 'Secret not found in session data' );
		}

		// Only the user who started the authentication process can have the secret in their local
		// session. There is no way to guarantee that the person entering their credentials on the
		// login form on the shared domain is the same; if an attacker initiates a login flow,
		// tricks a victim into visiting the redirect URL returned by beginPrimaryAuthentication(),
		// and then is somehow able to obtain the URL the victim would be redirected back to after
		// submitting the login form, they would get logged in as the victim locally. But there is
		// no way to do that without fundamentally compromising browser, site or network security.
		if ( !$data['secret'] || $data['secret'] !== $sessionData['secret'] ) {
			LoggerFactory::getInstance( 'security' )->error( __CLASS__ . ': Secret mismatch',
				[
					'username' => $data['username'],
				]
			);
			return AuthenticationResponse::newFail( wfMessage( 'centralauth-error-badtoken' ) );
		}

		// T388177 - after account creation, we want to avoid any immediate lags, and read
		// the user data from the primary DB during login (immediately after signups), hence
		// ::getPrimaryInstanceByName(). But for a regular login, let's read from replica
		// so we don't increase load on the DB for every single authentication which is
		// needless.
		$centralUser = CentralAuthUser::getInstanceByName( $data['username'] );
		if ( !$centralUser->exists() ) {
			$centralUser = CentralAuthUser::getPrimaryInstanceByName( $data['username'] );
		}

		if ( $centralUser->getId() !== $data['userId'] ) {
			$msg = "User ID: {centralUserId} mismatch with {storedUserId} for user: {username}";
			throw new NormalizedException( $msg, [
				'centralUserId' => $centralUser->getId(),
				'storedUserId' => $data['userId'],
				'username' => $data['username'],
			] );
		}

		// Refresh the local wiki's "remember me" state based on the
		// corresponding state from the central domain.
		if ( $data['rememberMe'] ) {
			$this->manager->setAuthenticationSessionData( AuthManager::REMEMBER_ME, true );
		}

		// Flag this login session as being the local leg of a SUL3 login, so we can run
		// CentralAuthPostLoginRedirect afterward.
		$this->manager->setAuthenticationSessionData( self::SESSION_DATA_FLAG, [
			'isSignup' => $data['isSignup'],
		] );

		return AuthenticationResponse::newPass( $data['username'] );
	}

	/** @inheritDoc */
	public function postAuthentication( $user, AuthenticationResponse $response ) {
		$data = $this->manager->getAuthenticationSessionData( self::SESSION_DATA_FLAG );
		if ( $data && $response->status === AuthenticationResponse::PASS ) {
			// HACK: CentralAuth replaces the core PostLoginRedirect hook with its own variant,
			// CentralAuthPostLoginRedirect. In SUL2 this is because we can't allow other extensions
			// to redirect before we could do central login. In SUL3 setting the central session is
			// part of the normal login process, so letting extensions redirect on PostLoginRedirect
			// would be fine, but most extensions which support CentralAuth skip that hook when they
			// see CentralAuth is installed, so fire CentralAuthPostLoginRedirect for them.
			// See T384829 for solving this better in the future.
			$this->hookContainer->register( 'PostLoginRedirect',
				function ( &$returnTo, &$returnToQuery, &$type ) use ( $data ) {
					$hookRunner = new CentralAuthHookRunner( $this->hookContainer );
					$caReturnToQuery = wfArrayToCgi( $returnToQuery );
					// As seen by MediaWiki on a local domain, the action is always a login, even when
					// the user is actually signing up. Use the URL flag to differentiate.
					$caType = $data['isSignup'] ? 'signup' : '';
					$unused = '';
					$retval = $hookRunner->onCentralAuthPostLoginRedirect( $returnTo,
						$caReturnToQuery, false, $caType, $unused );
					$returnToQuery = wfCgiToArray( $caReturnToQuery );
					return $retval;
				}
			);
		}
	}

	/** @inheritDoc */
	public function testUserCanAuthenticate( $username ) {
		return false;
	}

	/** @inheritDoc */
	public function providerNormalizeUsername( $username ) {
		return null;
	}

	/** @inheritDoc */
	public function testUserExists( $username, $flags = IDBAccessObject::READ_NORMAL ) {
		$username = $this->userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_USABLE );
		if ( $username === false ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		return $centralUser->exists();
	}

	/** @inheritDoc */
	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req, $checkData = true ) {
		return StatusValue::newGood( 'ignored' );
	}

	/** @inheritDoc */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
	}

	/** @inheritDoc */
	public function accountCreationType() {
		return self::TYPE_NONE;
	}

	/** @inheritDoc */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

}
