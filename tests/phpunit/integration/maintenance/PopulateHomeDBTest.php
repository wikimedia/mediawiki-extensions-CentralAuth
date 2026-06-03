<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\Maintenance\PopulateHomeDB;
use MediaWiki\MainConfigNames;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\PopulateHomeDB
 * @group Database
 */
class PopulateHomeDBTest extends MaintenanceBaseTestCase {

	private int $nextGuId = 3000;
	private const OTHER_WIKI_ID = 'otherwiki';

	protected function setUp(): void {
		parent::setUp();

		// Set up site configuration for the current wiki and a "foreign" wiki
		// which will be used to simulate unattached accounts.
		$currentSite = new MediaWikiSite();
		$currentSite->setGlobalId( WikiMap::getCurrentWikiId() );
		$currentSite->setPath( MediaWikiSite::PATH_PAGE, 'https://example.com/wiki/$1' );

		$otherSite = new MediaWikiSite();
		$otherSite->setGlobalId( self::OTHER_WIKI_ID );
		$otherSite->setPath( MediaWikiSite::PATH_PAGE, 'https://other.xample.com/wiki/$1' );

		$this->setService( 'SiteLookup', new HashSiteStore( [ $currentSite, $otherSite ] ) );

		$this->overrideConfigValue( MainConfigNames::LocalDatabases, [
			WikiMap::getCurrentWikiId(),
			self::OTHER_WIKI_ID,
		] );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass(): string {
		return PopulateHomeDB::class;
	}

	private function getCentralAuthTestUsername(
		array $wikis,
		string $homeDb = ''
	): string {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$centralUserProperties = [
			'gu_id' => ++$this->nextGuId,
			'gu_registration' => $user->getRegistration(),
			'gu_email' => $user->getEmail(),
			'gu_email_authenticated' => $user->getEmailAuthenticationTimestamp(),
		];
		$centralUserProperties['gu_home_db'] = $homeDb;

		$centralAuthTestUser = new CentralAuthTestUser(
			$user->getName(),
			$testUser->getPassword(),
			$centralUserProperties,
			$wikis
		);

		return $centralAuthTestUser->save( $this->getDb() )
			->getCentralUser()
			->getName();
	}

	private function assertHomeDbForUser( string $username, string $expectedHomeDb ): void {
		$this->newSelectQueryBuilder()
			->select( 'gu_home_db' )
			->from( 'globaluser' )
			->where( [ 'gu_name' => $username ] )
			->caller( __METHOD__ )
			->assertFieldValue( $expectedHomeDb );
	}

	public function testWhenNoUsersNeedPopulation(): void {
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( "done", $actualOutput );
	}

	public function testWhenSomeUsersNeedPopulation(): void {
		$skippedUsername = $this->getCentralAuthTestUsername(
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ],
			self::OTHER_WIKI_ID
		);
		$updatedUsername = $this->getCentralAuthTestUsername( [ [ WikiMap::getCurrentWikiId(), 'primary' ] ] );

		$this->expectOutputString( "1\ndone.\n" );
		$this->maintenance->execute();

		$this->assertHomeDbForUser( $skippedUsername, self::OTHER_WIKI_ID );
		$this->assertHomeDbForUser( $updatedUsername, WikiMap::getCurrentWikiId() );
	}

	public function testProcessesUsersInBatches(): void {
		$usernames = [
			$this->getCentralAuthTestUsername( [ [ WikiMap::getCurrentWikiId(), 'primary' ] ] ),
			$this->getCentralAuthTestUsername( [ [ WikiMap::getCurrentWikiId(), 'new' ] ] ),
			$this->getCentralAuthTestUsername( [ [ WikiMap::getCurrentWikiId(), 'primary' ] ] ),
		];

		$this->maintenance->loadWithArgv( [ '--batch-size', 1 ] );

		$this->expectOutputString( "1\n2\n3\ndone.\n" );
		$this->maintenance->execute();

		foreach ( $usernames as $username ) {
			$this->assertHomeDbForUser( $username, WikiMap::getCurrentWikiId() );
		}
	}
}
