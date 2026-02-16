<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\User;

/**
 * Run hooks provided by CentralAuth.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthHookRunner implements
	CentralAuthIsUIReloadRecommendedHook,
	CentralAuthPostLoginRedirectHook,
	CentralAuthLoginRedirectDataHook,
	CentralAuthSilentLoginRedirectHook,
	CentralAuthWikiListHook,
	CentralAuthInfoFieldsHook,
	CentralAuthGlobalUserGroupMembershipChangedHook,
	CentralAuthGlobalUserLockStatusChangedHook,
	CentralAuthUserVisibilityChangedHook,
	CentralAuthAccountDeletedHook
{

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload ) {
		$this->hookContainer->run(
			'CentralAuthIsUIReloadRecommended',
			[ $user, &$recommendReload ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo,
		string &$returnToQuery,
		bool $unused,
		string $type,
		string &$unused2
	) {
		return $this->hookContainer->run(
			'CentralAuthPostLoginRedirect',
			[ &$returnTo, &$returnToQuery, $unused, $type, &$unused2 ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthLoginRedirectData( CentralAuthUser $centralAuthUser, array &$data ) {
		$this->hookContainer->run(
			'CentralAuthLoginRedirectData',
			[ $centralAuthUser, &$data ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthSilentLoginRedirect( CentralAuthUser $centralAuthUser, string &$url, array $data ) {
		$this->hookContainer->run(
			'CentralAuthSilentLoginRedirect',
			[ $centralAuthUser, &$url, $data ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthWikiList( ?array &$wikiList ) {
		$this->hookContainer->run(
			'CentralAuthWikiList',
			[ &$wikiList ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthInfoFields(
		CentralAuthUser $centralAuthUser, IContextSource $context, array &$attribs
	) {
		$this->hookContainer->run(
			'CentralAuthInfoFields',
			[ $centralAuthUser, $context, &$attribs ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthGlobalUserGroupMembershipChanged(
		CentralAuthUser $centralAuthUser, array $oldGroups, array $newGroups
	) {
		$this->hookContainer->run(
			'CentralAuthGlobalUserGroupMembershipChanged',
			[ $centralAuthUser, $oldGroups, $newGroups ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthGlobalUserLockStatusChanged(
		CentralAuthUser $centralAuthUser,
		bool $isLocked
	): void {
		$this->hookContainer->run(
			'CentralAuthGlobalUserLockStatusChanged',
			[ $centralAuthUser, $isLocked ],
			[ 'abortable' => false ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthUserVisibilityChanged(
		CentralAuthUser $centralAuthUser,
		int $newVisibility
	): void {
		$this->hookContainer->run(
			'CentralAuthUserVisibilityChanged',
			[ $centralAuthUser, $newVisibility ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthAccountDeleted( int $userID, string $userName ): void {
		$this->hookContainer->run(
			'CentralAuthAccountDeleted',
			[ $userID, $userName ]
		);
	}
}
