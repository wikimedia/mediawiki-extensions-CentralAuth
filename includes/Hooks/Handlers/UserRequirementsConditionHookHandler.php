<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\Hook\UserRequirementsConditionHook;
use MediaWiki\User\UserIdentity;

class UserRequirementsConditionHookHandler implements UserRequirementsConditionHook {

	/** @inheritDoc */
	public function onUserRequirementsCondition(
		$type,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest,
		?bool &$result
	): void {
		if ( $type !== APCOND_CA_INGLOBALGROUPS ) {
			return;
		}

		// If there is no central account for this user or if the central account is not attached to the local
		// user, then consider the local user to have no global groups.
		$centralUser = CentralAuthUser::getInstanceByName( $user->getName() );
		if ( !$centralUser->isAttached() ) {
			$result = false;
			return;
		}

		// Check if the central account for this user has all the required groups for the condition to be true.
		$matchingGroups = array_intersect( $args, $centralUser->getGlobalGroups() );
		$result = count( $matchingGroups ) === count( $args );
	}
}
