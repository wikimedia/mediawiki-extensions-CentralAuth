<?php

use Wikimedia\TestingAccessWrapper;

/**
 * Basic tests for CentralAuthPreAuthManagerHooks
 * @group CentralAuthDB
 */
class CentralAuthPreAuthManagerHooksUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {

	/**
	 * @dataProvider provideAbortNewAccount
	 * @covers CentralAuthPreAuthManagerHooks::onAbortNewAccount
	 */
	public function testOnAbortNewAccount( $user, $result ) {
		$error = '';
		$hookResult = CentralAuthPreAuthManagerHooks::onAbortNewAccount( $user, $error );
		$this->assertSame( $result, $hookResult );
	}

	public function provideAbortNewAccount() {
		$userExists = User::newFromName( 'GlobalUser' );
		$userNotExists = User::newFromName( 'UserNotExists' );
		return [
			[ $userNotExists, true ],
			[ $userExists, false ],
		];
	}

	/**
	 * @dataProvider provideAbortLogin
	 * @covers CentralAuthPreAuthManagerHooks::onAbortLogin
	 */
	public function testOnAbortLogin( $user, $result, $test ) {
		$retval = 0;
		$msg = '';
		$hookResult = CentralAuthPreAuthManagerHooks::onAbortLogin( $user, '', $retval, $msg );
		$this->assertEquals( $result, $hookResult, $test );
	}

	public function provideAbortLogin() {
		$user = User::newFromName( 'GlobalUser' );
		$lockedUser = User::newFromName( 'GlobalLockedUser' );

		// Fake out CentralAuthUser::getInstance()
		$noUser = User::newFromName( 'NoUser' );
		TestingAccessWrapper::newFromClass( 'CentralAuthUser' )->getUserCache()
			->set( $noUser->getName(), CentralAuthUser::newUnattached( 'NoUser' ) );

		return [
			[ $user, true, 'Attached user can login' ],
			[ $noUser, true, 'Unattached user can login' ],
			[ $lockedUser, false, 'Locked User cannot login' ],
		];
	}

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp() {
		parent::setUp();
		$u = new CentralAuthTestUser(
			'GlobalUser',
			'GUP@ssword',
			[ 'gu_id' => '1001' ],
			[
				[ wfWikiID(), 'primary' ],
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
				'gu_hidden' => CentralAuthUser::HIDDEN_NONE,
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
