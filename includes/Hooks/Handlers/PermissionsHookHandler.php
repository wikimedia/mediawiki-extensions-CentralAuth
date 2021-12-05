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

use CentralAuthUser;
use Config;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook;
use Title;
use User;

class PermissionsHookHandler implements
	GetUserPermissionsErrorsExpensiveHook
{
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

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

		if ( $this->config->get( 'DisableUnmergedEditing' )
			&& ( $action === 'edit' || $action === 'delete' )
			&& !$centralUser->exists()
			&& !$title->inNamespaces( NS_USER_TALK, NS_PROJECT_TALK )
		) {
			$result = 'centralauth-error-unmerged';
			return false;
		}

		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}

		if ( $centralUser->isLocked() || $centralUser->isHidden() ) {
			$result = 'centralauth-error-locked';
			return false;
		}

		return true;
	}
}
