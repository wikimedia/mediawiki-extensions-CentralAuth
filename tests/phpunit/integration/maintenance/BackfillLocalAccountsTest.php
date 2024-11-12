<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Maintenance\BackfillLocalAccounts;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Password\PasswordFactory;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\BackfillLocalAccounts
 * @group Database
 */
class BackfillLocalAccountsTest extends MaintenanceBaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		// These tests only work if CheckUser is loaded
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
	}

	public function getMaintenanceClass() {
		return BackfillLocalAccounts::class;
	}

	protected function getMockedAccountCreator() {
		// testing the account creating piece, so mock the underlying bits
		$mockBackfiller = $this->getMockBuilder( BackfillLocalAccounts::class )
			->onlyMethods( [ 'getGlobalUserBatch', 'checkUserAndGetHomeWiki' ] )
			->getMock();
		return $mockBackfiller;
	}

	protected function setBackfillerReturns( $mockBackfiller ) {
		$results = [];
		foreach ( range( 1, 5 ) as $uid ) {
			$results[] = [ 'gu_name' => "Fake user $uid", 'gu_id' => strval( 100 + $uid ) ];
		}
		$mockBackfiller->method( 'getGlobalUserBatch' )
			->willReturnOnConsecutiveCalls(
				[ 106, new FakeResultWrapper( $results ) ],
				[ 111, new FakeResultWrapper( [] ) ]
			);

		$mockBackfiller->method( 'checkUserAndGetHomeWiki' )
			->willReturnOnConsecutiveCalls( 'testwiki', 'elwiki', 'testwiki', null, 'testwiki' );
		return $mockBackfiller;
	}

	protected function getMockAccountLookup( $db ) {
		$mockLookup = $this->createMock( AccountCreationDetailsLookup::class );
		$mockLookup->method( 'getAccountCreationIPAndUserAgent' )->willReturnCallback(
			static function ( $username ) {
				return [
					'Fake user 1' => [ 'ip' => '192.168.1.5', 'agent' => 'Fake user agent 1' ],
					'Fake user 2' => [ 'ip' => '192.168.1.6', 'agent' => 'Fake user agent 2' ],
					'Fake user 3' => [ 'ip' => '192.168.1.6', 'agent' => 'Fake user agent 3' ],
					'Fake user 5' => [ 'ip' => '192.168.1.6', 'agent' => 'Fake user agent 5' ]
				][ $username ];
			}
		);
		return $mockLookup;
	}

	protected function makeCAGlobalUserEntries( $cadb, $ids, $homeWikis ) {
		// we will have 'Fake User 1', 2,3,5 with guids 101, 102, etc
		// the rest of the fields we can just shove arbitrary things into
		$config = RequestContext::getMain()->getConfig();
		$passwordFactory = new PasswordFactory(
			$config->get( MainConfigNames::PasswordConfig ),
			$config->get( MainConfigNames::PasswordDefault )
		);
		$passwordHash = $passwordFactory->newFromPlaintext( "Fake password here" )->toString();

		$row = [
			'gu_password' => $passwordHash,
			'gu_locked' => 0,
			'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
			'gu_registration' => '20240805084012',
			'gu_email_authenticated' => '20240826093105',
		];

		foreach ( $ids as $id ) {
			$row['gu_name'] = "Fake user {$id}";
			$row['gu_id'] = 100 + $id;
			$row['gu_auth_token'] = "123{$id}";
			$row['gu_email'] = "fakeuser{$id}@localhost";
			$row['gu_home_db'] = $homeWikis[ $id - 1 ];

			$cadb->newInsertQueryBuilder()
				->insertInto( 'globaluser' )
				->row( $row )
				->caller( __METHOD__ )
				->execute();
		}
	}

	protected function makeCALocalUserEntries( $cadb, $ids ) {
		// we will have the same 'Fake User 1', 2,3,5 with luids 151, 152, etc
		// the rest of the fields we can still just shove arbitrary things into
		$localWiki = WikiMap::getCurrentWikiId();

		foreach ( $ids as $id ) {
			$row = [
				'lu_wiki' => $localWiki,
				'lu_name' => "Fake user {$id}",
				'lu_local_id' => 150 + $id,
				'lu_global_id' => 100 + $id,
				'lu_attached_method' => 'new',
				'lu_attached_timestamp' => '20240805084012',
				'lu_attachment_method' => null,
			];

			$cadb->newInsertQueryBuilder()
				->insertInto( 'localuser' )
				->row( $row )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public function testCheckAndCreateAccountsDryRun() {
		$mocked = $this->getMockedAccountCreator();
		$mocked = $this->setBackfillerReturns( $mocked );
		$mocked = TestingAccessWrapper::newFromObject( $mocked );
		$mocked->setBatchSize( 5 );

		$db = $this->getDb();

		$localWiki = WikiMap::getCurrentWikiId();

		$dryrun = true;
		$verbose = false;
		$startGlobalUID = 101;
		$maxGlobalUID = 105;
		$localWiki = WikiMap::getCurrentWikiId();

		$mocked->checkAndCreateAccounts(
			$db,
			$this->getServiceContainer()->getUserFactory(),
			$this->getMockAccountLookup( $db ),
			$this->createMock( LBFactory::class ),
			$dryrun, $verbose,
			$startGlobalUID, $maxGlobalUID,
			$localWiki, null
		);

		$this->expectOutputString(
			"Would create user Fake user 1 from guid 101 and home wiki testwiki\n" .
			"Would create user Fake user 2 from guid 102 and home wiki elwiki\n" .
			"Would create user Fake user 3 from guid 103 and home wiki testwiki\n" .
			"Would create user Fake user 5 from guid 105 and home wiki testwiki\n"
		);
	}

	public function testCheckAndCreateAccountsReally() {
		$mocked = $this->getMockedAccountCreator();
		$mocked = $this->setBackfillerReturns( $mocked );
		$mocked = TestingAccessWrapper::newFromObject( $mocked );
		$mocked->mBatchSize = 5;

		$db = $this->getDb();

		$cadb = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		$good_uids = [ 1, 2, 3, 5 ];
		$homeWikis = [ 'testwiki', 'elwiki', 'testwiki', 'none', 'testwiki' ];
		$this->makeCAGlobalUserEntries( $cadb, $good_uids, $homeWikis );

		$dryrun = false;
		$verbose = true;
		$startGlobalUID = 100;
		$maxGlobalUID = 105;
		$localWiki = WikiMap::getCurrentWikiId();

		$mocked->checkAndCreateAccounts(
			$db,
			$this->getServiceContainer()->getUserFactory(),
			$this->getMockAccountLookup( $db ),
			$this->createMock( LBFactory::class ),
			$dryrun, $verbose,
			$startGlobalUID, $maxGlobalUID,
			$localWiki
		);

		$this->expectOutputString(
			"Using ip 192.168.1.5 and agent Fake user agent 1 \n" .
			"User 'Fake user 1' created\n" .
			"Using ip 192.168.1.6 and agent Fake user agent 2 \n" .
			"User 'Fake user 2' created\n" .
			"Using ip 192.168.1.6 and agent Fake user agent 3 \n" .
			"User 'Fake user 3' created\n" .
			"Using ip 192.168.1.6 and agent Fake user agent 5 \n" .
			"User 'Fake user 5' created\n" .
			"Created users: 4, done.\n"
		);
	}

	public function testGetGlobalUserBatch() {
		$db = $this->getDb();

		$this->maintenance->mBatchSize = 5;

		$cadb = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		// we don't use these values in this test but the table entries need them
		$homeWikis = array_fill( 0, 11, 'none' );

		$this->makeCAGlobalUserEntries( $cadb, [ 1, 2, 3, 4, 5, 6, 7, 9, 10, 11 ], $homeWikis );
		$this->makeCALocalUserEntries( $cadb, [ 1, 3, 6, 8, 9 ] );

		$startGlobalUID = 101;
		$maxGlobalUID = 112;
		$localWiki = WikiMap::getCurrentWikiId();

		[ $nextUID, $results ] = $this->maintenance->getGlobalUserBatch(
			$cadb,
			$startGlobalUID, $maxGlobalUID,
			$localWiki
		);

		$this->assertEquals( 106, $nextUID );
		$this->assertSame( 3, $results->numRows(), "Three global user entries should be in first batch" );

		$rows = [];
		$expectedRows = [
			0 => (object)[ 'gu_name' => 'Fake user 2', 'gu_id' => '102' ],
			1 => (object)[ 'gu_name' => 'Fake user 4', 'gu_id' => '104' ],
			2 => (object)[ 'gu_name' => 'Fake user 5', 'gu_id' => '105' ]
		];
		foreach ( $results as $i => $row ) {
			$rows[$i] = $row;
		}
		$this->assertEquals( $expectedRows, $rows );
	}
}
