<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthUserDBTest extends CentralAuthDBTest {

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
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( 1001, $caUser->getId() );
		$this->assertEquals( 'GlobalUser', $caUser->getName() );
		$this->assertEquals( wfWikiID(), $caUser->getHomeWiki() );
		$this->assertEquals( true, $caUser->isAttached() );
		$this->assertEquals( false, $caUser->isLocked() );
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
		$this->assertEquals( true, $caUser->exists() );
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
		$this->assertEquals(
			0,
			preg_match( '/[^a-f0-9]/', $token )
		);
	}

	/**
	 * @covers CentralAuthUser::register
	 */
	public function testRegister() {
		$caUserNew = new CentralAuthUser( 'RegTest' );
		$caUserNew->register( "R3gT3stP@ssword", "user@localhost" );

		$caUser = new CentralAuthUser( 'RegTest' );
		$this->assertEquals( true, $caUser->exists() );
	}

	/**
	 * @covers CentralAuthUser::isLocked
	 */
	public function testLocked() {
		$caUser = new CentralAuthUser( 'GlobalLockedUser' );
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( true, $caUser->isLocked() );
	}

	/**
	 * @covers CentralAuthUser::isHidden
	 * @covers CentralAuthUser::isOversighted
	 * @covers CentralAuthUser::getHiddenLevel
	 */
	public function testHidden() {
		$caUser = new CentralAuthUser( 'GlobalSuppressedUser' );
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( true, $caUser->isHidden() );
		$this->assertEquals( true, $caUser->isOversighted() );
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
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( false, $caUser->isHidden() );
		$this->assertEquals( false, $caUser->isLocked() ); #sanity

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
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( true, $caUser->isLocked() );
		$this->assertEquals( true, $caUser->isHidden() );

		$caUser->adminUnlock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_NONE );

		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( false, $caUser->isHidden() );
		$this->assertEquals( false, $caUser->isLocked() );
	}


	/**
	 * @covers CentralAuthUser::attach
	 */
	public function testAttach() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$caUser->attach( 'anotherwiki', 'admin', false );
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( true, in_array( 'anotherwiki', $caUser->listAttached() ) );
	}

	/**
	 * TODO
	 * @covers CentralAuthUser::adminLockHide
	 * @dataProvider provideAdminLockHide
	 */
	public function testAdminLockHide( $user, $setLocked, $setHidden, $reason ) {
		$this->assertEquals( true, true );
	}

	public function provideAdminLockHide() {
		return array(
			array( '', '', '', '' ),
		);
	}

	/**
	 * TODO
	 * @covers CentralAuthUser::getPasswordHash
	 */
}
