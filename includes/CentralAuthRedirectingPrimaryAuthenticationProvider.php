<?php

namespace MediaWiki\Extension\CentralAuth;

use IDBAccessObject;
use LogicException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use StatusValue;

/**
 * Redirect-based provider which sends the user to another domain, assumed to be
 * served by the same wiki farm, to log in, and expects to receive the result of
 * that authentication process when the user returns.
 */
class CentralAuthRedirectingPrimaryAuthenticationProvider
	extends AbstractPrimaryAuthenticationProvider
{
	use CentralAuthenticationProviderTrait;

	public const NON_LOGIN_WIKI_BUTTONREQUEST_NAME = 'non-loginwiki';

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action === AuthManager::ACTION_LOGIN
			&& $this->isSul3Enabled( $this->config, $this->manager->getRequest() )
			&& !$this->isSharedDomain()
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

		$this->assertSul3Enabled( $this->config, $this->manager->getRequest() );
		$this->assertIsNotSharedDomain();

		$url = wfAppendQuery(
			$this->getCentralLoginUrl(), [ 'returnurl' => $req->returnToUrl ]
		);
		return AuthenticationResponse::newRedirect( [ new CentralAuthReturnRequest() ], $url );
	}

	/** @inheritDoc */
	public function continuePrimaryAuthentication( array $reqs ) {
		$this->assertSul3Enabled( $this->config, $this->manager->getRequest() );
		$this->assertIsNotSharedDomain();

		$req = AuthenticationRequest::getRequestByClass(
			$reqs, CentralAuthReturnRequest::class
		);

		if ( !$req ) {
			throw new LogicException( 'Local authentication failed, please try again.' );
		}

		return AuthenticationResponse::newPass( $req->token );
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
		if ( $this->isSharedDomain() ) {
			return false;
		}

		return $this->testUserExistsInternal( $username, $this->userNameUtils );
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
