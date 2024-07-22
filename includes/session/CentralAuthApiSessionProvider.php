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

	/**
	 * @param WebRequest $request
	 *
	 * @return string|null
	 */
	protected function getTokenFromRequest( WebRequest $request ) {
		// Only relevant in the API
		if ( !defined( 'MW_API' ) ) {
			return null;
		}

		return $request->getVal( 'centralauthtoken' );
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
