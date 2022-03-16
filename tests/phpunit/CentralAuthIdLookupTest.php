<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\UserIdentity;

/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthIdLookup
 */
class CentralAuthIdLookupTest extends CentralAuthUsingDatabaseTestCase {
	use MockAuthorityTrait;

	/** @var int[] */
	private $centralUsers = [
		'GlobalUser' => 1001,
		'GlobalLockedUser' => 1003,
		'GlobalSuppressedUser' => 1004,
	];

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
	}

	private function newLookup(): CentralIdLookup {
		$factory = new CentralIdLookupFactory(
			new ServiceOptions(
				CentralIdLookupFactory::CONSTRUCTOR_OPTIONS,
				[
					'CentralIdLookupProviders' => [
						'central' => [
							'class' => CentralAuthIdLookup::class,
							'services' => [
								'CentralAuth.CentralAuthDatabaseManager',
							]
						],
					],
					'CentralIdLookupProvider' => 'central',
				]
			),
			$this->getServiceContainer()->getObjectFactory(),
			$this->getServiceContainer()->getUserIdentityLookup()
		);
		return $factory->getLookup();
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
		$lookup = $this->newLookup();
		$this->assertSame( $succeed, $lookup->isAttached( $user, $wikiId ) );
	}

}
