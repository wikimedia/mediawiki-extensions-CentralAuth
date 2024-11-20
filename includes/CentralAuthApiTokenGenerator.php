<?php

namespace MediaWiki\Extension\CentralAuth;

use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\UserIdentity;
use MWCryptRand;

/**
 * Generate an authentication token to be used for API calls.
 */
class CentralAuthApiTokenGenerator {

	private CentralAuthTokenManager $tokenManager;

	public function __construct(
		CentralAuthTokenManager $tokenManager
	) {
		$this->tokenManager = $tokenManager;
	}

	/**
	 * Get a CentralAuth token for making authenticated API requests to an attached wiki.
	 *
	 * @param UserIdentity $user The registered user
	 * @param string $sessionId The global session ID
	 * @param string $wikiId The wiki ID
	 * @return string Token in hexadecimal, with a random part and the central user ID
	 * @throws InvalidArgumentException
	 */
	public function getToken( UserIdentity $user, string $sessionId, string $wikiId ) {
		if ( !$user->isRegistered() ) {
			throw new InvalidArgumentException( 'Cannot get a token for an unregistered user' );
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			throw new InvalidArgumentException( 'Cannot get a token without an attached global user' );
		}

		$data = [
			'userName' => $user->getName(),
			'token' => $centralUser->getAuthToken(),
			'origin' => $wikiId,
			'originSessionId' => $sessionId,
		];
		$loginToken = MWCryptRand::generateHex( 32 ) . dechex( $centralUser->getId() );
		$this->tokenManager->tokenize( $data, 'api-token', [ 'token' => $loginToken ] );

		return $loginToken;
	}

}
