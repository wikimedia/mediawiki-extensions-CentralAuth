<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @covers CentralAuthIdLookup
 */
class CentralAuthIdLookupTest extends CentralAuthTestCaseUsingDatabase {

	private $centralUsers = array(
		'GlobalUser' => 1001,
		'GlobalLockedUser' => 1003,
		'GlobalSuppressedUser' => 1004,
	);

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp() {
		global $wgGroupPermissions;

		parent::setUp();

		if ( !class_exists( 'CentralIdLookup' ) ) {
			$this->markTestSkipped( 'CentralIdLookup does not exist' );
		}

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

		$this->stashMwGlobals( array( 'wgGroupPermissions' ) );
		$wgGroupPermissions['centralauth-id-lookup-test']['centralauth-oversight'] = true;
	}

	public function addDBData() {
		$user = User::newFromName( "UTCentralAuthIdLookup1" );
		if ( $user->getId() == 0 ) {
			$user->addToDatabase();
		}
		$user->addGroup( 'centralauth-id-lookup-test' );

		$user = User::newFromName( "UTCentralAuthIdLookup2" );
		if ( $user->getId() == 0 ) {
			$user->addToDatabase();
		}
	}

	public function testRegistration() {
		$this->assertInstanceOf( 'CentralAuthIdLookup', CentralIdLookup::factory( 'CentralAuth' ) );
	}

	public function testLookupCentralIds() {
		$lookup = new CentralAuthIdLookup();
		$user1 = User::newFromName( 'UTCentralAuthIdLookup1' );
		$user2 = User::newFromName( 'UTCentralAuthIdLookup2' );

		$this->assertTrue( $user1->isAllowed( 'centralauth-oversight' ), 'sanity check' );
		$this->assertFalse( $user2->isAllowed( 'centralauth-oversight' ), 'sanity check' );

		$this->assertSame( array(), $lookup->lookupCentralIds( array() ) );

		$expect = array_flip( $this->centralUsers );
		$expect[123] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2[$this->centralUsers['GlobalSuppressedUser']] = '';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, $user1 ) );
		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg, $user2 ) );
	}

	public function testLookupUserNames() {
		$lookup = new CentralAuthIdLookup();
		$user1 = User::newFromName( 'UTCentralAuthIdLookup1' );
		$user2 = User::newFromName( 'UTCentralAuthIdLookup2' );

		$this->assertTrue( $user1->isAllowed( 'centralauth-oversight' ), 'sanity check' );
		$this->assertFalse( $user2->isAllowed( 'centralauth-oversight' ), 'sanity check' );

		$this->assertSame( array(), $lookup->lookupUserNames( array() ) );

		$expect = $this->centralUsers;
		$expect['UTDoesNotExist'] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2['GlobalSuppressedUser'] = 'X';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, $user1 ) );
		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg, $user2 ) );
	}

	public static function provideLocalUsers() {
		return array(
			array( 'GlobalUser', 1001, true ),
			array( 'UTSysop', 1, false ),
			array( 'DoesNotExist', 123, false ),
		);
	}

	/**
	 * @dataProvider provideLocalUsers
	 * @param string $username
	 * @param int $id
	 * @param bool $succeed
	 */
	public function testCentralIdFromLocalUser( $username, $id, $succeed ) {
		$user = User::newFromName( $username );
		$lookup = new CentralAuthIdLookup();
		$ret = $lookup->centralIdFromLocalUser( $user );
		if ( $succeed ) {
			$this->assertSame( $id, $ret );
		} else {
			$this->assertSame( 0, $ret );
		}
	}

	/**
	 * @dataProvider provideLocalUsers
	 * @param string $username
	 * @param int $id
	 * @param bool $succeed
	 */
	public function testLocalUserFromCentralId( $username, $id, $succeed ) {
		$lookup = new CentralAuthIdLookup();
		$user = $lookup->localUserFromCentralId( $id );
		if ( $succeed ) {
			$this->assertInstanceOf( 'User', $user );
			$this->assertSame( $username, $user->getName() );
		} else {
			$this->assertNull( $user );
		}
	}

	public static function provideIsAttached() {
		return array(
			array( 'GlobalUser', 'enwiki', true ),
			array( 'GlobalUser', 'foowiki', false ),
			array( 'GlobalUser', null, true ),
			array( 'UTSysop', null, false ),
			array( 'DoesNotExist', null, false ),
		);
	}

	/**
	 * @dataProvider provideIsAttached
	 * @param string $username
	 * @param string $wikiId
	 * @param bool $succeed
	 */
	public function testIsAttached( $username, $wikiId, $succeed ) {
		$user = User::newFromName( $username );
		$lookup = new CentralAuthIdLookup();
		$this->assertSame( $succeed, $lookup->isAttached( $user, $wikiId ) );
	}

}
