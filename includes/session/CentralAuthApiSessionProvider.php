<?php

use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionInfo;

/**
 * Session provider for CentralAuth API centralauthtoken
 *
 * This session provider looks for the 'centralauthtoken' request parameter,
 * and checks that it corresponds to an existing token set up by
 * ApiCentralAuthToken. If the parameter is present but invalid, it returns a
 * bogus SessionInfo and hooks ApiBeforeMain to throw an appropriate exception
 * later when MediaWiki is ready to handle it.
 *
 * @see \MediaWiki\Extension\CentralAuth\Api\ApiCentralAuthToken
 */
class CentralAuthApiSessionProvider extends CentralAuthTokenSessionProvider {

	/** @inheritDoc */
	protected function getTokenDataFromRequest( WebRequest $request ) {
		// Only relevant in the API
		if ( !defined( 'MW_API' ) ) {
			return null;
		}

		$oneTimeToken = $request->getVal( 'centralauthtoken' );
		if ( $oneTimeToken === null ) {
			return null;
		}

		$timeout = $this->getConfig()->get( 'CentralAuthTokenSessionTimeout' );

		if ( $request->getMethod() === 'OPTIONS' ) {
			// Do not delete the tokenized data on OPTIONS requests, as they are generated automatically
			// by the browser as part of the CORS preflight mechanism, with the same URL as the real
			// GET/POST request (including the 'centralauthtoken' parameter). Deleting it here would cause
			// the subsequent real request to fail. There is no way to avoid that.
			// https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS#preflighted_requests
			return $this->tokenManager->detokenize( $oneTimeToken, 'api-token', [ 'timeout' => $timeout ] );
		} else {
			return $this->tokenManager->detokenizeAndDelete( $oneTimeToken, 'api-token', [ 'timeout' => $timeout ] );
		}
	}

	/**
	 * Throw an exception, later
	 *
	 * @param string $code Error code
	 * @param string|array $error Error message key, or key+parameters
	 * @return SessionInfo
	 */
	protected function makeBogusSessionInfo( $code, $error ) {
		// Schedule the throwing of the exception for later when the API
		// is ready to catch it.
		$exception = ApiUsageException::newWithMessage( null, $error, $code );
		/** @return never */
		$excepClosure = static function () use ( $exception ) {
			throw $exception;
		};
		$this->getHookContainer()->register( 'ApiBeforeMain', $excepClosure );

		return parent::makeBogusSessionInfo( $code, $error );
	}

	/** @inheritDoc */
	public function provideSessionInfo( WebRequest $request ) {
		// Only relevant in the API
		if ( !defined( 'MW_API' ) ) {
			return null;
		}

		return parent::provideSessionInfo( $request );
	}

}
