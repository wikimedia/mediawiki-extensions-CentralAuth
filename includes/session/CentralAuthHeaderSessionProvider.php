<?php

use MediaWiki\Request\WebRequest;

/**
 * Session provider for REST API `Authorization: CentralAuthToken ...` header.
 *
 * This session provider looks for an Authorization header using the CentralAuthToken
 * authentication type, and checks that the token provided in the header
 * corresponds to an existing token set up by ApiCentralAuthToken.
 * If the header is present but invalid, it returns a
 * bogus SessionInfo to prevent other SessionProviders from establishing a session
 * and throw an appropriate exception later when MediaWiki is ready to handle it.
 *
 * @see \MediaWiki\Extension\CentralAuth\Api\ApiCentralAuthToken
 */
class CentralAuthHeaderSessionProvider extends CentralAuthTokenSessionProvider {

	/** @inheritDoc */
	protected function getTokenDataFromRequest( WebRequest $request ) {
		$authHeader = $request->getHeader( 'Authorization' );
		if ( $authHeader === null ) {
			return null;
		}

		if ( !preg_match( '/^CentralAuthToken\s+(\S+)/i', $authHeader, $match ) ) {
			return null;
		}

		$oneTimeToken = $match[1];
		if ( $oneTimeToken === null ) {
			return null;
		}

		return $this->apiTokenManager->detokenizeAndDelete( $oneTimeToken, 'api-token' );
	}

	/**
	 * This session provider is based on a header, so it's safe against CSRF attacks.
	 * @return true
	 */
	public function safeAgainstCsrf() {
		return true;
	}

}
