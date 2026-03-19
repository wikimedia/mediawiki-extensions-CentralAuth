<?php

namespace MediaWiki\Extension\CentralAuth;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Json\JwtCodec;
use MediaWiki\Json\JwtException;
use MediaWiki\MainConfigNames;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Generate an authentication token to be used for API calls.
 */
class CentralAuthApiTokenManager {

	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::UseSessionCookieJwt,
		CAMainConfigNames::CentralAuthCentralWiki,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly JwtCodec $jwtCodec,
		private readonly CentralAuthTokenManager $tokenManager,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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

		return $this->wrapTokenInJwt( $loginToken, $user );
	}

	/**
	 * Given a non-wrapped token, maybe wrap it into a JWT, depending on config settings.
	 */
	private function wrapTokenInJwt( string $token, UserIdentity $user ): string {
		// Use $wgUseSessionCookieJwt, despite surprising name, for consistent behavior
		// between API token sessions and normal CentralAuth sessions.
		if ( $this->options->get( MainConfigNames::UseSessionCookieJwt ) ) {
			$issuerWiki = $this->options->get( CAMainConfigNames::CentralAuthCentralWiki );
			$jwtClaimOverrides = [
				// Same as CentralAuthSessionProvider
				'iss' => WikiMap::getWiki( $issuerWiki )->getCanonicalServer(),
				// Same as expiration in CentralAuthTokenManager
				'exp' => ConvertibleTimestamp::time() + CentralAuthTokenManager::EXPIRY,
				// Wrap the token in a custom claim
				'CAToken' => $token,
			];
			$jwtData = $jwtClaimOverrides + SessionManager::singleton()->getJwtData( $user );
			return $this->jwtCodec->create( $jwtData );
		} else {
			return $token;
		}
	}

	/**
	 * When given a JWT string, unwrap our token from it. When given a non-wrapped token, return it as-is.
	 */
	private function unwrapTokenFromJwt( string $maybeJwt ): ?string {
		// Don't check $wgUseSessionCookieJwt here, so that we don't invalidate "in-flight"
		// tokens when deploying config changes. Just check whether the input looks like a JWT,
		// and accept both JWT and plain tokens regardless of the configured mode.
		if ( str_contains( $maybeJwt, '.' ) ) {
			// Probably a JWT
			try {
				$data = $this->jwtCodec->parse( $maybeJwt );
				// We do not need to validate the JWT data here.
				// Looking up the unwrapped token in the store is the real validation.
				return $data['CAToken'] ?? null;
			} catch ( JwtException ) {
				return null;
			}
		} else {
			// Not a JWT - assume this is a non-wrapped token
			return $maybeJwt;
		}
	}

	/**
	 * @see CentralAuthTokenManager::detokenize
	 */
	public function detokenize( string $maybeJwt, string $keyPrefix ): mixed {
		$token = $this->unwrapTokenFromJwt( $maybeJwt );
		if ( $token === null ) {
			return false;
		}
		return $this->tokenManager->detokenize( $token, $keyPrefix );
	}

	/**
	 * @see CentralAuthTokenManager::detokenizeAndDelete
	 */
	public function detokenizeAndDelete( string $maybeJwt, string $keyPrefix ): mixed {
		$token = $this->unwrapTokenFromJwt( $maybeJwt );
		if ( $token === null ) {
			return false;
		}
		return $this->tokenManager->detokenizeAndDelete( $token, $keyPrefix );
	}

}
