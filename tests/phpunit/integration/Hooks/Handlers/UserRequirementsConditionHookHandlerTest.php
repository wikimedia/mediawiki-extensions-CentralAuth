<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserRequirementsConditionHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserRequirementsConditionHookHandler
 */
class UserRequirementsConditionHookHandlerTest extends MediaWikiIntegrationTestCase {

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

	private function getObjectUnderTest(): UserRequirementsConditionHookHandler {
		return new UserRequirementsConditionHookHandler();
	}

	public function testOnUserRequirementsConditionForNonCentralAuthCondition() {
		$result = null;
		$userIdentity = new UserIdentityValue( 1, 'TestUser' );
		$this->getObjectUnderTest()->onUserRequirementsCondition(
			APCOND_AGE, [ 1234 ], $userIdentity, true, $result
		);
		$this->assertNull( $result );
	}

	public function testIsCalledFromUserRequirementsConditionChecker() {
		$condition = [ APCOND_CA_INGLOBALGROUPS, 'test-group' ];
		$user = new UserIdentityValue( 1, 'TestUser' );

		$checker = $this->getServiceContainer()->getUserRequirementsConditionChecker();
		$result = $checker->recursivelyCheckCondition( $condition, $user );
		$this->assertFalse( $result );
	}

	/** @dataProvider provideOnUserRequirementsCondition */
	public function testOnUserRequirementsCondition(
		$centralAccountExists, $centralAccountAttached, $centralAccountGlobalGroups, $globalGroupArguments,
		$expectedResult
	) {
		$user = $this->getRegisteredTestUser( $centralAccountExists, $centralAccountAttached );
		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		foreach ( $centralAccountGlobalGroups as $globalGroup ) {
			$caUser->addToGlobalGroup( $globalGroup );
		}

		$result = null;
		$this->getObjectUnderTest()->onUserRequirementsCondition(
			APCOND_CA_INGLOBALGROUPS, $globalGroupArguments, $user->getUser(), true, $result
		);
		$this->assertSame( $expectedResult, $result );
	}

	public static function provideOnUserRequirementsCondition() {
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
