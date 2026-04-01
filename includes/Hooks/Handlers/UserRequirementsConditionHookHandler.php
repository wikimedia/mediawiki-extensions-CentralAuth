<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\Hook\UserRequirementsConditionDisplayHook;
use MediaWiki\User\Hook\UserRequirementsConditionHook;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageSpecifier;

class UserRequirementsConditionHookHandler implements
	UserRequirementsConditionHook,
	UserRequirementsConditionDisplayHook
{

	/** @inheritDoc */
	public function onUserRequirementsCondition(
		string|int $type,
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

	/** @inheritDoc */
	public function onUserRequirementsConditionDisplay(
		string|int $type,
		array $args,
		IContextSource $context,
		?MessageSpecifier &$message
	): void {
		if ( $type !== APCOND_CA_INGLOBALGROUPS ) {
			return;
		}

		$lang = $context->getLanguage();
		$groupNames = [];
		foreach ( $args as $group ) {
			$groupNames[] = $lang->getGroupName( $group );
		}
		$message = $context->msg( 'listgrouprights-restrictedgroups-cond-ca-inglobalgroups' );
		$message->params( count( $args ), $lang->listToText( $groupNames ) );
	}
}
