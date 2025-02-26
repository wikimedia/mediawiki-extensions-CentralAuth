<?php

use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup
 */
class CentralAuthIdLookupTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	/** @var int[] */
	private $centralUsers = [
		'GlobalUser' => 1001,
		'GlobalLockedUser' => 1003,
		'GlobalSuppressedUser' => 1004,
		'GlobalUserUnattached' => 1007,
		'GlobalUserConflict' => 1008,
	];

	/**
	 * Setup a fresh set of global users for each test.
	 * Note: MediaWikiIntegrationTestCase::resetDB() will delete all tables between
	 * test runs, so no explicit tearDown() is needed.
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
		$user->save( $this->getDb() );

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
		$u->save( $this->getDb() );

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
		$u->save( $this->getDb() );

		$user = new CentralAuthTestUser(
			'GlobalUserUnattached',
			'GUUP@ssword',
			[ 'gu_id' => '1007' ],
			[
				[ 'metawiki', 'primary' ],
			],
			false
		);
		$user->save( $this->getDb() );

		$user = new CentralAuthTestUser(
			'GlobalUserConflict',
			'GUCP@ssword',
			[ 'gu_id' => '1008' ],
			[
				[ 'metawiki', 'primary' ],
			],
			false
		);
		$user->save( $this->getDb() );
		( new TestUser( 'GlobalUserConflict' ) );
	}

	private function newLookup( $strict = true ): CentralIdLookup {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthStrict => $strict,
			MainConfigNames::CentralIdLookupProviders => [
				'central' => [
					'class' => CentralAuthIdLookup::class,
					'services' => [
						'MainConfig',
						'CentralAuth.CentralAuthDatabaseManager',
					],
				],
			],
			MainConfigNames::CentralIdLookupProvider => 'central',
		] );
		return $this->getServiceContainer()->getCentralIdLookupFactory()->getLookup();
	}

	public function testRegistration() {
		$this->assertInstanceOf(
			CentralAuthIdLookup::class,
			$this->getServiceContainer()
				->getCentralIdLookupFactory()
				->getLookup( 'CentralAuth' )
		);
	}

	public function testLookupCentralIds() {
		$lookup = $this->newLookup();
		$permitted = $this->mockAnonAuthorityWithPermissions( [ 'centralauth-suppress' ] );
		$nonPermitted = $this->mockAnonAuthorityWithoutPermissions( [ 'centralauth-suppress' ] );

		$this->assertSame( [], $lookup->lookupCentralIds( [] ) );

		$expect = array_flip( $this->centralUsers );
		$expect[123] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2[$this->centralUsers['GlobalSuppressedUser']] = '';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupCentralIds( $arg, $permitted ) );
		$this->assertSame( $expect2, $lookup->lookupCentralIds( $arg, $nonPermitted ) );
	}

	public function testLookupUserNames() {
		$lookup = $this->newLookup();
		$permitted = $this->mockAnonAuthorityWithPermissions( [ 'centralauth-suppress' ] );
		$nonPermitted = $this->mockAnonAuthorityWithoutPermissions( [ 'centralauth-suppress' ] );

		$this->assertSame( [], $lookup->lookupUserNames( [] ) );

		$expect = $this->centralUsers;
		$expect['UTDoesNotExist'] = 'X';
		ksort( $expect );

		$expect2 = $expect;
		$expect2['GlobalSuppressedUser'] = 'X';

		$arg = array_fill_keys( array_keys( $expect ), 'X' );

		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, CentralIdLookup::AUDIENCE_RAW ) );
		$this->assertSame( $expect, $lookup->lookupUserNames( $arg, $permitted ) );
		$this->assertSame( $expect2, $lookup->lookupUserNames( $arg, $nonPermitted ) );

		// test looking up a single name, which uses a different code path
		foreach ( $expect as $name => $id ) {
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ],
				CentralIdLookup::AUDIENCE_RAW ) );
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ], $permitted ) );
		}
		foreach ( $expect2 as $name => $id ) {
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ] ) );
			$this->assertSame( [ $name => $id ], $lookup->lookupUserNames( [ $name => 'X' ], $nonPermitted ) );
		}
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
		$lookup = $this->newLookup();
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
		$lookup = $this->newLookup();
		$user = $lookup->localUserFromCentralId( $id );
		if ( $succeed ) {
			$this->assertInstanceOf( UserIdentity::class, $user );
			$this->assertSame( $username, $user->getName() );
		} else {
			$this->assertNull( $user );
		}
	}

	public static function provideIsAttached() {
		return [
			[
				1, 'GlobalUser', 'enwiki',
				true
			],
			[
				1, 'GlobalUser', 'foowiki',
				false
			],

			'Local user exists and is attached to the global user' => [
				1, 'GlobalUser', UserIdentityValue::LOCAL,
				true
			],
			'Local user exists and IS NOT attached to the global user' => [
				1, 'GlobalUserConflict', UserIdentityValue::LOCAL,
				false
			],
			'Local user DOES NOT exist, so can not be attached, but is owned by the global user' => [
				0, 'GlobalUserUnattached', UserIdentityValue::LOCAL,
				false, true, true
			],
			'Local user DOES NOT exist, so can not be attached, and IS NOT owned by the global user' => [
				0, 'GlobalUserUnattached', UserIdentityValue::LOCAL,
				false, false, false
			],

			[
				1, 'UTSysop', UserIdentityValue::LOCAL,
				false
			],
			[
				0, 'DoesNotExist', UserIdentityValue::LOCAL,
				false
			],
		];
	}

	/**
	 * @dataProvider provideIsAttached
	 * @param int $id
	 * @param string $username
	 * @param string $wikiId
	 * @param bool $isAttached
	 * @param bool|null $isOwned
	 * @param bool $strict
	 */
	public function testIsAttached( $id, $username, $wikiId, $isAttached, $isOwned = null, $strict = true ) {
		$user = new UserIdentityValue( $id, $username, $wikiId );
		$lookup = $this->newLookup( $strict );
		$this->assertSame( $isAttached, $lookup->isAttached( $user, $wikiId ) );
		$this->assertSame( $isOwned ?? $isAttached, $lookup->isOwned( $user, $wikiId ) );
	}

}
