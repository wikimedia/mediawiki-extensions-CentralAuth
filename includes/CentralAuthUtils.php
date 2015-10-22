<?php

class CentralAuthUtils {
	public static function isReadOnly() {
		return ( self::getReadOnlyReason() !== false );
	}

	public static function getReadOnlyReason() {
		global $wgCentralAuthDatabase;

		if ( wfReadOnly() ) {
			return wfReadOnlyReason();
		}

		$lb = wfGetLB( $wgCentralAuthDatabase );
		$reason = $lb->getReadOnlyReason( $wgCentralAuthDatabase );
		if ( $reason !== false ) {
			return $reason;
		}

		return false;
	}

	/**
	 * @param WebRequest|null $request
	 */
	public static function setP3P( WebRequest $request = null ) {
		if ( !$request ) {
			$request = RequestContext::getMain()->getRequest();
		}
		$response = $request->response();

		$sent = is_callable( array( $response, 'headersSent' ) )
			? $response->headersSent()
			: headers_sent();

		if ( !$sent && $response->getHeader( 'P3P' ) === null ) {
			// IE requires that a P3P header be provided for the cookies to be
			// visible to the auto-login check.
			global $wgCentralAuthCookiesP3P;
			if ( $wgCentralAuthCookiesP3P === true ) {
				// Note this policy is not valid: it has no valid tokens, while
				// a valid policy would contain an "access" token and at least
				// one statement, which would contain either the NID token or
				// at least one "purpose" token, one "recipient" token, and one
				// "retention" token.
				$url = Title::makeTitle( NS_SPECIAL, 'CentralAutoLogin/P3P' )->getCanonicalURL();
				$response->header( "P3P: CP=\"This is not a P3P policy! See $url for more info.\"" );
			} elseif ( $wgCentralAuthCookiesP3P ) {
				$response->header( "P3P: $wgCentralAuthCookiesP3P" );
			}
		}
	}

	/**
	 * @return string
	 */
	public static function memcKey( /*...*/ ) {
		global $wgCentralAuthDatabase;
		$args = func_get_args();
		return $wgCentralAuthDatabase . ':' . implode( ':', $args );
	}

	/**
	 * @return BagOStuff
	 */
	public static function getSessionCache() {
		global $wgSessionsInObjectCache, $wgSessionCacheType;

		return $wgSessionsInObjectCache
			? ObjectCache::getInstance( $wgSessionCacheType )
			: ObjectCache::getMainStashInstance();
	}

	/**
	 * Auto-create a user
	 * @param User $user
	 * @return bool Success
	 */
	public static function autoCreateUser( User $user ) {
		return CentralAuthSessionCompat::attemptAddUser( $user );
	}

	/**
	 * Get the central session data
	 * @return array
	 */
	public static function getCentralSession() {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return array();
		}

		$id = isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] )
			? $_COOKIE[$wgCentralAuthCookiePrefix . 'Session']
			: null;

		if ( $id ) {
			$key = CentralAuthUtils::memcKey( 'session', $id );
			$stime = microtime( true );
			$data = CentralAuthUtils::getSessionCache()->get( $key ) ?: array();
			$real = microtime( true ) - $stime;
			RequestContext::getMain()->getStats()->timing( 'centralauth.session.read', $real );
			return $data;
		} else {
			return array();
		}
	}

	/**
	 * Set data in the central session
	 * @param array $data
	 * @param bool|string $reset Reset the session ID. If a string, this is the new ID.
	 * @return string|null Session ID
	 */
	public static function setCentralSession( array $data, $reset = false ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return null;
		}

		$id = isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] )
			? $_COOKIE[$wgCentralAuthCookiePrefix . 'Session']
			: null;

		if ( $reset || $id === null ) {
			$id = is_string( $reset ) ? $reset : MWCryptRand::generateHex( 32 );
		}
		$data['sessionId'] = $id;
		$key = CentralAuthUtils::memcKey( 'session', $id );
		$stime = microtime( true );
		CentralAuthUtils::getSessionCache()->set( $key, $data, 86400 );
		$real = microtime( true ) - $stime;
		RequestContext::getMain()->getStats()->timing( 'centralauth.session.write', $real );

		return $id;
	}

	/**
	 * Delete the central session data
	 */
	public static function deleteCentralSession() {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return;
		}

		$id = isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] )
			? $_COOKIE[$wgCentralAuthCookiePrefix . 'Session']
			: null;

		if ( $id !== null ) {
			$key = CentralAuthUtils::memcKey( 'session', $id );
			$stime = microtime( true );
			CentralAuthUtils::getSessionCache()->delete( $key );
			$real = microtime( true ) - $stime;
			RequestContext::getMain()->getStats()->timing( "centralauth.session.delete", $real );
		}
	}
}
