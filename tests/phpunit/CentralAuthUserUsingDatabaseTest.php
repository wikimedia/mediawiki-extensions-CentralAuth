<?php

/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthUserUsingDatabaseTest extends CentralAuthUsingDatabaseTestCase {

	/**
	 * @covers CentralAuthUser::exists
	 * @covers CentralAuthUser::getId
	 * @covers CentralAuthUser::getName
	 * @covers CentralAuthUser::getHomeWiki
	 * @covers CentralAuthUser::isAttached
	 * @covers CentralAuthUser::getRegistration
	 * @covers CentralAuthUser::getStateHash
	 */
	public function testBasicAttrs() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertEquals( 1001, $caUser->getId() );
		$this->assertEquals( 'GlobalUser', $caUser->getName() );
		$this->assertEquals( wfWikiID(), $caUser->getHomeWiki() );
		$this->assertSame( true, $caUser->isAttached() );
		$this->assertSame( false, $caUser->isLocked() );
		$this->assertSame( '20130627183537', $caUser->getRegistration() );
		$this->assertEquals(
			CentralAuthUser::HIDDEN_NONE,
			$caUser->getHiddenLevel()
		);
		$this->assertEquals(
			'2234d7949459185926a50073d174b673',
			$caUser->getStateHash()
		);
	}

	/**
	 * @covers CentralAuthUser::loadStateNoCache
	 * @covers CentralAuthUser::loadState
	 */
	public function testLoadFromDB() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$caUser->loadStateNoCache();
		$this->assertSame( true, $caUser->exists() );
		$this->assertEquals( 1001, $caUser->getId() );
	}

	/**
	 * @covers CentralAuthUser::listAttached
	 */
	public function testLoadAttached() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertArrayEquals(
			[
				wfWikiID(),
				'enwiki',
				'dewiki',
				'metawiki',
			],
			$caUser->listAttached()
		);
	}

	/**
	 * @covers CentralAuthUser::getAuthToken
	 * @covers CentralAuthUser::resetAuthToken
	 */
	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertEquals(
			32,
			strlen( $token )
		);
		$this->assertSame(
			0,
			preg_match( '/[^a-f0-9]/', $token )
		);
	}

	/**
	 * @covers CentralAuthUser::newFromId
	 */
	public function testNewFromId() {
		$ca = CentralAuthUser::newFromId( 1001 );
		$this->assertEquals( 'GlobalUser', $ca->getName() );

		$caBad = CentralAuthUser::newFromId( -1001 );
		$this->assertFalse( $caBad );
	}

	/**
	 * @covers CentralAuthUser::register
	 */
	public function testRegister() {
		$caUserNew = new CentralAuthUser( 'RegTest', CentralAuthUser::READ_LATEST );
		$ok = $caUserNew->register( "R3gT3stP@ssword", "user@localhost" );
		$this->assertSame( true, $ok );

		$caUser = new CentralAuthUser( 'RegTest' );
		$this->assertSame( true, $caUser->exists() );

		// And duplicate registration doesn't throw an exception (T108541)
		$this->assertFalse( $caUserNew->register( "R3gT3stP@ssword", "user@localhost" ) );
	}

	/**
	 * @covers CentralAuthUser::isLocked
	 */
	public function testLocked() {
		$caUser = new CentralAuthUser( 'GlobalLockedUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, $caUser->isLocked() );
	}

	/**
	 * @covers CentralAuthUser::isHidden
	 * @covers CentralAuthUser::isOversighted
	 * @covers CentralAuthUser::getHiddenLevel
	 */
	public function testHidden() {
		$caUser = new CentralAuthUser( 'GlobalSuppressedUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, $caUser->isHidden() );
		$this->assertSame( true, $caUser->isOversighted() );
		$this->assertEquals( CentralAuthUser::HIDDEN_OVERSIGHT, $caUser->getHiddenLevel() );
	}

	/**
	 * @covers CentralAuthUser::storeMigrationData
	 */
	public function testStoreMigrationData() {
		$caUsers = [
			'2001' => 'StoreMigrationDataUser 1',
			'2002' => 'StoreMigrationDataUser 2',
			'2003' => 'StoreMigrationDataUser 3',
		];
		CentralAuthUser::storeMigrationData( 'smdwiki', $caUsers );
		$this->assertSelect(
			'localnames',
			'ln_name',
			[ 'ln_wiki' => 'smdwiki' ],
			[
				[ 'StoreMigrationDataUser 1' ],
				[ 'StoreMigrationDataUser 2' ],
				[ 'StoreMigrationDataUser 3' ],
			]
		);
	}

	/**
	 * @covers CentralAuthUser::adminLock
	 * @covers CentralAuthUser::adminUnlock
	 * @covers CentralAuthUser::adminSetHidden
	 */
	public function testAdminLockAndHide() {
		$caUser = new CentralAuthUser( 'GlobalUser', CentralAuthUser::READ_LATEST );
		$this->assertSame( true, $caUser->exists() ); # sanity
		$this->assertSame( false, $caUser->isHidden() ); # sanity
		$this->assertSame( false, $caUser->isLocked() ); # sanity

		$caUser->adminLock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LISTS );

		// Check the DB
		$this->assertSelect(
			'globaluser',
			[ 'gu_name', 'gu_locked', 'gu_hidden' ],
			[ 'gu_name' => 'GlobalUser' ],
			[
				[ 'GlobalUser', '1', CentralAuthUser::HIDDEN_LISTS ]
			]
		);

		// Check that the instance was reloaded from the DB
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, $caUser->isLocked() );
		$this->assertSame( true, $caUser->isHidden() );
		// Ignore cache, read from DB for new instance
		$caUser = new CentralAuthUser( 'GlobalUser', CentralAuthUser::READ_LATEST );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, $caUser->isLocked() );
		$this->assertSame( true, $caUser->isHidden() );

		$caUser->adminUnlock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_NONE );

		// Check that the instance was reloaded from the DB
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( false, $caUser->isHidden() );
		$this->assertSame( false, $caUser->isLocked() );
		// Ignore cache, read from DB for new instance
		$caUser = new CentralAuthUser( 'GlobalUser', CentralAuthUser::READ_LATEST );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( false, $caUser->isHidden() );
		$this->assertSame( false, $caUser->isLocked() );
	}

	/**
	 * @covers CentralAuthUser::attach
	 */
	public function testAttach() {
		$caUser = new CentralAuthUser( 'GlobalUser', CentralAuthUser::READ_LATEST );
		$caUser->attach( 'anotherwiki', 'admin', false );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, in_array( 'anotherwiki', $caUser->listAttached() ) );
	}

	/**
	 * @covers CentralAuthUser::getGroupMemberships
	 * @covers CentralAuthUser::getGlobalGroups
	 * @covers CentralAuthUtils::purgeExpired
	 * @covers CentralAuthUser::addToGlobalGroup
	 */
	public function testGetGroupMemberships() {
		$caUser = new CentralAuthUser( 'GlobalUser', CentralAuthUser::READ_LATEST );
		$this->assertSame( true, $caUser->exists() );

		// Enable global groups adding at least one right
		$insertPerm = [
			[ 'ggp_group' => 'permfoogroup', 'ggp_permission' => 'dummyright' ],
			[ 'ggp_group' => 'foogroup', 'ggp_permission' => 'dummyright' ],
			[ 'ggp_group' => 'fooexpired', 'ggp_permission' => 'dummyright' ]
		];

		$this->db->replace(
			'global_group_permissions',
			[ 'ggp_group', 'ggp_permission' ],
			$insertPerm,
			__METHOD__
		);

		// Add user to groups
		$caUser->addToGlobalGroup( 'permfoogroup', null );
		$caUser->addToGlobalGroup( 'foogroup', '29991128122813' );

		// Simulate an expired group and check if it exist
		$this->db->replace( 'global_user_groups',
			[ 'gug_user', 'gug_group', 'gug_expiry' ],
			[ 'gug_user' => $caUser->getId(),
				'gug_group' => 'fooexpired',
				'gug_expiry' => '20011128122813'
			],
			__METHOD__
		);
		$this->assertSelect(
			'global_user_groups',
			[ 'gug_user', 'gug_group', 'gug_expiry' ],
			[ 'gug_user' => $caUser->getId(),
				'gug_expiry' . '<' . $this->db->addQuotes( $this->db->timestamp() )
			],
			[
				[ $caUser->getId(), 'fooexpired', '20011128122813' ]
			]
		);

		// Check that groups are loaded with old function
		$cagroup = $caUser->getGlobalGroups();
		$this->assertFalse( in_array( 'fooexpired', $cagroup ) );
		$this->assertTrue( in_array( 'permfoogroup', $cagroup ) );
		$this->assertTrue( in_array( 'foogroup', $cagroup ) );

		// Load global groups with expiry
		$caUserGroups = $caUser->getGroupMemberships();
		// Permanent global group
		$groupMembership = $caUserGroups['permfoogroup'];
		$this->assertNull( $groupMembership->getExpiry() );
		// Expired group is not loaded by getGroupMemberships
		$this->assertSame( true, empty( $caUserGroups['fooexpired'] ) );
		// Non expired global group
		$groupMembership = $caUserGroups['foogroup'];
		$this->assertSame( '29991128122813', $groupMembership->getExpiry() );

		// Than delete expired row
		CentralAuthUtils::purgeExpired();
		// Exipired row deleted?
		$this->assertSelect(
			'global_user_groups',
			[ 'gug_user', 'gug_group', 'gug_expiry' ],
			[ 'gug_user' => $caUser->getId(),
				'gug_expiry' . '<' . $this->db->addQuotes( $this->db->timestamp() )
			],
			[]
		);

		// Try to add an invalid group
		try {
			$caUser->addToGlobalGroup( 'novalidgroup', '29991128122813' );
		} catch ( UnexpectedValueException $ex ) {
			$expetted = 'The global group "novalidgroup" does not exist';
			$this->assertEquals( $ex->getMessage(), $expetted );
		}

		// Force an expired right via AddToGlobalGroup
		try {
			$caUser->addToGlobalGroup( 'fooexpired', '20011128122813' );
		} catch ( UnexpectedValueException $ex ) {
			$expetted = 'The expiry time for group "fooexpired" is in the past.';
			$this->assertEquals( $ex->getMessage(), $expetted );
		}

		// Force an invalid expiry via AddToGlobalGroup
		try {
			$caUser->addToGlobalGroup( 'fooexpired', 'invalidex' );
		} catch ( UnexpectedValueException $ex ) {
			$expetted = 'The expiry time for group "fooexpired" is invalid.';
			$this->assertEquals( $ex->getMessage(), $expetted );
		}
	}

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp() : void {
		parent::setUp();
		$user = new CentralAuthTestUser(
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
		$user->save( $this->db );

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

		$u = new CentralAuthTestUser(
			'GlobalSuppressedUser',
			'GSUP@ssword',
			[
				'gu_id' => '1004',
				'gu_locked' => 1,
				'gu_hidden' => CentralAuthUser::HIDDEN_OVERSIGHT,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'metawiki', 'primary' ],
			]
		);
		$u->save( $this->db );
	}

}
