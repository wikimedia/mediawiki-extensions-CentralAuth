<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager
 */
class CentralAuthAutomaticGlobalGroupManagerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideHandleAutomaticGlobalGroups */
	public function testHandleAutomaticGlobalGroups(
		$assignedGroups,
		$config,
		$expectedGroupsToAdd,
		$expectedGroupsToRemove,
		$expectedExpiriesToChange,
		$expectedAnyChanged,
	) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', $config );

		$groupsToAdd = [];
		$groupsToRemove = [];
		$expiriesToChange = [];

		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$anyChanged = $automaticGroupManager->handleAutomaticGlobalGroups(
				$assignedGroups,
				$groupsToAdd,
				$groupsToRemove,
				$expiriesToChange
			);

		$this->assertSame( $expectedGroupsToAdd, $groupsToAdd );
		$this->assertSame( $expectedGroupsToRemove, $groupsToRemove );
		$this->assertSame( $expectedExpiriesToChange, $expiriesToChange );
		$this->assertSame( $expectedAnyChanged, $anyChanged );
	}

	public static function provideHandleAutomaticGlobalGroups() {
		return [
			'Having a group causes an automatic group to be added' => [
				'assignedGroups' => [ 'test-group' => null ],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [
					'automatic-group' => null,
				],
				'expectedAnyChanged' => true,
			],
			'Not having a group causes an automatic group to be removed' => [
				'assignedGroups' => [ 'automatic-group' => null ],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [ 'automatic-group' ],
				'expectedExpiriesToChange' => [],
				'expectedAnyChanged' => true,
			],
			'If two local groups add the same global group, only having one adds the automatic group' => [
				'assignedGroups' => [ 'test-group-1' => null ],
				'config' => [
					'test-group-1' => [ 'automatic-group' ],
					'test-group-2' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [
					'automatic-group' => null,
				],
				'expectedAnyChanged' => true,
			],
			'Having an automatic group does not cause another automatic group to be updated' => [
				'assignedGroups' => [ 'test-group' => null, 'automatic-group-1' => null ],
				'config' => [
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [],
				'expectedAnyChanged' => false,
			],
			'Adding an automatic group does not cause another automatic group to be updated' => [
				'assignedGroups' => [ 'test-group' => null ],
				'config' => [
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				'expectedGroupsToAdd' => [ 'automatic-group-1' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [
					'automatic-group-1' => null,
				],
				'expectedAnyChanged' => true,
			],
			'Having a group with an expiry adds the automatic group with the expiry' => [
				'assignedGroups' => [ 'test-group' => '20230405060707' ],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [ 'automatic-group' => '20230405060707' ],
				'expectedAnyChanged' => true,
			],
			'Having groups with expiries adds the automatic group with the latest expiry' => [
				'assignedGroups' => [
					'test-group-1' => '20230405060707',
					'test-group-2' => '20220405060707'
				],
				'config' => [
					'test-group-1' => [ 'automatic-group' ],
					'test-group-2' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [ 'automatic-group' => '20230405060707' ],
				'expectedAnyChanged' => true,
			],
			'Having first group with expiry adds the automatic group with no expiry' => [
				'assignedGroups' => [
					'test-group-1' => '20230405060707',
					'test-group-2' => null
				],
				'config' => [
					'test-group-1' => [ 'automatic-group' ],
					'test-group-2' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [
					'automatic-group' => null,
				],
				'expectedAnyChanged' => true,
			],
			'Updating the expiry of a group updates the expiry of the automatic group' => [
				'assignedGroups' => [
					'test-group' => '20230405060707',
					'automatic-group' => '20220405060707'
				],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [ 'automatic-group' => '20230405060707' ],
				'expectedAnyChanged' => true,
			],
		];
	}

	/** @dataProvider provideHandleAutomaticGlobalGroupsManually */
	public function testHandleAutomaticGlobalGroupsManually(
		$assignedGroups,
		$groupsToAdd,
		$groupsToRemove,
		$expiriesToChange,
		$config,
		$expectedGroupsToAdd,
		$expectedGroupsToRemove,
		$expectedExpiriesToChange,
		$expectedAnyChanged
	) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', $config );

		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$anyChanged = $automaticGroupManager->handleAutomaticGlobalGroups(
				$assignedGroups,
				$groupsToAdd,
				$groupsToRemove,
				$expiriesToChange
			);

		$this->assertSame( $expectedGroupsToAdd, $groupsToAdd );
		$this->assertSame( $expectedGroupsToRemove, $groupsToRemove );
		$this->assertSame( $expectedExpiriesToChange, $expiriesToChange );
		$this->assertSame( $expectedAnyChanged, $anyChanged );
	}

	public static function provideHandleAutomaticGlobalGroupsManually() {
		return [
			'An automatic group cannot be manually removed if it should be present' => [
				'assignedGroups' => [ 'test-group' => null, 'automatic-group' => null ],
				'groupsToAdd' => [],
				'groupsToRemove' => [ 'automatic-group' ],
				'expiriesToChange' => [],
				'config' => [
					'test-group' => [ 'automatic-group' ],
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [],
				'expectedAnyChanged' => true,
			],
			'An automatic group cannot be manually added if it should not be present' => [
				'assignedGroups' => [],
				'groupsToAdd' => [ 'automatic-group' ],
				'groupsToRemove' => [],
				'expiriesToChange' => [],
				'config' => [
					'test-group' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [],
				'expectedAnyChanged' => true,
			],
			'An automatic group expiry cannot be manually changed' => [
				'assignedGroups' => [
					'test-group' => '20230405060707',
					'automatic-group' => '20230405060707'
				],
				'groupsToAdd' => [],
				'groupsToRemove' => [ 'automatic-group' ],
				'expiriesToChange' => [ 'automatic-group' => '20240405060707' ],
				'config' => [
					'test-group' => [ 'automatic-group' ],
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
				'expectedExpiriesToChange' => [],
				'expectedAnyChanged' => true,
			],
		];
	}

	/** @dataProvider provideTestExpiryEarlierThan */
	public function testExpiryEarlierThan( $expiry1, $expiry2, $expected ) {
		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $automaticGroupManager );
		$this->assertSame( $expected, $objectUnderTest->expiryEarlierThan( $expiry1, $expiry2 ) );
	}

	public static function provideTestExpiryEarlierThan() {
		return [
			'Expiry1 is earlier' => [
				'expiry1' => '20200405060707',
				'expiry2' => '20230405060707',
				'expected' => true,
			],
			'Expiry2 is null' => [
				'expiry1' => '20230405060707',
				'expiry2' => null,
				'expected' => true,
			],
			'Expiry2 is earlier' => [
				'expiry1' => '20230405060707',
				'expiry2' => '20200405060707',
				'expected' => false,
			],
			'Expiry1 is null' => [
				'expiry1' => null,
				'expiry2' => '20200405060707',
				'expected' => false,
			],
			'Both expirires are null' => [
				'expiry1' => null,
				'expiry2' => null,
				'expected' => false,
			],
			'Expirires are equal' => [
				'expiry1' => '20200405060707',
				'expiry2' => '20200405060707',
				'expected' => false,
			],
		];
	}

	/** @dataProvider provideTestExpiryEquals */
	public function testExpiryEquals( $expiry1, $expiry2, $expected ) {
		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $automaticGroupManager );
		$this->assertSame( $expected, $objectUnderTest->expiryEquals( $expiry1, $expiry2 ) );
	}

	public static function provideTestExpiryEquals() {
		return [
			'Expiry1 is earlier' => [
				'expiry1' => '20200405060707',
				'expiry2' => '20230405060707',
				'expected' => false,
			],
			'Expiry2 is null' => [
				'expiry1' => '20230405060707',
				'expiry2' => null,
				'expected' => false,
			],
			'Expiry2 is earlier' => [
				'expiry1' => '20230405060707',
				'expiry2' => '20200405060707',
				'expected' => false,
			],
			'Expiry1 is null' => [
				'expiry1' => null,
				'expiry2' => '20200405060707',
				'expected' => false,
			],
			'Both expirires are null' => [
				'expiry1' => null,
				'expiry2' => null,
				'expected' => true,
			],
			'Expirires are equal' => [
				'expiry1' => '20200405060707',
				'expiry2' => '20200405060707',
				'expected' => true,
			],
		];
	}

}
