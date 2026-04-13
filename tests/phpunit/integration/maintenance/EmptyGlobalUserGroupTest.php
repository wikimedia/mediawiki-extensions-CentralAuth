<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\Maintenance\EmptyGlobalUserGroup;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\EmptyGlobalUserGroup
 * @group Database
 */
class EmptyGlobalUserGroupTest extends MaintenanceBaseTestCase {

	/** @inheritDoc */
	public function getMaintenanceClass() {
		return EmptyGlobalUserGroup::class;
	}

	public function testWhenGroupIsAlreadyEmpty(): void {
		$this->maintenance->setArg( 'group', 'steward' );
		$this->maintenance->execute();
		$this->expectOutputString( "All done, 0 users removed from steward in total.\n" );
	}

	public function testWhenScriptEmptiesGroup(): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'steward', 'ggp_permission' => 'centralauth-lock' ],
				[ 'ggp_group' => 'another-group', 'ggp_permission' => 'centralauth-lock' ],
			] )
			->caller( __METHOD__ )
			->execute();

		$firstCentralTestUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$firstCentralTestUser->addToGlobalGroup( 'steward' );
		$firstCentralTestUser->addToGlobalGroup( 'another-group' );

		$secondCentralTestUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$secondCentralTestUser->addToGlobalGroup( 'steward' );

		$thirdCentralTestUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$thirdCentralTestUser->addToGlobalGroup( 'steward' );

		$this->assertGroupMemberCount( 'steward', 3 );
		$this->assertGroupMemberCount( 'another-group', 1 );

		$this->maintenance->loadWithArgv( [ '--batch-size', 2 ] );
		$this->maintenance->setArg( 'group', 'steward' );
		$this->maintenance->execute();

		$this->assertGroupMemberCount( 'steward', 0 );
		$this->assertGroupMemberCount( 'another-group', 1 );

		$this->expectOutputRegex(
			"/Deleted 2 group members so far.*waiting for replication to catch up.*\n" .
			"Deleted 3 group members so far.*waiting for replication to catch up.*\n" .
			"All done, 3 users removed from steward in total/"
		);
	}

	private function assertGroupMemberCount( string $group, int $expectedCount ): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'global_user_groups' )
			->where( [ 'gug_group' => $group ] )
			->caller( __METHOD__ )
			->assertFieldValue( $expectedCount );
	}
}
