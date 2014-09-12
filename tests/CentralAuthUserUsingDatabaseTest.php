<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthUserUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {

	/**
	 * @covers CentralAuthUser::exists
	 * @covers CentralAuthUser::getId
	 * @covers CentralAuthUser::getHomeWiki
	 * @covers CentralAuthUser::getStateHash
	 */
	public function testBasicAttrs() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() );
		$this->assertEquals( 1001, $caUser->getId() );
		$this->assertEquals( wfWikiID(), $caUser->getHomeWiki() );
		$this->assertSame( false, $caUser->isLocked() );
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
		$caNewUser = new CentralAuthUser( 'NewGlobalUser' );

		$this->assertSame(
			false,
			$caNewUser->exists()
		);
		$caNewUser->register( '1234567890', 'test@test.com' );
		$this->assertSame(
			true,
			$caNewUser->exists()
		);
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
	}

}
