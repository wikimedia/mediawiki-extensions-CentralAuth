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
				array( self::safeWfWikiID(), 'primary' ),
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
				array( self::safeWfWikiID(), 'password' ),
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
			TestUser::setPasswordForUser( $user, $this->password );
		}

		// Local only account
		$user = User::newFromName( 'AnotherNewUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			TestUser::setPasswordForUser( $user, 'ANUP@ssword' );
		}

		// Global user who was renamed when migrated
		$u = new CentralAuthTestUser(
			'GlobalUser~' . str_replace( '_', '-', self::safeWfWikiID() ),
			'GURP@ssword',
			array( 'gu_id' => '1006' ),
			array(
				array( self::safeWfWikiID(), 'primary' ),
			)
		);
		$u->save( $this->db );
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
	 * @dataProvider provideAuthenticateWithPreRenameUsername
	 * @covers CentralAuthPlugin::authenticate
	 */
	public function testAuthenticateWithPreRenameUsername(
		$username, $password, $checkMigration,
		$expectAuth, $expectMigrationName,
		$test
	) {
		$auth = new CentralAuthPlugin();
		$this->setMwGlobals( array(
			'wgCentralAuthCheckSULMigration' => $checkMigration,
		) );

		$this->assertSame( $expectAuth, $auth->authenticate( $username, $password ),
			"{$test}; authenticate"
		);
		$this->assertSame( $expectMigrationName, $auth->sulMigrationName,
			"{$test}; sulMigrationName"
		);
	}

	public function provideAuthenticateWithPreRenameUsername() {
		return array(
			array(
				'GlobalUser', 'GURP@ssword', false,
				false, null,
				'wgCentralAuthCheckSULMigration disabled',
			),
			array(
				'GlobalUser', 'GURP@ssword', true,
				true, 'GlobalUser~' . str_replace( '_', '-', self::safeWfWikiID() ),
				'wgCentralAuthCheckSULMigration enabled; correct password',
			),
			array(
				'GlobalUser', 'not_my_password', true,
				false, null,
				'wgCentralAuthCheckSULMigration enabled; incorrect password',
			),
		);
	}

	/**
	 * @dataProvider provideUpdateUserRenameAnnotation
	 * @covers CentralAuthPlugin::updateUser
	 */
	public function testUpdateUserRenameAnnotation(
		$username, $checkMigration, $migrationName,
		$expectAnnotated, $expectRename,
		$test
	) {
		$auth = new CentralAuthPlugin();
		$this->setMwGlobals( array(
			'wgCentralAuthCheckSULMigration' => $checkMigration,
		) );
		$auth->sulMigrationName = $migrationName;

		$user = User::newFromName( $username );
		$auth->updateUser( $user );

		$this->assertSame( $expectAnnotated, isset( $user->sulRenamed ),
			"{$test}; annotated"
		);
		$this->assertSame( $expectRename, $username !== $user->getName(),
			"{$test}; renamed"
		);
	}

	public function provideUpdateUserRenameAnnotation() {
		return array(
			array(
				'GlobalUser', false, 'GlobalUser~' . str_replace( '_', '-', self::safeWfWikiID() ),
				false, false,
				'wgCentralAuthCheckSULMigration disabled; sulMigrationName set',
			),
			array(
				'GlobalUser', false, null,
				false, false,
				'wgCentralAuthCheckSULMigration disabled; sulMigrationName unset',
			),
			array(
				'GlobalUser', true, 'GlobalUser~' . str_replace( '_', '-', self::safeWfWikiID() ),
				true, true,
				'wgCentralAuthCheckSULMigration enabled; sulMigrationName set',
			),
			array(
				'GlobalUser', true, null,
				false, false,
				'wgCentralAuthCheckSULMigration enabled; sulMigrationName unset',
			),
		);
	}

	/**
	 * TODO: CentralAuthPlugin::updateUser
	 */

	/**
	 * TODO: CentralAuthPlugin::addUser - calls CentralAuthUser::importLocalNames()
	 */

	/**
	 * Wrapper for wfWikiID that ensures that the returned value always ends
	 * with a db prefix.
	 *
	 * When phpunit is running tests, wfWikiID() returns a different value
	 * inside a test from the value that it returns during a dataProvider call.
	 *
	 * Bug: 68231
	 * @return string
	 * @see wfWikiID()
	 */
	protected static function safeWfWikiID() {
		$wikiId = wfWikiID();
		$suffix = '-' . self::DB_PREFIX;
		if ( substr( $wikiId, -strlen( $suffix ) ) !== $suffix ) {
			$wikiId = "{$wikiId}{$suffix}";
		}
		return $wikiId;
	}
}
