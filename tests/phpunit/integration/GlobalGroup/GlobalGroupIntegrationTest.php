<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\TestingAccessWrapper;

/**
 * Tests to make sure the whole global group membership flow works correctly.
 *
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthHooks
 *
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 * @group Database
 */
class GlobalGroupIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralPrimaryDB();

		$caDbw->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'global-foos', 'ggp_permission' => 'some-global-right' ],
				[ 'ggp_group' => 'global-bars', 'ggp_permission' => 'some-other-right' ],
				[ 'ggp_group' => 'global-bazes', 'ggp_permission' => 'yet-another-right' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function getRegisteredTestUser(): User {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		$caUser->register( $testUser->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );

		return $testUser->getUser();
	}

	public function testReadGroupsFromDatabase() {
		$user = $this->getRegisteredTestUser();
		$caUser = TestingAccessWrapper::newFromObject( CentralAuthUser::getPrimaryInstance( $user ) );

		$services = $this->getServiceContainer();
		$permissionManager = $services->getPermissionManager();

		$this->assertFalse( $permissionManager->userHasRight( $user, 'some-global-right' ) );
		$this->assertFalse( $user->isAllowed( 'some-global-right' ) );

		$expiryFuture = time() + 1800;

		$caDbw = CentralAuthServices::getDatabaseManager( $services )->getCentralPrimaryDB();
		$caDbw->newInsertQueryBuilder()
			->insertInto( 'global_user_groups' )
			->rows( [
				[ 'gug_user' => $caUser->getId(), 'gug_group' => 'global-foos', 'gug_expiry' => null, ],
				[
					'gug_user' => $caUser->getId(),
					'gug_group' => 'global-bars',
					'gug_expiry' => $caDbw->timestamp( $expiryFuture ),
				],
				[
					'gug_user' => $caUser->getId(),
					'gug_group' => 'global-bazes',
					'gug_expiry' => $caDbw->timestamp( '20201201121212' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();

		$caUser->invalidateCache();
		$permissionManager->invalidateUsersRightsCache( $user );

		$this->assertTrue( $permissionManager->userHasRight( $user, 'some-global-right' ) );
		$this->assertTrue( $permissionManager->userHasRight( $user, 'some-other-right' ) );
		$this->assertFalse( $permissionManager->userHasRight( $user, 'yet-another-right' ) );

		$this->assertTrue( $user->isAllowed( 'some-global-right' ) );
		$this->assertTrue( $user->isAllowed( 'some-other-right' ) );
		$this->assertFalse( $user->isAllowed( 'yet-another-right' ) );

		$this->assertEquals( $expiryFuture, $caUser->getClosestGlobalUserGroupExpiry() );
	}

	public function testAddToGlobalGroups() {
		$user = $this->getRegisteredTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user );

		$this->assertFalse(
			$this->getServiceContainer()
				->getPermissionManager()
				->userHasRight( $user, 'some-global-right' )
		);

		$this->assertFalse( $user->isAllowed( 'some-global-right' ) );

		$caUser->addToGlobalGroup( 'global-foos' );

		$caUser->invalidateCache();
		$this->getServiceContainer()->getPermissionManager()->invalidateUsersRightsCache( $user );

		$this->assertTrue(
			$this->getServiceContainer()
				->getPermissionManager()
				->userHasRight( $user, 'some-global-right' )
		);

		$this->assertTrue( $user->isAllowed( 'some-global-right' ) );
	}
}
