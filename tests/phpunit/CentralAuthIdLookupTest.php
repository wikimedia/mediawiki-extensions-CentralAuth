<?php

/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @group Database
 * @covers CentralAuthIdLookup
 */
class CentralAuthIdLookupTest extends CentralAuthUsingDatabaseTestCase {

	private $centralUsers = [
		'GlobalUser' => 1001,
		'GlobalLockedUser' => 1003,
		'GlobalSuppressedUser' => 1004,
	];

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiTestCase::resetDB() will delete all tables between
	 * test runs, so no explicite tearDown() is needed.
	 */
	protected function setUp() {
		global $wgGroupPermissions;

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

		$this->setGroupPermissions( 'centralauth-id-lookup-test', 'centralauth-oversight', true );
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

		$this->assertSame( [], $lookup->lookupCentralIds( [] ) );

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

		$this->assertSame( [], $lookup->lookupUserNames( [] ) );

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
		return [
			[ 'GlobalUser', 1001, true ],
			[ 'UTSysop', 1, false ],
			[ 'DoesNotExist', 123, false ],
		];
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
		return [
			[ 'GlobalUser', 'enwiki', true ],
			[ 'GlobalUser', 'foowiki', false ],
			[ 'GlobalUser', null, true ],
			[ 'UTSysop', null, false ],
			[ 'DoesNotExist', null, false ],
		];
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
