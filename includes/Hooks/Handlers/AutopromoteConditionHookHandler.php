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
use MediaWiki\User\Hook\AutopromoteConditionHook;

class AutopromoteConditionHookHandler implements AutopromoteConditionHook {

	/** @inheritDoc */
	public function onAutopromoteCondition( $type, $args, $user, &$result ) {
		if ( $type !== APCOND_CA_INGLOBALGROUPS ) {
			return;
		}

		// If there is no central account for this user or if the central account is not attached to the local
		// user, then consider the local user to have no global groups.
		$centralUser = CentralAuthUser::getInstanceByName( $user );
		if ( !$centralUser->isAttached() ) {
			$result = false;
			return;
		}

		// Check if the central account for this user has all the required groups for the condition to be true.
		$matchingGroups = array_intersect( $args, $centralUser->getGlobalGroups() );
		$result = count( $matchingGroups ) === count( $args );
	}
}
