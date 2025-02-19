<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;

class UserGroupsHookHandler implements UserGroupsChangedHook {

	private TitleFactory $titleFactory;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private CentralAuthAutomaticGlobalGroupManager $automaticGroupManager;
	private GlobalGroupLookup $globalGroupLookup;

	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		CentralAuthAutomaticGlobalGroupManager $automaticGroupManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		$this->titleFactory = $titleFactory;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->automaticGroupManager = $automaticGroupManager;
		$this->globalGroupLookup = $globalGroupLookup;
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

		$globalGroupsToAdd = [];
		$globalGroupsToRemove = [];

		$assignedGroups = array_merge(
			$globalUser->getLocalGroups(),
			$globalUser->getGlobalGroups()
		);

		$this->automaticGroupManager->handleAutomaticGlobalGroups(
			$assignedGroups,
			$globalGroupsToAdd,
			$globalGroupsToRemove
		);

		// To do: Have a service that the special page, APIs and hook handler call: T270857
		$specialPage = new SpecialGlobalGroupMembership(
			$this->titleFactory,
			$this->userNamePrefixSearch,
			$this->userNameUtils,
			$this->globalGroupLookup
		);

		// Automatic global groups must be updated, even if this user does not have
		// the rights to do so. Do now rather than via a maintenance script so that
		// the change is made immediately and the performer who changed the local
		// group is logged.
		$context = new DerivativeContext( $specialPage->getContext() );
		$context->setAuthority( new UltimateAuthority( $performer ) );
		$specialPage->setContext( $context );

		$reason = Message::newFromKey( 'centralauth-automatic-global-groups-reason' )
			->inContentLanguage();
		$specialPage->doSaveUserGroups(
			$globalUser,
			$globalGroupsToAdd,
			$globalGroupsToRemove,
			$reason
		);
	}

}
