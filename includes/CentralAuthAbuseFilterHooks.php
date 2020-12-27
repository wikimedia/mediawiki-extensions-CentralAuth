<?php

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterBuilderHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterComputeVariableHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterGenerateUserVarsHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterShouldFilterActionHook;

/**
 * CentralAuth hook runners for the AbuseFilter extension
 */
class CentralAuthAbuseFilterHooks implements
	// AbuseFilterBuilderHook, // T270852
	// AbuseFilterComputeVariableHook, //
	// AbuseFilterGenerateUserVarsHook, // T270852
	AbuseFilterShouldFilterActionHook
{
	/**
	 * Tell AbuseFilter about our global_user_groups variable
	 *
	 * @param array $realValues
	 *
	 * @return bool
	 */
	public static function onAbuseFilterBuilder( array &$realValues ) {
		// Uses: 'abusefilter-edit-builder-vars-global-user-groups'
		$builderValues['vars']['global_user_groups'] = 'global-user-groups';
		return true;
	}

	/**
	 * Computes the global_user_groups variable
	 *
	 * @param string $method
	 * @param AbuseFilterVariableHolder $vars
	 * @param array $parameters
	 * @param string|null $result
	 *
	 * @return bool
	 */
	public static function onAbuseFilterComputeVariable(
		string $method,
		AbuseFilterVariableHolder $vars,
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
	 *
	 * @param AbuseFilterVariableHolder $vars
	 * @param User $user
	 * @param RecentChange|null $rc
	 *
	 * @return bool
	 */
	public static function onAbuseFilterGenerateUserVars(
		AbuseFilterVariableHolder $vars,
		User $user,
		?RecentChange $rc
	) {
		$vars->setLazyLoadVar( 'global_user_groups', 'global-user-groups', [ 'user' => $user ] );
		return true;
	}

	/**
	 * Avoid filtering page moves during global rename
	 *
	 * @param AbuseFilterVariableHolder $vars
	 * @param Title $title
	 * @param User $user
	 * @param array &$skipReasons
	 *
	 * @return bool
	 */
	public function onAbuseFilterShouldFilterAction(
		AbuseFilterVariableHolder $vars,
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
