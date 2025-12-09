<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\BlockHookHandler
 */
class BlockHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testGetBlock() {
		$u = new CentralAuthTestUser(
			'GloballySuppressedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1004',
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->getDb() );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	public function testGetBlock_noLocalAccount() {
		// This user doesn't exist locally, but we still surface the block.
		$u = new CentralAuthTestUser(
			'GloballySuppressedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1004',
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			],
			[],
			false
		);
		$u->save( $this->getDb() );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	public function testGetBlock_ipRange() {
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newAnonymous();
		// T358112: IP ranges (invalid usernames) should not cause an exception.
		$user->setName( '127.0.0.1/24' );
		$this->assertNull( $user->getBlock() );
	}
}
