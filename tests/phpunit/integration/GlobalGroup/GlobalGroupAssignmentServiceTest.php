<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService
 * @group Database
 */
class GlobalGroupAssignmentServiceTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;
	use MockWikiMapTrait;
	use TempUserTestTrait;

	private function getRegisteredTestUser( ?User $user = null ): CentralAuthUser {
		$user = $user ?? $this->getMutableTestUser()->getUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		$caUser->register( '', null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		return $caUser;
	}

	/** @dataProvider provideTargetCanHaveGroups */
	public function testTargetCanHaveGroups( bool $isRegistered ): void {
		$target = $isRegistered ? $this->getRegisteredTestUser() : new CentralAuthUser( 'Test user' );
		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		$this->assertEquals( $isRegistered, $service->targetCanHaveUserGroups( $target ) );
	}

	public static function provideTargetCanHaveGroups(): array {
		return [
			'Registered user' => [ true ],
			'Unregistered user' => [ false ],
		];
	}

	public function testTempAccountsCannotHaveGroups(): void {
		$this->enableAutoCreateTempUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		$target = $this->getRegisteredTestUser( $tempUser );

		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		$this->assertFalse( $service->targetCanHaveUserGroups( $target ) );
		$this->assertFalse( $service->userCanChangeRights( $this->mockAnonUltimateAuthority(), $target ) );
	}

	/** @dataProvider provideUserCanChangeRights */
	public function testUserCanChangeRights( bool $hasGlobalGroupsRight ): void {
		$groupLookupMock = $this->createMock( GlobalGroupLookup::class );
		$groupLookupMock->method( 'getDefinedGroups' )
			->willReturn( [ 'steward' ] );

		$this->setService( 'CentralAuth.GlobalGroupLookup', $groupLookupMock );
		$service = CentralAuthServices::getGlobalGroupAssignmentService();

		$performer = $this->mockRegisteredAuthorityWithPermissions(
			$hasGlobalGroupsRight ? [ 'globalgroupmembership' ] : []
		);
		$target = $this->getRegisteredTestUser();
		$canChange = $service->userCanChangeRights( $performer, $target );
		$this->assertSame( $hasGlobalGroupsRight, $canChange );
	}

	public static function provideUserCanChangeRights(): array {
		return [
			'Insufficient rights' => [ false ],
			'Has sufficient rights' => [ true ],
		];
	}

	public function testGetChangeableGroupsWithAutomatic(): void {
		$groupLookupMock = $this->createMock( GlobalGroupLookup::class );
		$groupLookupMock->method( 'getDefinedGroups' )
			->willReturn( [ 'steward', 'autogroup' ] );
		$this->setService( 'CentralAuth.GlobalGroupLookup', $groupLookupMock );

		$autoGroupMock = $this->createMock( CentralAuthAutomaticGlobalGroupManager::class );
		$autoGroupMock->method( 'getAutomaticGlobalGroups' )
			->willReturn( [ 'autogroup' ] );
		$this->setService( 'CentralAuth.CentralAuthAutomaticGlobalGroupManager', $autoGroupMock );

		$service = CentralAuthServices::getGlobalGroupAssignmentService();

		$performer = $this->mockAnonUltimateAuthority();
		$target = $this->getRegisteredTestUser();

		$groups = $service->getChangeableGroups( $performer, $target );
		$this->assertSame( [
			'add' => [ 'steward' ],
			'remove' => [ 'steward' ],
			'restricted' => [
				'autogroup' => [
					'condition-met' => false,
					'ignore-condition' => false,
					'message' => 'centralauth-globalgroupperms-automatic-group-reason'
				],
			],
		], $groups );
	}

	public function testSaveUserGroups(): void {
		$add = [ 'shortened-group', 'added-group', 'autogroup' ];
		$remove = [ 'removed-group' ];
		$newExpiries = [
			'shortened-group' => '20290101000000',
			'added-group' => null,
		];

		$groupLookupMock = $this->createMock( GlobalGroupLookup::class );
		$groupLookupMock->method( 'getDefinedGroups' )
			->willReturn( [ 'static-group', 'shortened-group', 'removed-group', 'added-group', 'autogroup' ] );
		$this->setService( 'CentralAuth.GlobalGroupLookup', $groupLookupMock );

		$autoGroupMock = $this->createMock( CentralAuthAutomaticGlobalGroupManager::class );
		$autoGroupMock->method( 'getAutomaticGlobalGroups' )
			->willReturn( [ 'autogroup' ] );
		$this->setService( 'CentralAuth.CentralAuthAutomaticGlobalGroupManager', $autoGroupMock );

		$performer = $this->mockRegisteredUltimateAuthority();
		$target = $this->getRegisteredTestUser();
		$target->addToGlobalGroup( 'static-group' );
		$target->addToGlobalGroup( 'shortened-group' );
		$target->addToGlobalGroup( 'removed-group' );

		$hookCalled = false;
		$this->setTemporaryHook(
			'CentralAuthGlobalUserGroupMembershipChanged',
			function (
				CentralAuthUser $target, array $oldGroups, array $newGroups
			) use ( &$hookCalled ) {
				$this->assertSame( [
					'removed-group' => null,
					'shortened-group' => null,
					'static-group' => null,
				], $oldGroups );
				$this->assertSame( [
					'added-group' => null,
					'shortened-group' => '20290101000000',
					'static-group' => null,
				], $newGroups );
				$hookCalled = true;
			}
		);

		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		[ $added, $removed ] = $service->saveChangesToUserGroups( $performer, $target, $add, $remove, $newExpiries );

		$this->assertEquals( [ 'shortened-group', 'added-group' ], $added );
		$this->assertEquals( [ 'removed-group' ], $removed );
		$this->assertTrue( $hookCalled, 'Hook was not called' );
	}

	/** @dataProvider provideSaveWithAutomaticGroup */
	public function testSaveWithAutomaticGroup( $localGroups, $expected ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'local-group' => [ 'group-one' ],
			'group-two' => [ 'group-one' ],
		] );

		$this->mockWikiMap();

		$user = $this->getMutableTestUser( $localGroups );
		$target = $this->getRegisteredTestUser( $user->getUser() );
		$performer = $this->mockRegisteredUltimateAuthority();

		$groupLookupMock = $this->createMock( GlobalGroupLookup::class );
		$groupLookupMock->method( 'getDefinedGroups' )
			->willReturn( [ 'group-one', 'group-two', 'group-three' ] );
		$this->setService( 'CentralAuth.GlobalGroupLookup', $groupLookupMock );

		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		$service->saveChangesToUserGroups( $performer, $target, [ 'group-two' ], [], [ 'group-two' => null ] );

		// The automatic global group is added
		$this->assertEquals( [ 'group-one', 'group-two' ], $target->getGlobalGroups() );

		$service->saveChangesToUserGroups( $performer, $target, [], [ 'group-two' ], [] );

		// The automatic global group is removed or not, depending on the local group
		$this->assertEquals( $expected, $target->getGlobalGroups() );
	}

	public static function provideSaveWithAutomaticGroup() {
		return [
			'Automatic global group is not removed if user has a local group' => [
				[ 'local-group' ],
				[ 'group-one' ]
			],
			'Automatic global group is removed if user has no other groups' => [
				[],
				[]
			],
		];
	}

	public function testAdjustForAutomaticGlobalGroups() {
		$localGroup = 'localGroup';
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			$localGroup => [ 'group-one' ],
		] );

		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		$wrappedService = TestingAccessWrapper::newFromObject( $service );

		$localGroupMembership = $this->createMock( UserGroupMembership::class );
		$localGroupMembership->method( 'getGroup' )
			->willReturn( $localGroup );
		$localGroupMembership->method( 'getExpiry' )
			->willReturn( '20230405060707' );

		$externalGroupMembership = $this->createMock( UserGroupMembership::class );
		$externalGroupMembership->method( 'getGroup' )
			->willReturn( $localGroup );
		$externalGroupMembership->method( 'getExpiry' )
			->willReturn( '20240405060707' );

		$user = $this->createMock( CentralAuthUser::class );
		$user->method( 'queryAttached' )
			->willReturn( [
				'localWiki' => [
					'groupMemberships' => [ $localGroupMembership ],
				],
				'externalWiki' => [
					'groupMemberships' => [ $externalGroupMembership ],
				]
			] );

		$assignedGroups = [];
		$add = [];
		$remove = [];
		$groupExpiries = [];
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $wrappedService->object );
		$methodReflection = $classReflection->getMethod( 'adjustForAutomaticGlobalGroups' );
		$methodReflection->invokeArgs( $wrappedService->object, [
			$user, $assignedGroups, &$add, &$remove, &$groupExpiries,
		] );

		$this->assertEquals( [ 'group-one' ], $add );
		$this->assertEquals( [], $remove );
		$this->assertEquals( [ 'group-one' => '20240405060707' ], $groupExpiries );
	}

	/** @dataProvider provideGetLogReason */
	public function testGetLogReason( $expected, $reason, $added, $removed ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'global-group-1' => [ 'automatic-group-1' ],
			'global-group-2' => [ 'automatic-group-2' ],
		] );

		$this->setUserLang( 'qqx' );
		$this->setContentLang( 'qqx' );

		$service = CentralAuthServices::getGlobalGroupAssignmentService();
		$wrappedService = TestingAccessWrapper::newFromObject( $service );

		$this->assertSame(
			$expected,
			$wrappedService->getLogReason( $reason, $added, $removed )
		);
	}

	public static function provideGetLogReason() {
		return [
			'No automatic groups are changed, reason is unchanged' => [
				'Test reason',
				'Test reason',
				[ 'global-group-1' ],
				[ 'global-group-2' ],
			],
			'Automatic groups are added, reason is updated' => [
				'Test reason(semicolon-separator)(centralauth-automatic-global-groups-reason-global)',
				'Test reason',
				[ 'automatic-group-1', 'automatic-group-2' ],
				[],
			],
			'Automatic groups are removed, reason is updated' => [
				'Test reason(semicolon-separator)(centralauth-automatic-global-groups-reason-global)',
				'Test reason',
				[],
				[ 'automatic-group-1', 'automatic-group-2' ],
			],
			'Automatic groups are added after local change, reason unchanged' => [
				'(centralauth-automatic-global-groups-reason-local)',
				'(centralauth-automatic-global-groups-reason-local)',
				[ 'automatic-group-1', 'automatic-group-2' ],
				[],
			],
		];
	}
}
