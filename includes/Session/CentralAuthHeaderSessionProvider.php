<?php

namespace MediaWiki\Extension\CentralAuth\Session;

use WebRequest;

/**
 * Session provider for CentralAuth Authorization header for use in REST APIs.
 *
 * This session provider looks for an Authorization header using the MWCentralAuth
 * authentication type, and checks that the token provided in the header
 * corresponds to an existing token set up by ApiCentralAuthToken.
 * If the header is present but invalid, it returns a
 * bogus SessionInfo to prevent other SessionProviders from establishing a session.
 */
class CentralAuthHeaderSessionProvider extends CentralAuthTokenSessionProvider {

	protected function getTokenFromRequest( WebRequest $request ) {
		$authHeader = $request->getHeader( 'Authorization' );
		if ( $authHeader === null ) {
			return null;
		}

		if ( !preg_match( '/^CentralAuthToken\s+(\w+)/is', $authHeader, $match ) ) {
			return null;
		}

		return $match[1];
	}

	/**
	 * This session provider is based on a header, so it's safe against CSRF attacks.
	 * @return true
	 */
	public function safeAgainstCsrf() {
		return true;
	}

}
