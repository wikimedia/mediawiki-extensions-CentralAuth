<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use CentralAuthUser;
use LocalPageMoveJob;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterComputeVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterShouldFilterActionHook;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use RecentChange;
use Title;
use User;

class AbuseFilterHooks implements
	AbuseFilterBuilderHook,
	AbuseFilterComputeVariableHook,
	AbuseFilterGenerateUserVarsHook,
	AbuseFilterShouldFilterActionHook
{

	/**
	 * Computes the global_user_groups variable
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
			$user = CentralAuthUser::getInstance( $parameters['user'] );
			if ( $user->exists() && $user->isAttached() ) {
				$result = $user->getGlobalGroups();
			} else {
				$result = [];
			}
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Load our global_user_groups variable
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
		$vars->setLazyLoadVar( 'global_user_groups', 'global-user-groups', [ 'user' => $user ] );
		return true;
	}

	/**
	 * Tell AbuseFilter about our global_user_groups variable
	 * @param array &$realValues
	 * @return bool
	 */
	public function onAbuseFilter_builder( array &$realValues ) {
		// Uses: 'abusefilter-edit-builder-vars-global-user-groups'
		$realValues['vars']['global_user_groups'] = 'global-user-groups';
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
