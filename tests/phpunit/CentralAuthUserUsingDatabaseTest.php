<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthUserUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {

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
		$this->assertEquals( '20130627183537', $caUser->getRegistration() );
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
			array(
				wfWikiID(),
				'enwiki',
				'dewiki',
				'metawiki',
			),
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
		$caUserNew = new CentralAuthUser( 'RegTest' );
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
		$caUsers = array(
			'2001' => 'StoreMigrationDataUser 1',
			'2002' => 'StoreMigrationDataUser 2',
			'2003' => 'StoreMigrationDataUser 3',
		);
		CentralAuthUser::storeMigrationData( 'smdwiki', $caUsers );
		$this->assertSelect(
			'localnames',
			'ln_name',
			array( 'ln_wiki' => 'smdwiki' ),
			array(
				array( 'StoreMigrationDataUser 1' ),
				array( 'StoreMigrationDataUser 2' ),
				array( 'StoreMigrationDataUser 3' ),
			)
		);
	}

	/**
	 * @covers CentralAuthUser::adminLock
	 * @covers CentralAuthUser::adminUnlock
	 * @covers CentralAuthUser::adminSetHidden
	 */
	public function testAdminLockAndHide() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() ); #sanity
		$this->assertSame( false, $caUser->isHidden() ); #sanity
		$this->assertSame( false, $caUser->isLocked() ); #sanity

		$caUser->adminLock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LISTS );

		// Check the DB
		$this->assertSelect(
			'globaluser',
			array( 'gu_name', 'gu_locked', 'gu_hidden' ),
			array( 'gu_name' => 'GlobalUser' ),
			array(
				array( 'GlobalUser', '1', CentralAuthUser::HIDDEN_LISTS )
			)
		);

		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, $caUser->isLocked() );
		$this->assertSame( true, $caUser->isHidden() );

		$caUser->adminUnlock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_NONE );

		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( false, $caUser->isHidden() );
		$this->assertSame( false, $caUser->isLocked() );
	}

	/**
	 * @covers CentralAuthUser::attach
	 */
	public function testAttach() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$caUser->attach( 'anotherwiki', 'admin', false );
		$this->assertSame( true, $caUser->exists() );
		$this->assertSame( true, in_array( 'anotherwiki', $caUser->listAttached() ) );
	}

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp() {
		parent::setUp();
		$user = new CentralAuthTestUser(
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
		$user->save( $this->db );

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

		$u = new CentralAuthTestUser(
			'GlobalSuppressedUser',
			'GSUP@ssword',
			array(
				'gu_id' => '1004',
				'gu_locked' => 1,
				'gu_hidden' => CentralAuthUser::HIDDEN_OVERSIGHT,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			),
			array(
				array( 'metawiki', 'primary' ),
			)
		);
		$u->save( $this->db );
	}

}
