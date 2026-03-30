<?php

use MediaWiki\Request\WebRequest;

/**
 * Session provider for action API `centralauthtoken=...` parameter.
 *
 * This session provider looks for the 'centralauthtoken' request parameter,
 * and checks that it corresponds to an existing token set up by
 * ApiCentralAuthToken. If the parameter is present but invalid, it returns a
 * bogus SessionInfo to prevent other SessionProviders from establishing a session
 * and throw an appropriate exception later when MediaWiki is ready to handle it.
 *
 * @see \MediaWiki\Extension\CentralAuth\Api\ApiCentralAuthToken
 */
class CentralAuthApiSessionProvider extends CentralAuthTokenSessionProvider {

	/** @inheritDoc */
	protected function getTokenDataFromRequest( WebRequest $request ) {
		// Only applied to api.php and unit tests
		if ( !defined( 'MW_API' ) && !defined( 'MW_PHPUNIT_TEST' ) ) {
			return null;
		}

		$oneTimeToken = $request->getVal( 'centralauthtoken' );
		if ( $oneTimeToken === null ) {
			return null;
		}

		if ( $request->getMethod() === 'OPTIONS' ) {
			// Do not delete the tokenized data on OPTIONS requests, as they are generated automatically
			// by the browser as part of the CORS preflight mechanism, with the same URL as the real
			// GET/POST request (including the 'centralauthtoken' parameter). Deleting it here would cause
			// the subsequent real request to fail. There is no way to avoid that.
			// https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS#preflighted_requests
			return $this->apiTokenManager->detokenize( $oneTimeToken, 'api-token' );
		} else {
			return $this->apiTokenManager->detokenizeAndDelete( $oneTimeToken, 'api-token' );
		}
	}

}
