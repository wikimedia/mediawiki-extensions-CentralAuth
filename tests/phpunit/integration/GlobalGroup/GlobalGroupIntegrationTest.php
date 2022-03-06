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

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\TestingAccessWrapper;

/**
 * Tests to make sure the whole global group membership flow works correctly.
 *
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser::getGlobalRights
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser::loadGroups
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser::getClosestGlobalUserGroupExpiry
 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onUserGetRights
 *
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 * @group Database
 */
class GlobalGroupIntegrationTest extends CentralAuthUsingDatabaseTestCase {
	protected function setUp(): void {
		parent::setUp();

		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralDB( DB_PRIMARY );

		$caDbw->insert(
			'global_group_permissions',
			[
				[ 'ggp_group' => 'global-foos', 'ggp_permission' => 'some-global-right' ],
				[ 'ggp_group' => 'global-bars', 'ggp_permission' => 'some-other-right' ],
				[ 'ggp_group' => 'global-bazes', 'ggp_permission' => 'yet-another-right' ],
			],
			__METHOD__
		);
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

		$caDbw = CentralAuthServices::getDatabaseManager( $services )->getCentralDB( DB_PRIMARY );
		$caDbw->insert(
			'global_user_groups',
			[
				[ 'gug_user' => $caUser->getId(), 'gug_group' => 'global-foos',  'gug_expiry' => null, ],
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
			],
			__METHOD__
		);

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

	/**
	 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser::addToGlobalGroup
	 */
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
