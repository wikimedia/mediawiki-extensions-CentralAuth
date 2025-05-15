<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager
 */
class CentralAuthAutomaticGlobalGroupManagerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideHandleAutomaticGlobalGroups */
	public function testHandleAutomaticGlobalGroups(
		$assignedGroups,
		$config,
		$expectedGroupsToAdd,
		$expectedGroupsToRemove
	) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', $config );

		$groupsToAdd = [];
		$groupsToRemove = [];

		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$automaticGroupManager->handleAutomaticGlobalGroups(
				$assignedGroups,
				$groupsToAdd,
				$groupsToRemove
			);

		$this->assertSame( $expectedGroupsToAdd, $groupsToAdd );
		$this->assertSame( $expectedGroupsToRemove, $groupsToRemove );
	}

	public static function provideHandleAutomaticGlobalGroups() {
		return [
			'Having a group causes an automatic group to be added' => [
				'assignedGroups' => [ 'test-group' ],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
			],
			'Not having a group causes an automatic group to be removed' => [
				'assignedGroups' => [ 'automatic-group' ],
				'config' => [ 'test-group' => [ 'automatic-group' ] ],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [ 'automatic-group' ],
			],
			'If two local groups add the same global group, only having one adds the automatic group' => [
				'assignedGroups' => [ 'test-group-1' ],
				'config' => [
					'test-group-1' => [ 'automatic-group' ],
					'test-group-2' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [ 'automatic-group' ],
				'expectedGroupsToRemove' => [],
			],
			'Having an automatic group does not cause another automatic group to be updated' => [
				'assignedGroups' => [ 'test-group', 'automatic-group-1' ],
				'config' => [
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
			],
			'Adding an automatic group does not cause another automatic group to be updated' => [
				'assignedGroups' => [ 'test-group' ],
				'config' => [
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				'expectedGroupsToAdd' => [ 'automatic-group-1' ],
				'expectedGroupsToRemove' => [],
			],
		];
	}

	/** @dataProvider provideHandleAutomaticGlobalGroupsManually */
	public function testHandleAutomaticGlobalGroupsManually(
		$assignedGroups,
		$groupsToAdd,
		$groupsToRemove,
		$config,
		$expectedGroupsToAdd,
		$expectedGroupsToRemove
	) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', $config );

		$automaticGroupManager = CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() );
		$automaticGroupManager->handleAutomaticGlobalGroups(
				$assignedGroups,
				$groupsToAdd,
				$groupsToRemove
			);

		$this->assertSame( $expectedGroupsToAdd, $groupsToAdd );
		$this->assertSame( $expectedGroupsToRemove, $groupsToRemove );
	}

	public static function provideHandleAutomaticGlobalGroupsManually() {
		return [
			'An automatic group cannot be manually removed if it should be present' => [
				'assignedGroups' => [ 'test-group', 'automatic-group' ],
				'groupsToAdd' => [],
				'groupsToRemove' => [ 'automatic-group' ],
				'config' => [
					'test-group' => [ 'automatic-group' ],
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
			],
			'An automatic group cannot be manually added if it should not be present' => [
				'assignedGroups' => [],
				'groupsToAdd' => [ 'automatic-group' ],
				'groupsToRemove' => [],
				'config' => [
					'test-group' => [ 'automatic-group' ]
				],
				'expectedGroupsToAdd' => [],
				'expectedGroupsToRemove' => [],
			],
		];
	}

}
