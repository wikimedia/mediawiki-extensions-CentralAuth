<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * Setup database tests for centralauth
 *
 * @coversDefaultClass MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthUserUsingDatabaseTest extends CentralAuthUsingDatabaseTestCase {
	/**
	 * @covers ::exists
	 * @covers ::getId
	 * @covers ::getName
	 * @covers ::getHomeWiki
	 * @covers ::isAttached
	 * @covers ::getRegistration
	 * @covers ::getStateHash
	 */
	public function testBasicAttrs() {
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertSame( 1001, $caUser->getId() );
		$this->assertSame( 'GlobalUser', $caUser->getName() );
		$this->assertSame( WikiMap::getCurrentWikiId(), $caUser->getHomeWiki() );
		$this->assertTrue( $caUser->isAttached() );
		$this->assertFalse( $caUser->isLocked() );
		$this->assertSame( '20130627183537', $caUser->getRegistration() );
		$this->assertSame(
			CentralAuthUser::HIDDEN_NONE,
			$caUser->getHiddenLevel()
		);
		$this->assertSame(
			CentralAuthUser::HIDDEN_LEVEL_NONE,
			$caUser->getHiddenLevelInt()
		);
		$this->assertSame(
			'8de7319aacab2020d38db1fbfac313a4',
			$caUser->getStateHash()
		);
		$this->assertSame(
			'2234d7949459185926a50073d174b673',
			$caUser->getStateHash( false, true )
		);
	}

	/**
	 * @covers ::loadStateNoCache
	 * @covers ::loadState
	 */
	public function testLoadFromDB() {
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$caUser->loadStateNoCache();
		$this->assertTrue( $caUser->exists() );
		$this->assertSame( 1001, $caUser->getId() );
	}

	/**
	 * @covers ::listAttached
	 */
	public function testLoadAttached() {
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$this->assertArrayEquals(
			[
				WikiMap::getCurrentWikiId(),
				'enwiki',
				'dewiki',
				'metawiki',
			],
			$caUser->listAttached()
		);
	}

	/**
	 * @covers ::getAuthToken
	 * @covers ::resetAuthToken
	 */
	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertSame( 1, preg_match( '/^[0-9a-f]{32}$/', $token ) );
	}

	/**
	 * @covers ::newFromId
	 */
	public function testNewFromId() {
		$ca = CentralAuthUser::newFromId( 1001 );
		$this->assertSame( 'GlobalUser', $ca->getName() );

		$caBad = CentralAuthUser::newFromId( -1001 );
		$this->assertFalse( $caBad );
	}

	/**
	 * @covers ::register
	 */
	public function testRegister() {
		$caUserNew = CentralAuthUser::getPrimaryInstanceByName( 'RegTest' );
		$ok = $caUserNew->register( "R3gT3stP@ssword", "user@localhost" );
		$this->assertTrue( $ok );

		$caUser = CentralAuthUser::getInstanceByName( 'RegTest' );
		$this->assertTrue( $caUser->exists() );

		// And duplicate registration doesn't throw an exception (T108541)
		$this->assertFalse( $caUserNew->register( "R3gT3stP@ssword", "user@localhost" ) );
	}

	/**
	 * @covers ::isLocked
	 */
	public function testLocked() {
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalLockedUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
	}

	/**
	 * @covers ::isHidden
	 * @covers ::isSuppressed
	 * @covers ::getHiddenLevel
	 * @covers ::getHiddenLevelInt
	 */
	public function testHidden() {
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalSuppressedUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isHidden() );
		$this->assertTrue( $caUser->isSuppressed() );
		$this->assertSame( CentralAuthUser::HIDDEN_OVERSIGHT, $caUser->getHiddenLevel() );
		$this->assertSame( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, $caUser->getHiddenLevelInt() );
	}

	/**
	 * @covers ::storeMigrationData
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
	 * @covers ::adminLock
	 * @covers ::adminUnlock
	 * @covers ::adminSetHidden
	 */
	public function testAdminLockAndHide() {
		$caUser = CentralAuthUser::getPrimaryInstanceByName( 'GlobalUser' );
		$this->assertTrue( $caUser->exists() ); # sanity
		$this->assertFalse( $caUser->isHidden() ); # sanity
		$this->assertFalse( $caUser->isLocked() ); # sanity

		$caUser->adminLock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_LISTS );

		// Check the DB
		$this->assertSelect(
			'globaluser',
			[ 'gu_name', 'gu_locked', 'gu_hidden_level' ],
			[ 'gu_name' => 'GlobalUser' ],
			[
				[ 'GlobalUser', '1', CentralAuthUser::HIDDEN_LEVEL_LISTS ]
			]
		);

		// Check that the instance was reloaded from the DB
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
		$this->assertTrue( $caUser->isHidden() );
		// Ignore cache, read from DB for new instance
		$caUser = CentralAuthUser::getPrimaryInstanceByName( 'GlobalUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
		$this->assertTrue( $caUser->isHidden() );

		$caUser->adminUnlock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_NONE );

		// Check that the instance was reloaded from the DB
		$this->assertTrue( $caUser->exists() );
		$this->assertFalse( $caUser->isHidden() );
		$this->assertFalse( $caUser->isLocked() );
		// Ignore cache, read from DB for new instance
		$caUser = CentralAuthUser::getPrimaryInstanceByName( 'GlobalUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertFalse( $caUser->isHidden() );
		$this->assertFalse( $caUser->isLocked() );
	}

	/**
	 * @covers ::attach
	 */
	public function testAttach() {
		$caUser = new class( 'GlobalUser' ) extends CentralAuthUser {
			protected function addLocalEdits( $wikiID ) {
				// This test can't connect to anotherwiki to fetch edit counts
			}
		};
		$caUser->attach( 'anotherwiki', 'admin', false );
		$this->assertTrue( $caUser->exists() );
		$this->assertContains( 'anotherwiki', $caUser->listAttached() );
	}

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiIntegrationTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp(): void {
		parent::setUp();
		$user = new CentralAuthTestUser(
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
		$user->save( $this->db );

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

		$u = new CentralAuthTestUser(
			'GlobalSuppressedUser',
			'GSUP@ssword',
			[
				'gu_id' => '1004',
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'metawiki', 'primary' ],
			]
		);
		$u->save( $this->db );

		$this->setMwGlobals( [
			'wgCentralAuthHiddenLevelMigrationStage' => SCHEMA_COMPAT_NEW,
		] );
	}

}
