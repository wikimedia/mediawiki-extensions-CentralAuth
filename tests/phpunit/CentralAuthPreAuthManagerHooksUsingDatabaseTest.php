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
	public function testOnAbortNewAccount( $user, $result) {
		$error = '';
		$hookResult = CentralAuthPreAuthManagerHooks::onAbortNewAccount( $user, $error );
		$this->assertSame( $result, $hookResult );
	}

	public function provideAbortNewAccount() {
		$userExists = User::newFromName( 'GlobalUser' );
		$userNotExists = User::newFromName( 'UserNotExists' );
		return array(
			array( $userNotExists, true ),
			array( $userExists, false ),
		);
	}

	/**
	 * @dataProvider provideAbortLogin
	 * @covers CentralAuthPreAuthManagerHooks::onAbortLogin
	 */
	public function testOnAbortLogin( $user, $result, $test) {
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

		return array(
			array( $user, true, 'Attached user can login' ),
			array( $noUser, true, 'Unattached user can login' ),
			array( $lockedUser, false, 'Locked User cannot login' ),
		);
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
			array( 'gu_id' => '1001' ),
			array(
				array( wfWikiID(), 'primary' ),
				array( 'enwiki', 'primary' ),
				array( 'dewiki', 'login' ),
				array( 'metawiki', 'password' ),
			)
		);
		$u->save( $this->db );

		$u = new CentralAuthTestUser(
			'GlobalLockedUser',
			'GLUP@ssword',
			array(
				'gu_id' => '1003',
				'gu_locked' => 1,
				'gu_hidden' => CentralAuthUser::HIDDEN_NONE,
				'gu_email' => 'testlocked@localhost',
				'gu_home_db' => 'metawiki',
			),
			array(
				array( 'metawiki', 'primary' ),
			)
		);
		$u->save( $this->db );
	}


}
