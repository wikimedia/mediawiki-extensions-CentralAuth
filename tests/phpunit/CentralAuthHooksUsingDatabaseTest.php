<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;

/**
 * Basic tests for CentralAuthHooks
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthHooksUsingDatabaseTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onUserGetEmailAuthenticationTimestamp
	 */
	public function testLockedEmailDisabled() {
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GlobalLockedUser' );
		$this->assertFalse( $user->isEmailConfirmed() );
		$this->assertFalse( $user->canReceiveEmail() );
	}

	/**
	 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onGetUserBlock
	 */
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
		$u->save( $this->db );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	/**
	 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onGetUserBlock
	 */
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
		$u->save( $this->db );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	/**
	 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onGetUserBlock
	 */
	public function testGetBlock_ipRange() {
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newAnonymous();
		// T358112: IP ranges (invalid usernames) should not cause an exception.
		$user->setName( '127.0.0.1/24' );
		$this->assertNull( $user->getBlock() );
	}

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiIntegrationTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp(): void {
		parent::setUp();

		$u = new CentralAuthTestUser(
			'GlobalUser',
			'GUP@ssword',
			[ 'gu_id' => '1001' ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
				[ 'enwiki', 'primary' ],
				[ 'dewiki', 'login' ],
				[ 'metawiki', 'password' ],
			]
		);
		$u->save( $this->db );

		$u = new CentralAuthTestUser(
			'GlobalLockedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1003',
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
				'gu_email' => 'testlocked@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'metawiki', 'primary' ],
			]
		);
		$u->save( $this->db );
	}

}
