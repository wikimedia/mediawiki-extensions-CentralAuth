<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\User\Registration\IUserRegistrationProvider;
use MediaWiki\User\UserIdentity;

class CentralAuthGlobalRegistrationProvider implements IUserRegistrationProvider {

	/**
	 * @inheritDoc
	 */
	public function fetchRegistration( UserIdentity $user ) {
		if ( !$user->isRegistered() ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists() && $centralUser->isAttached() ) {
			return $centralUser->getRegistration();
		}
		return null;
	}
}
