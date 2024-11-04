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

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\Hooks\Handlers\AutopromoteConditionHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\AutopromoteConditionHookHandler
 */
class AutopromoteConditionHookHandlerTest extends MediaWikiIntegrationTestCase {

	private function getRegisteredTestUser( $centralAccountExists, $centralAccountAttached ): User {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		if ( $centralAccountExists ) {
			$caUser = CentralAuthUser::getPrimaryInstance( $user );
			$caUser->register( $testUser->getPassword(), null );
			if ( $centralAccountAttached ) {
				$caUser->attach( WikiMap::getCurrentWikiId() );
			}
		}

		return $testUser->getUser();
	}

	private function getObjectUnderTest(): AutopromoteConditionHookHandler {
		return new AutopromoteConditionHookHandler();
	}

	public function testOnAutopromoteConditionForNonCentralAuthCondition() {
		$result = null;
		$this->getObjectUnderTest()->onAutopromoteCondition(
			APCOND_AGE, [ 1234 ], $this->createMock( User::class ), $result
		);
		$this->assertNull( $result );
	}

	/** @dataProvider provideOnAutopromoteCondition */
	public function testOnAutopromoteCondition(
		$centralAccountExists, $centralAccountAttached, $centralAccountGlobalGroups, $globalGroupArguments,
		$expectedResult
	) {
		$user = $this->getRegisteredTestUser( $centralAccountExists, $centralAccountAttached );
		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		foreach ( $centralAccountGlobalGroups as $globalGroup ) {
			$caUser->addToGlobalGroup( $globalGroup );
		}

		$result = null;
		$this->getObjectUnderTest()->onAutopromoteCondition( APCOND_CA_INGLOBALGROUPS, $globalGroupArguments, $user, $result );
		$this->assertSame( $expectedResult, $result );
	}

	public static function provideOnAutopromoteCondition() {
		return [
			'Global user does not exist' => [
				'centralAccountExists' => false, 'centralAccountAttached' => false,
				'centralAccountGlobalGroups' => [], 'globalGroupArguments' => [],
				'expectedResult' => false,
			],
			'Global user is not attached' => [
				'centralAccountExists' => true, 'centralAccountAttached' => false,
				'centralAccountGlobalGroups' => [], 'globalGroupArguments' => [],
				'expectedResult' => false,
			],
			'Global user has no groups' => [
				'centralAccountExists' => true, 'centralAccountAttached' => true,
				'centralAccountGlobalGroups' => [], 'globalGroupArguments' => [ 'global-foo' ],
				'expectedResult' => false,
			],
			'Global user has groups, but none of the groups needed' => [
				'centralAccountExists' => true, 'centralAccountAttached' => true,
				'centralAccountGlobalGroups' => [ 'global-bar' ], 'globalGroupArguments' => [ 'global-foo' ],
				'expectedResult' => false,
			],
			'Global user has some of necessary groups' => [
				'centralAccountExists' => true, 'centralAccountAttached' => true,
				'centralAccountGlobalGroups' => [ 'global-foo' ],
				'globalGroupArguments' => [ 'global-foo', 'global-bar' ],
				'expectedResult' => false,
			],
			'Global user has the necessary group' => [
				'centralAccountExists' => true, 'centralAccountAttached' => true,
				'centralAccountGlobalGroups' => [ 'global-foo' ], 'globalGroupArguments' => [ 'global-foo' ],
				'expectedResult' => true,
			],
			'Global user has the necessary groups' => [
				'centralAccountExists' => true, 'centralAccountAttached' => true,
				'centralAccountGlobalGroups' => [ 'global-foo', 'global-bar' ],
				'globalGroupArguments' => [ 'global-foo', 'global-bar' ],
				'expectedResult' => true,
			],
		];
	}
}
