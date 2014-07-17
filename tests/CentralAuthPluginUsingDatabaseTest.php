<?php
/**
 * Basic tests for CentralAuthPlugin
 * @group CentralAuthDB
 */
class CentralAuthPluginUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {

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

		// Locked but not hidden Global account
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
				array( wfWikiID(), 'password' ),
			)
		);
		$u->save( $this->db );

		// Global account that isn't attached on this wiki, and doesn't conflict
		$u = new CentralAuthTestUser(
			'GlobalUnattachedUser',
			'GUUP@ssword',
			array(
				'gu_id' => '1004',
				'gu_email' => 'testunattached@localhost',
				'gu_home_db' => 'metawiki',
			),
			array(
				array( 'metawiki', 'primary' ),
			),
			false
		);
		$u->save( $this->db );

		// Global unattached account with conflicting local user account
		$u = new CentralAuthTestUser(
			'GlobalConflictUser',
			'GCUP@ssword',
			array(
				'gu_id' => '1005',
				'gu_email' => 'testconflict@localhost',
				'gu_home_db' => 'metawiki',
			),
			array(
				array( 'metawiki', 'primary' ),
			)
		);
		$u->save( $this->db );
		$user = User::newFromName( 'GlobalConflictUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( $this->password );
			$user->saveSettings();
		}

		// Local only account
		$user = User::newFromName( 'AnotherNewUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( 'ANUP@ssword' );
			$user->saveSettings();
		}
	}


	/**
	 * @dataProvider provideUserExists
	 * @covers CentralAuthPlugin::userExists
	 */
	public function testUserExists( $username, $expected, $test ) {
		$auth = new CentralAuthPlugin();
		$this->assertSame( $expected, $auth->userExists( $username ), $test );
	}

	public function provideUserExists() {
		return array(
			array( 'GlobalUser', true, 'Test for existing user' ),
			array( 'ThisUserDoesNotExist', false, 'Test for non-existing user' ),
		);
	}

	/**
	 * @dataProvider provideAuthenticate
	 * @covers CentralAuthPlugin::authenticate
	 */
	public function testAuthenticate( $username, $password, $expected, $test ) {
		$auth = new CentralAuthPlugin();
		$this->setMwGlobals( array(
			'wgCentralAuthAutoMigrate' => false,
		) );
		$this->assertSame( $expected, $auth->authenticate( $username, $password ), $test );
	}

	public function provideAuthenticate() {
		return array(
			array( 'GlobalUser', 'GUP@ssword', true, 'Valid login for attached global' ),
			array( 'GlobalUser', 'wrongPassword', false, 'Invalid password for attached global' ),
			array( 'GlobalLockedUser', 'GLUP@ssword', false, 'Valid password for locked global' ),
			array( 'GlobalUnattachedUser', 'GUUP@ssword', true, 'Valid password for global but unattached account' ),
			array( 'GlobalConflictUser', 'GCUP@ssword', false, 'Valid password for global but unattached account, with conflicting local' ),
		);
	}

	/**
	 * @dataProvider provideStrictUserAuth
	 * @covers CentralAuthPlugin::strictUserAuth
	 */
	public function testStrictUserAuth( $username, $expected, $test ) {
		$auth = new CentralAuthPlugin();
		$this->assertSame( $expected, $auth->strictUserAuth( $username ), $test );
	}

	public function provideStrictUserAuth() {
		return array(
			array( 'GlobalUser', true, 'Attached global' ),
			array( 'ThisUserDoesNotExist', false, 'Non-existing user' ),
			array( 'GlobalUnattachedUser', false, 'Unattached global account' ),
		);
	}

	/**
	 * @covers CentralAuthPlugin::setPassword
	 */
	public function testSetPassword() {
		$auth = new CentralAuthPlugin();
		$user = User::newFromName( 'GlobalUser' );
		$this->assertSame( false, $user->isAnon(), 'Local account for GlobalUser exists' ); #sanity
		$auth->setPassword( $user, 'ANewPassword' );
		$central = new CentralAuthUser( 'GlobalUser' );
		$this->assertEquals( 'ok', $central->authenticate( 'ANewPassword' ), 'Authenticate with newly set password' );
	}

	/**
	 * @covers CentralAuthPlugin::getUserInstance
	 */
	public function testGetUserInstance() {
		$auth = new CentralAuthPlugin();
		$user = User::newFromName( 'GlobalUser' );
		$this->assertSame( 'CentralAuthUser', get_class( $auth->getUserInstance( $user ) ) );
	}

	/**
	 * TODO: CentralAuthPlugin::updateUser
	 */

	/**
	 * TODO: CentralAuthPlugin::addUser - calls CentralAuthUser::importLocalNames()
	 */

}
