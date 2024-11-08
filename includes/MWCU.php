<?php

// This class is intentionally not in the normal CentralAuth namespace, for easy access.

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * Debug helper class for CentralAuth.
 * @see MW
 * @internal must not be used in code, anywhere
 */
class MWCU {

	public static function user( string $username ): CentralAuthUser {
		$cu = CentralAuthUser::getPrimaryInstanceByName( $username );
		// Make sure the data is loaded
		$cu->getId();
		return $cu;
	}

}
