<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserGroupsHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserGroupsHookHandler
 */
class UserGroupsHookHandlerTest extends MediaWikiIntegrationTestCase {
	use MockWikiMapTrait;

	public function addDBDataOnce() {
		// Set up defined global groups
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'automatic-global-group', 'ggp_permission' => 'read' ],
				[ 'ggp_group' => 'existing-global-group', 'ggp_permission' => 'read' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function getHandler() {
		return new UserGroupsHookHandler(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserNamePrefixSearch(),
			$this->getServiceContainer()->getUserNameUtils(),
			CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() ),
			CentralAuthServices::getGlobalGroupLookup( $this->getServiceContainer() )
		);
	}

	private function getTargetUser() {
		// Set up a global user that has an attached local user
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$globalUser = CentralAuthUser::getPrimaryInstance( $user );
		$globalUser->register( $testUser->getPassword(), null );
		$globalUser->attach( WikiMap::getCurrentWikiId() );

		return $user;
	}

	/** @dataProvider provideGlobalGroupMembershipPermission */
	public function testOnUserGroupsChanged( $hasPermission ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'new-local-group' => [ 'automatic-global-group' ],
			'existing-global-group' => [ 'automatic-global-group' ],
		] );

		$this->mockWikiMap();

		$user = $this->getTargetUser();
		$handler = $this->getHandler();

		$this->setGroupPermissions( 'sysop', 'globalgroupmembership', $hasPermission );
		$performer = $this->getTestSysop()->getUser();
		RequestContext::getMain()->setUser( $performer );

		// Add the user to the local group, then run the hook.
		// The user should be added to the automatic global group.

		$this->getServiceContainer()->getUserGroupManager()
			->addUserToGroup( $user, 'new-local-group' );

		$handler->onUserGroupsChanged(
			$user,
			[ 'new-local-group' ],
			[],
			$performer,
			'Test reason',
			[],
			[]
		);

		// Re-fetch the global user with the new groups
		CentralAuthServices::getUserCache()->clear();
		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $user->getName() );

		$this->assertSame( [ 'automatic-global-group' ], $globalUser->getGlobalGroups() );

		$globalUser->addToGlobalGroup( 'existing-global-group' );

		// Remove the user from the local group, then run the hook.
		// The user should not be removed from the automatic global group, because of the
		// other global group.

		$this->getServiceContainer()->getUserGroupManager()
			->removeUserFromGroup( $user, 'new-local-group' );

		$handler->onUserGroupsChanged(
			$user,
			[],
			[ 'new-local-group' ],
			$performer,
			'Test reason',
			[],
			[]
		);

		// Re-fetch the global user with the new groups
		CentralAuthServices::getUserCache()->clear();
		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $user->getName() );

		$this->assertSame(
			[ 'automatic-global-group', 'existing-global-group' ],
			$globalUser->getGlobalGroups()
		);
	}

	public static function provideGlobalGroupMembershipPermission() {
		return [
			'Groups are updated when the performer has permission' => [ true ],
			'Groups are updated when the performer does not have permission' => [ false ],
		];
	}

	public function testOnUserGroupsChangedForAutopromotion() {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'new-local-group' => [ 'automatic-global-group' ],
			'existing-global-group' => [ 'automatic-global-group' ],
		] );
		$globalGroupManager = $this->createNoOpMock( CentralAuthAutomaticGlobalGroupManager::class );
		$this->setService( 'CentralAuth.CentralAuthAutomaticGlobalGroupManager', $globalGroupManager );
		$this->getHandler()->onUserGroupsChanged(
			$this->getTargetUser(),
			[],
			[],
			false,
			'Test reason',
			[],
			[]
		);
	}

	public function testOnUserGroupsChangedNonexistentUser() {
		$globalGroupManager = $this->createNoOpMock( CentralAuthAutomaticGlobalGroupManager::class );
		$this->setService( 'CentralAuth.CentralAuthAutomaticGlobalGroupManager', $globalGroupManager );

		// Simulate running the hook with a local user without a global user
		$this->getHandler()->onUserGroupsChanged(
			$this->getTestUser()->getUser(),
			[],
			[],
			$this->getTestUser()->getUser(),
			'Test reason',
			[],
			[]
		);
	}
}
