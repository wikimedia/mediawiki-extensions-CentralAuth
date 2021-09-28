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

/**
 * Tests to make sure the whole global group membership flow works correctly.
 *
 * @covers CentralAuthUser::getGlobalRights
 * @covers CentralAuthUser::loadGroups
 * @covers CentralAuthHooks::onUserGetRights
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
			[ 'ggp_group' => 'global-foos', 'ggp_permission' => 'some-global-right' ],
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
		$caUser = CentralAuthUser::getPrimaryInstance( $user );

		$this->assertFalse(
			$this->getServiceContainer()
				->getPermissionManager()
				->userHasRight( $user, 'some-global-right' )
		);

		$this->assertFalse( $user->isAllowed( 'some-global-right' ) );

		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralDB( DB_PRIMARY );
		$caDbw->insert(
			'global_user_groups',
			[ 'gug_user' => $caUser->getId(), 'gug_group' => 'global-foos' ]
		);

		$caUser->invalidateCache();
		$this->getServiceContainer()->getPermissionManager()->invalidateUsersRightsCache( $user );

		$this->assertTrue(
			$this->getServiceContainer()
				->getPermissionManager()
				->userHasRight( $user, 'some-global-right' )
		);

		$this->assertTrue( $user->isAllowed( 'some-global-right' ) );
	}

	/**
	 * @covers CentralAuthUser::addToGlobalGroups
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

		$caUser->addToGlobalGroups( 'global-foos' );

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
