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

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterAlterVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterComputeVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterShouldFilterActionHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalPageMoveJob;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Title\Title;
use RecentChange;
use User;

class AbuseFilterHookHandler implements
	AbuseFilterAlterVariablesHook,
	AbuseFilterBuilderHook,
	AbuseFilterComputeVariableHook,
	AbuseFilterGenerateUserVarsHook,
	AbuseFilterShouldFilterActionHook
{

	/**
	 * Load our global_account_groups and global_account_editcount variables
	 * during (auto)createaccount actions.
	 *
	 * @param VariableHolder &$vars
	 * @param Title $title Title object target of the action
	 * @param User $user User object performer of the action
	 */
	public function onAbuseFilterAlterVariables(
		VariableHolder &$vars,
		Title $title,
		User $user
	) {
		$action = $vars->getComputedVariable( 'action' )->toString();
		if ( in_array( $action, [ 'createaccount', 'autocreateaccount' ] ) ) {
			$accountname = $vars->getComputedVariable( 'accountname' )->toString();
			$vars->setLazyLoadVar( 'global_account_groups', 'global-user-groups', [ 'user' => $accountname ] );
			$vars->setLazyLoadVar( 'global_account_editcount', 'global-user-editcount', [ 'user' => $accountname ] );
		}
	}

	/**
	 * Computes the global_user_groups and global_user_editcount variables
	 * @param string $method
	 * @param VariableHolder $vars
	 * @param array $parameters
	 * @param ?string &$result
	 * @return bool
	 */
	public function onAbuseFilter_computeVariable(
		string $method,
		VariableHolder $vars,
		array $parameters,
		?string &$result
	) {
		if ( $method == 'global-user-groups' ) {
			$user = CentralAuthUser::getInstanceByName( $parameters['user'] );
			if ( $user->exists() && $user->isAttached() ) {
				$result = $user->getGlobalGroups();
			} else {
				$result = [];
			}
			return false;
		} elseif ( $method == 'global-user-editcount' ) {
			$user = CentralAuthUser::getInstanceByName( $parameters['user'] );
			if ( $user->exists() && $user->isAttached() ) {
				$result = $user->getGlobalEditCount();
			} else {
				$result = 0;
			}
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Load our global_user_groups and global_user_editcount variables
	 * @param VariableHolder $vars
	 * @param User $user
	 * @param ?RecentChange $rc
	 * @return bool
	 */
	public function onAbuseFilter_generateUserVars(
		VariableHolder $vars,
		User $user,
		?RecentChange $rc
	) {
		$vars->setLazyLoadVar( 'global_user_groups', 'global-user-groups', [ 'user' => $user->getName() ] );
		$vars->setLazyLoadVar( 'global_user_editcount', 'global-user-editcount', [ 'user' => $user->getName() ] );
		return true;
	}

	/**
	 * Tell AbuseFilter about our global_user_groups and global_user_editcount variables
	 * @param array &$realValues
	 * @return bool
	 */
	public function onAbuseFilter_builder( array &$realValues ) {
		// Uses: 'abusefilter-edit-builder-vars-global-user-groups'
		$realValues['vars']['global_user_groups'] = 'global-user-groups';
		// Uses: 'abusefilter-edit-builder-vars-global-user-editcount'
		$realValues['vars']['global_user_editcount'] = 'global-user-editcount';
		// Uses: 'abusefilter-edit-builder-vars-global-account-groups'
		$realValues['vars']['global_account_groups'] = 'global-account-groups';
		// Uses: 'abusefilter-edit-builder-vars-global-account-editcount'
		$realValues['vars']['global_account_editcount'] = 'global-account-editcount';
		return true;
	}

	/**
	 * Avoid filtering page moves during global rename
	 *
	 * @param VariableHolder $vars
	 * @param Title $title
	 * @param User $user
	 * @param array &$skipReasons
	 * @return bool
	 */
	public function onAbuseFilterShouldFilterAction(
		VariableHolder $vars,
		Title $title,
		User $user,
		array &$skipReasons
	) {
		$action = $vars->getComputedVariable( 'action' )->toString();
		if ( $action === 'move' && LocalPageMoveJob::$moveInProgress === true ) {
			$skipReasons[] = "CentralAuth: $user is moving $title for global rename";
			// Don't allow reusing this flag
			LocalPageMoveJob::$moveInProgress = false;
			return false;
		}
		return true;
	}
}
