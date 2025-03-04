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

	public function provideHandleAutomaticGlobalGroups() {
		return [
			'Having a group causes an automatic group to be added' => [
				[ 'test-group' ],
				[ 'test-group' => [ 'automatic-group' ] ],
				[ 'automatic-group' ],
				[],
			],
			'Not having a group causes an automatic group to be removed' => [
				[ 'automatic-group' ],
				[ 'test-group' => [ 'automatic-group' ] ],
				[],
				[ 'automatic-group' ],
			],
			'If two local groups add the same global group, only having one adds the automatic group' => [
				[ 'test-group-1' ],
				[
					'test-group-1' => [ 'automatic-group' ],
					'test-group-2' => [ 'automatic-group' ]
				],
				[ 'automatic-group' ],
				[],
			],
			'Having an automatic group does not cause another automatic group to be updated' => [
				[ 'test-group', 'automatic-group-1' ],
				[
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				[],
				[],
			],
			'Adding an automatic group does not cause another automatic group to be updated' => [
				[ 'test-group' ],
				[
					'test-group' => [ 'automatic-group-1' ],
					'automatic-group-1' => [ 'automatic-group-2' ],
				],
				[ 'automatic-group-1' ],
				[],
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

	public function provideHandleAutomaticGlobalGroupsManually() {
		return [
			'An automatic group cannot be manually removed if it should be present' => [
				[ 'test-group', 'automatic-group' ],
				[],
				[ 'automatic-group' ],
				[
					'test-group' => [ 'automatic-group' ],
				],
				[],
				[],
			],
			'An automatic group cannot be manually added if it should not be present' => [
				[],
				[ 'automatic-group' ],
				[],
				[
					'test-group' => [ 'automatic-group' ]
				],
				[],
				[],
			],
		];
	}

}
