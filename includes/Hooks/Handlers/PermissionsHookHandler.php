<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class PermissionsHookHandler implements GetUserPermissionsErrorsExpensiveHook {

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param string &$result Message key
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ) {
		if ( $action == 'read' || !$user->isRegistered() ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );

		if (
			$centralUser->exists()
			&& $centralUser->isAttached()
			&& ( $centralUser->isLocked() || $centralUser->isHidden() )
		) {
			$result = 'centralauth-error-locked';
			return false;
		}

		return true;
	}
}
