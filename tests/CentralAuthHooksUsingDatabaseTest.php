<?php
/**
 * Basic tests for CentralAuthHooks
 * @group CentralAuthDB
 */
class CentralAuthHooksUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {


	/**
	 * @dataProvider provideAbortNewAccount
	 * @covers CentralAuthHooks::onAbortNewAccount
	 */
	public function testOnAbortNewAccount( $user, $result) {
		$error = '';
		$hookResult = CentralAuthHooks::onAbortNewAccount( $user, $error );
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
	 * @covers CentralAuthHooks::onUserGetEmailAuthenticationTimestampp
	 */
	public function testLockedEmailDisabled() {
		$user = User::newFromName( 'GlobalLockedUser' );
		$this->assertFalse( $user->isEmailConfirmed() );
		$this->assertFalse( $user->canReceiveEmail() );
	}

	/**
	 * @dataProvider provideAbortLogin
	 * @covers CentralAuthHooks::onAbortLogin
	 */
	public function testOnAbortLogin( $user, $result, $test) {
		$retval = 0;
		$msg = '';
		$hookResult = CentralAuthHooks::onAbortLogin( $user, '', $retval, $msg );
		$this->assertEquals( $result, $hookResult, $test );
	}

	public function provideAbortLogin() {
		$user = User::newFromName( 'GlobalUser' );
		$lockedUser = User::newFromName( 'GlobalLockedUser' );

		// We can fake out CentralAuthUser::getInstance() by adding centralAuthObj
		$noUser = User::newFromName( 'NoUser' );
		$noUser->centralAuthObj = CentralAuthUser::newUnattached( 'NoUser' );

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
