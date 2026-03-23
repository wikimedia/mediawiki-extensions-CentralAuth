<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Maintenance\AddToGlobalGroup;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\AddToGlobalGroup
 * @group Database
 */
class AddToGlobalGroupTest extends MaintenanceBaseTestCase {

	public function getMaintenanceClass() {
		return AddToGlobalGroup::class;
	}

	public function testExpiryIsInvalid(): void {
		$this->maintenance->setOption( 'expiry', 'Invalid timestamp' );
		$this->expectOutputRegex( '/Invalid expiry/' );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public function testUserIsInvalid(): void {
		$this->maintenance->setArg( 'user', 'InvalidUser#1234:' );
		$this->expectOutputRegex( '/Invalid username/' );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public function testUserDoesNotExistCentrally(): void {
		$this->maintenance->setArg( 'user', $this->getMutableTestUser()->getUserIdentity()->getName() );
		$this->expectOutputRegex( '/User does not exist centrally/' );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public function testNoChangesMadeWhenGlobalGroupDoesNotExist(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->assertSame(
			[],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			'Central user should have no groups at the start of the test'
		);

		$this->maintenance->setArg( 'user', $centralAuthUser->getName() );
		$this->maintenance->setArg( 'group', 'non-existing-group' );
		$this->maintenance->setOption( 'reason', 'Add test' );

		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();

		$this->assertSame(
			[],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			'Central user should not have a non-existing global group added'
		);

		$this->expectOutputRegex( '/No changes made/' );
	}

	/** @dataProvider provideUserAddedToGroup */
	public function testUserAddedToGroup(
		string|null $expiry,
		string|null $expectedExpiry
	): void {
		ConvertibleTimestamp::setFakeTime( '20250605040302' );
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthAutomaticGlobalGroups, [] );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->row( [ 'ggp_group' => 'steward', 'ggp_permission' => 'centralauth-lock' ] )
			->caller( __METHOD__ )
			->execute();

		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->assertSame(
			[],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			'Central user should have no groups at the start of the test'
		);

		$this->maintenance->setArg( 'user', $centralAuthUser->getName() );
		$this->maintenance->setArg( 'group', 'steward' );
		$this->maintenance->setOption( 'reason', 'Adding steward for test' );
		if ( $expiry !== null ) {
			$this->maintenance->setOption( 'expiry', $expiry );
		}

		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();

		$this->assertArrayEquals(
			[ 'steward' => $expectedExpiry ],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			false, true,
			'User should have steward group after maintenance script run'
		);

		$this->expectOutputRegex( '/Success/' );
	}

	public static function provideUserAddedToGroup(): array {
		return [
			'Expiry is indefinite' => [ null, null ],
			'Expiry is 1 year' => [ '1 year', '20260605040302' ],
			'Expiry is 23 seconds' => [ '23 seconds', '20250605040325' ]
		];
	}

	public function testUserRemovedFromGroup(): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'centralauth-lock' ],
				[ 'ggp_group' => 'another-group', 'ggp_permission' => 'centralauth-lock' ],
			] )
			->caller( __METHOD__ )
			->execute();

		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$centralAuthUser->addToGlobalGroup( 'steward' );
		$centralAuthUser->addToGlobalGroup( 'another-group' );

		$this->assertArrayEquals(
			[ 'steward' => null, 'another-group' => null ],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			false, true,
			'User should have steward group before maintenance script run'
		);

		$this->maintenance->setArg( 'user', $centralAuthUser->getName() );
		$this->maintenance->setArg( 'group', 'steward' );
		$this->maintenance->setOption( 'reason', 'Removing steward for test' );
		$this->maintenance->setOption( 'remove', 1 );

		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();

		$this->assertArrayEquals(
			[ 'another-group' => null ],
			$centralAuthUser->getGlobalGroupsWithExpiration(),
			false, true,
			'User should have steward group after maintenance script run'
		);

		$this->expectOutputRegex( '/Success/' );
	}
}
