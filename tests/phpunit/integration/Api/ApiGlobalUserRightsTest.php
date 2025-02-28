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

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Api;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\WikiMap\WikiMap;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Api\ApiGlobalUserRights
 * @group Database
 */
class ApiGlobalUserRightsTest extends ApiTestCase {
	use MockAuthorityTrait;
	use MockWikiMapTrait;

	private function getAttachedTestUserWithLocalGroup( $group = null ) {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		$caUser->register( $testUser->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );

		if ( $group ) {
			$this->getServiceContainer()->getUserGroupManager()
				->addUserToGroup( $user, $group );
		}

		return $user;
	}

	/** @dataProvider provideExecuteWithAutomaticGlobalGroupsAndLocalGroup */
	public function testExecuteWithAutomaticGlobalGroupsAndLocalGroup( $localGroup, $expectedRemoved ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'localgroup' => [ 'automaticglobalgroup' ],
			'globalgroup' => [ 'automaticglobalgroup' ],
		] );

		$this->mockWikiMap();

		$user = $this->getAttachedTestUserWithLocalGroup( $localGroup );
		$username = $user->getName();

		$performer = $this->mockRegisteredAuthorityWithPermissions( [
			'globalgroupmembership',
		] );

		$result = $this->doApiRequestWithToken(
			[
				'action' => 'globaluserrights',
				'user' => $username,
				'add' => 'globalgroup',
			],
			null,
			$performer
		);

		// The automatic global group is added
		$this->assertArrayEquals(
			[ 'automaticglobalgroup', 'globalgroup' ],
			$result[0]['globaluserrights']['added']
		);

		$this->assertArrayEquals(
			[],
			$result[0]['globaluserrights']['removed']
		);

		$result = $this->doApiRequestWithToken(
			[
				'action' => 'globaluserrights',
				'user' => $username,
				'remove' => 'globalgroup',
			],
			null,
			$performer
		);

		$this->assertArrayEquals(
			[],
			$result[0]['globaluserrights']['added']
		);

		// The automatic global group is removed or not, depending on the local group
		$this->assertArrayEquals(
			$expectedRemoved,
			$result[0]['globaluserrights']['removed']
		);
	}

	public function provideExecuteWithAutomaticGlobalGroupsAndLocalGroup() {
		return [
			'Automatic global group is not removed if user has a local group' => [
				'localgroup',
				[ 'globalgroup' ]
			],
			'Automatic global group is removed if user has no other groups' => [
				null,
				[ 'automaticglobalgroup', 'globalgroup' ]
			],
		];
	}

	public function testExecuteWithAutomaticGlobalGroupsAndUnrelatedGlobalGroup() {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'globalgroup' => [ 'automaticglobalgroup' ],
		] );

		$user = $this->getAttachedTestUserWithLocalGroup();
		$username = $user->getName();

		$performer = $this->mockRegisteredAuthorityWithPermissions( [
			'globalgroupmembership',
		] );

		$result = $this->doApiRequestWithToken(
			[
				'action' => 'globaluserrights',
				'user' => $username,
				'add' => 'globalgroup|unrelatedglobalgroup',
			],
			null,
			$performer
		);

		// The automatic global group is added
		$this->assertArrayEquals(
			[ 'automaticglobalgroup', 'globalgroup', 'unrelatedglobalgroup' ],
			$result[0]['globaluserrights']['added']
		);

		$this->assertArrayEquals(
			[],
			$result[0]['globaluserrights']['removed']
		);

		$result = $this->doApiRequestWithToken(
			[
				'action' => 'globaluserrights',
				'user' => $username,
				'remove' => 'unrelatedglobalgroup',
			],
			null,
			$performer
		);

		$this->assertArrayEquals(
			[],
			$result[0]['globaluserrights']['added']
		);

		// The automatic global group is removed or not, depending on the local group
		$this->assertArrayEquals(
			[ 'unrelatedglobalgroup' ],
			$result[0]['globaluserrights']['removed']
		);
	}

	public function addDBDataOnce() {
		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralPrimaryDB();

		$caDbw->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'automaticglobalgroup', 'ggp_permission' => 'test' ],
				[ 'ggp_group' => 'globalgroup', 'ggp_permission' => 'test' ],
				[ 'ggp_group' => 'unrelatedglobalgroup', 'ggp_permission' => 'test' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
