<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\Maintenance\DemoteIneligibleCentralUsers;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\RestrictedUserGroupConfigReader;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\DemoteIneligibleCentralUsers
 * @group Database
 */
class DemoteIneligibleCentralUsersTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return DemoteIneligibleCentralUsers::class;
	}

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthCentralWiki, null );
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthAutomaticGlobalGroups, [] );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [] );
	}

	public function testFatalErrorWhenNotOnCentralWiki(): void {
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthCentralWiki, 'otherwiki' );
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/must be run on the CentralAuth central wiki/' );
		$this->maintenance->execute();
	}

	public function testNoDemotableGroupsConfigured(): void {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				// 'demote' key intentionally absent
			],
		] );
		$this->maintenance->execute();
		$this->expectOutputString( "No global groups are configured for automatic demotion, exiting.\n" );
	}

	public function testGroupsOutsideCentralauthScopeAreIgnored(): void {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
				'scope' => [ RestrictedUserGroupConfigReader::SCOPE_LOCAL ],
			],
		] );
		$this->maintenance->execute();
		$this->expectOutputString( "No global groups are configured for automatic demotion, exiting.\n" );
	}

	public function testNoMembersInDemotableGroups(): void {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
		] );
		$this->maintenance->execute();
		$this->expectOutputString( "No members found in demotable groups, exiting.\n" );
	}

	public function testIneligibleUserIsDemoted(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward' ] );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertNotContains( 'steward', $caUser->getGlobalGroups() );
		$this->assertLogCount( 1 );
	}

	public function testEligibleUserIsNotDemoted(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
				'demote' => true,
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward' ] );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertContains( 'steward', $caUser->getGlobalGroups() );
		$this->expectOutputString( "No ineligible users found, exiting.\n" );
		$this->assertLogCount( 0 );
	}

	public function testDryRunDoesNotDemote(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward' ] );
		$this->maintenance->setOption( 'dry-run', true );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertContains( 'steward', $caUser->getGlobalGroups() );
		$this->expectOutputRegex( '/DRY RUN: 1 users would be affected normally/' );
		$this->assertLogCount( 0 );
	}

	public function testGroupsWithCentralauthScopeAreProcessed(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
				'scope' => [ GlobalGroupAssignmentService::RESTRICTION_SCOPE ],
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward' ] );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertNotContains( 'steward', $caUser->getGlobalGroups() );
		$this->assertLogCount( 1 );
	}

	public function testOnlyGroupsWithDemoteFlagAreProcessed(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->registerGlobalGroup( 'checkuser' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
			'checkuser' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				// 'demote' absent - checkuser must not be auto-demoted
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward', 'checkuser' ] );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertNotContains( 'steward', $caUser->getGlobalGroups() );
		$this->assertContains( 'checkuser', $caUser->getGlobalGroups() );
		$this->assertLogCount( 1 );
	}

	public function testUserDemotedFromMultipleGroups(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->registerGlobalGroup( 'checkuser' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
			'checkuser' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
		] );

		$caUser = $this->createGlobalUserInGroups( [ 'steward', 'checkuser' ] );

		$this->maintenance->execute();

		$caUser->invalidateCache();
		$this->assertNotContains( 'steward', $caUser->getGlobalGroups() );
		$this->assertNotContains( 'checkuser', $caUser->getGlobalGroups() );
		$this->assertLogCount( 1 );
	}

	public function testMultipleUsersAreDemoted(): void {
		$this->registerGlobalGroup( 'steward' );
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'steward' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 9999 ],
				'demote' => true,
			],
		] );

		$caUser1 = $this->createGlobalUserInGroups( [ 'steward' ] );
		$caUser2 = $this->createGlobalUserInGroups( [ 'steward' ] );

		$this->maintenance->execute();

		$caUser1->invalidateCache();
		$caUser2->invalidateCache();
		$this->assertNotContains( 'steward', $caUser1->getGlobalGroups() );
		$this->assertNotContains( 'steward', $caUser2->getGlobalGroups() );
		$this->expectOutputRegex( '/Removing groups from 2 users/' );
		$this->assertLogCount( 2 );
	}

	private function registerGlobalGroup( string $group ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->row( [ 'ggp_group' => $group, 'ggp_permission' => 'test' ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function createGlobalUserInGroups( array $groups ): CentralAuthUser {
		$caUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		foreach ( $groups as $group ) {
			$caUser->addToGlobalGroup( $group );
		}
		return $caUser;
	}

	private function assertLogCount( int $expectedCount ): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [ 'log_type' => 'gblrights', 'log_action' => 'usergroups' ] )
			->caller( __METHOD__ )
			->assertFieldValue( $expectedCount );
	}
}
