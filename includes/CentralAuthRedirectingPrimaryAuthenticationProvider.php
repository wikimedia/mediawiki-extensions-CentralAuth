<?php

namespace MediaWiki\Extension\CentralAuth;

use IDBAccessObject;
use LogicException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\RedirectingLoginHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use StatusValue;

/**
 * Redirect-based provider which sends the user to another domain, assumed to be
 * served by the same wiki farm, to log in, and expects to receive the result of
 * that authentication process when the user returns.
 */
class CentralAuthRedirectingPrimaryAuthenticationProvider
	extends AbstractPrimaryAuthenticationProvider
{
	public const NON_LOGIN_WIKI_BUTTONREQUEST_NAME = 'non-loginwiki';

	/**
	 * @internal
	 * @var string The storage key prefix for the URL token used for continuing
	 *   authentication in the central login wiki.
	 */
	public const RETURN_URL_TOKEN_KEY_PREFIX = 'centralauth-homewiki-return-url-token';

	private TitleFactory $titleFactory;
	private CentralAuthSessionManager $sessionManager;
	private CentralAuthUtilityService $centralAuthUtility;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		TitleFactory $titleFactory,
		CentralAuthSessionManager $sessionManager,
		CentralAuthUtilityService $centralAuthUtility,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->titleFactory = $titleFactory;
		$this->sessionManager = $sessionManager;
		$this->centralAuthUtility = $centralAuthUtility;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action === AuthManager::ACTION_LOGIN
			&& $this->sharedDomainUtils->isSul3Enabled( $this->manager->getRequest() )
			&& !$this->sharedDomainUtils->isSharedDomain()
		) {
			return [ new CentralAuthRedirectingAuthenticationRequest() ];
		}
		return [];
	}

	/** @inheritDoc */
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

		$returnUrlToken = $this->centralAuthUtility->tokenize(
			$req->returnToUrl, self::RETURN_URL_TOKEN_KEY_PREFIX, $this->sessionManager
		);

		$url = wfAppendQuery(
			$this->getCentralLoginUrl(),
			[ 'returnUrlToken' => $returnUrlToken ]
		);
		return AuthenticationResponse::newRedirect( [ new CentralAuthReturnRequest() ], $url );
	}

	/** @inheritDoc */
	public function continuePrimaryAuthentication( array $reqs ) {
		$this->sharedDomainUtils->assertSul3Enabled( $this->manager->getRequest() );
		$this->sharedDomainUtils->assertIsNotSharedDomain();

		$req = AuthenticationRequest::getRequestByClass(
			$reqs, CentralAuthReturnRequest::class
		);

		if ( !$req ) {
			throw new LogicException( 'Local authentication failed, please try again.' );
		}

		$username = $this->centralAuthUtility->detokenize(
			$req->token,
			RedirectingLoginHookHandler::LOGIN_CONTINUE_USERNAME_KEY_PREFIX,
			$this->sessionManager
		);

		if ( !$username ) {
			throw new RuntimeException( 'Invalid user token, try to login again' );
		}

		return AuthenticationResponse::newPass( $username );
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
		return $centralUser && $centralUser->exists();
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

	/**
	 * Get the login URL on the shared login domain wiki.
	 *
	 * @return string
	 */
	private function getCentralLoginUrl(): string {
		$localUrl = $this->titleFactory->newFromText( 'Special:UserLogin' )->getLocalURL();
		$url = $this->config->get( 'CentralAuthSsoUrlPrefix' ) . $localUrl;

		return wfAppendQuery( $url, [
			// At this point, we should just be leaving the local
			// wiki before hitting the loginwiki.
			'wikiid' => WikiMap::getCurrentWikiId(),
			// TODO: Fix T369467
			'returnto' => 'Main_Page',
			'usesul3' => '1',
		] );
	}

}
