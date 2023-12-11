<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * Basic tests for CentralAuthHooks
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthHooksUsingDatabaseTest extends CentralAuthUsingDatabaseTestCase {

	/**
	 * @covers MediaWiki\Extension\CentralAuth\CentralAuthHooks::onUserGetEmailAuthenticationTimestamp
	 */
	public function testLockedEmailDisabled() {
		$user = User::newFromName( 'GlobalLockedUser' );
		$this->assertFalse( $user->isEmailConfirmed() );
		$this->assertFalse( $user->canReceiveEmail() );
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
