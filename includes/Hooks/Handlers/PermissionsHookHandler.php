<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
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
