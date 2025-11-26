<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\Hook\UserGroupsChangedHook;

class UserGroupsHookHandler implements UserGroupsChangedHook {

	public function __construct(
		private readonly GlobalGroupAssignmentService $globalGroupAssignmentService
	) {
	}

	/**
	 * Add users to (or remove users from) global groups, if configured to do so.
	 *
	 * @inheritDoc
	 */
	public function onUserGroupsChanged(
		$user,
		$added,
		$removed,
		$performer,
		$reason,
		$oldUGMs,
		$newUGMs
	) {
		$globalUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( !( $globalUser->exists() && $globalUser->isAttached() ) ) {
			return;
		}

		// Clear cache for CentralAuthUser::getLocalGroups()
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $cache->makeGlobalKey( 'centralauthuser-getlocalgroups', $globalUser->getId() ) );

		// T387523: Return if no performer is set to avoid throwing an exception, which occurs when
		// the user is autopromoted into a group through $wgAutopromoteOnce. Updating automatic
		// global groups on autopromote is not supported.
		if ( !$performer ) {
			return;
		}

		// Automatic global groups must be updated, even if this user does not have
		// the rights to do so. Do now rather than via a maintenance script so that
		// the change is made immediately and the performer who changed the local
		// group is logged.
		$authority = new UltimateAuthority( $performer );
		$reason = Message::newFromKey( 'centralauth-automatic-global-groups-reason-local' )
			->inContentLanguage()
			->text();

		// We set add=[] and remove=[] on purpose. This call is made so that the automatic
		// global groups are recalculated and applied (so that even if we seemingly request
		// no change to global groups, a change may still occur).
		$this->globalGroupAssignmentService->saveChangesToUserGroups( $authority, $globalUser, [], [], [], $reason );
	}
}
