<?php
namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MailAddress;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Maintenance\SendConfirmAndMigrateEmail;
use MediaWiki\Mail\IEmailer;
use MediaWiki\MainConfigNames;
use MediaWiki\Site\HashSiteStore;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\SendConfirmAndMigrateEmail
 * @group Database
 */
class SendConfirmAndMigrateEmailTest extends MaintenanceBaseTestCase {
	private const OTHER_WIKI_ID = 'otherwiki';

	private IEmailer $emailer;

	protected function setUp(): void {
		parent::setUp();

		$this->emailer = $this->createMock( IEmailer::class );

		$this->setService( 'Emailer', $this->emailer );

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

		// Configure CentralAuth and LBFactory to return the test database connection
		// for the "foreign" wiki as well.
		$centralAuthDatabaseManager = $this->createMock( CentralAuthDatabaseManager::class );
		$centralAuthDatabaseManager->method(
			$this->logicalOr(
				'getCentralDBFromRecency',
				'getCentralPrimaryDB',
				'getCentralReplicaDB',
				'getLocalDB',
				'getLocalDBFromRecency',
			)
		)->willReturn( $this->getDb() );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( $this->logicalOr( 'getReplicaDatabase', 'getPrimaryDatabase' ) )
			->willReturn( $this->getDb() );
		$lbFactory->method( 'getMainLB' )
			->willReturn( $this->getServiceContainer()->getDBLoadBalancer() );

		$this->setService( 'CentralAuth.CentralAuthDatabaseManager', $centralAuthDatabaseManager );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
	}

	protected function getMaintenanceClass() {
		return SendConfirmAndMigrateEmail::class;
	}

	/**
	 * @dataProvider provideShouldNotSendEmail
	 *
	 * @param bool $hasConfirmedEmail Whether the user has confirmed their email address.
	 * @param bool $isDryRun Whether the maintenance script should be run dry run mode.
	 * @param bool $globalAccountExists Whether a central user exists for the user.
	 * @param bool $globalAccountAttached Whether the user's central user is attached to the local wiki.
	 * @param bool $hasUnattachedAccounts Whether the user has unattached accounts on other wikis.
	 * @param string $expectedOutputPattern A PCRE pattern that the output of the script should match.
	 */
	public function testShouldNotSendEmail(
		bool $hasConfirmedEmail,
		bool $isDryRun,
		bool $globalAccountExists,
		bool $globalAccountAttached,
		bool $hasUnattachedAccounts,
		string $expectedOutputPattern
	): void {
		$user = $this->getMutableTestUser()->getUser();
		$username = $user->getName();
		$this->expectOutputRegex( $expectedOutputPattern );

		$wikis = [];
		// Attach the central user for this user if needed.
		if ( $globalAccountAttached ) {
			$wikis[] = [ WikiMap::getCurrentWikiId(), 'primary' ];
		}

		// Add an unattached account on a different wiki optionally.
		if ( $hasUnattachedAccounts ) {
			$this->getDb()
				->newInsertQueryBuilder()
				->insert( 'localnames' )
				->row( [
					'ln_wiki' => self::OTHER_WIKI_ID,
					'ln_name' => $username,
				] )
				->execute();
		}

		// Setup a central user for this user if needed.
		if ( $globalAccountExists ) {
			$centralUser = new CentralAuthTestUser(
				$username,
				'GUP@ssword',
				[
					'gu_id' => 1001,
					'gu_registration' => $user->getRegistration(),
					'gu_email_authenticated' => $hasConfirmedEmail ? wfTimestampNow() : null,
				],
				$wikis
			);
			$centralUser->save( $this->getDb() );
		}

		$this->emailer->expects( $this->never() )
			->method( 'send' );

		$params = [ 'username' => $username ];
		if ( $isDryRun ) {
			$params['dryrun'] = true;
		}

		$this->maintenance->loadParamsAndArgs( null, $params );
		$this->maintenance->execute();
	}

	public static function provideShouldNotSendEmail(): iterable {
		yield 'user with confirmed email' => [
			'hasConfirmedEmail' => true,
			'isDryRun' => false,
			'globalAccountExists' => true,
			'globalAccountAttached' => true,
			'hasUnattachedAccounts' => true,
			'expectedOutputPattern' => "/ERROR: The user '[^@]+@\S+' already has a confirmed email address/",
		];

		yield 'user without confirmed email, dry run' => [
			'hasConfirmedEmail' => false,
			'isDryRun' => true,
			'globalAccountExists' => true,
			'globalAccountAttached' => true,
			'hasUnattachedAccounts' => true,
			'expectedOutputPattern' => '/Would have sent email/',
		];

		yield 'user without unattached accounts' => [
			'hasConfirmedEmail' => false,
			'isDryRun' => false,
			'globalAccountExists' => true,
			'globalAccountAttached' => true,
			'hasUnattachedAccounts' => false,
			'expectedOutputPattern' => "/ERROR: No unattached accounts for '[^']+'/",
		];

		yield 'user without a locally attached global account' => [
			'hasConfirmedEmail' => false,
			'isDryRun' => false,
			'globalAccountExists' => true,
			'globalAccountAttached' => false,
			'hasUnattachedAccounts' => false,
			'expectedOutputPattern' => "/ERROR: '[^@]+@\S+' is not attached to the global user/",
		];

		yield 'user without a global account' => [
			'hasConfirmedEmail' => false,
			'isDryRun' => false,
			'globalAccountExists' => false,
			'globalAccountAttached' => false,
			'hasUnattachedAccounts' => false,
			'expectedOutputPattern' => "/ERROR: No global account for '[^']+'/",
		];
	}

	/**
	 * @dataProvider provideShouldSendEmail
	 *
	 * @param bool $hasConfirmedEmail Whether the user has confirmed their email address.
	 * @param bool $shouldSendEvenIfConfirmed Whether to send an email even if the user
	 * has already confirmed their email address.
	 */
	public function testShouldSendEmail(
		bool $hasConfirmedEmail,
		bool $shouldSendEvenIfConfirmed
	): void {
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'qqx',
			MainConfigNames::CanonicalServer => 'https://test.example.org',
			MainConfigNames::ArticlePath => '/wiki/$1',
		] );

		ConvertibleTimestamp::setFakeTime( '20250501000000' );

		$user = $this->getMutableTestUser()->getUser();
		$username = $user->getName();
		$this->expectOutputRegex( "/Sent email to $username/" );

		// Setup a central user for this user with an unattached account on another wiki.
		$this->getDb()
			->newInsertQueryBuilder()
			->insert( 'localnames' )
			->row( [
				'ln_wiki' => self::OTHER_WIKI_ID,
				'ln_name' => $username,
			] )
			->execute();

		$centralUser = new CentralAuthTestUser(
			$username,
			'GUP@ssword',
			[
				'gu_id' => 1001,
				'gu_registration' => $user->getRegistration(),
				'gu_email_authenticated' => $hasConfirmedEmail ? wfTimestampNow() : null,
			],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$centralUser->save( $this->getDb() );

		// The link to Special:MergeAccount should include an email confirmation token
		// if the user has not confirmed their email address yet.
		$mergeAccountPathPattern = $hasConfirmedEmail ? '' : '/\S+';

		$this->emailer->expects( $this->once() )
			->method( 'send' )
			->with(
				[ MailAddress::newFromUser( $user ) ],
				$this->anything(),
				'(centralauth-finishglobaliseemail_subject)',
				$this->matchesRegularExpression(
					"#\\(centralauth-finishglobaliseemail_body: 127\\.0\\.0\\.1, {$username}," .
					" https://test\\.example\\.org/wiki/Special:MergeAccount{$mergeAccountPathPattern}," .
					" 00:00, 15 \\(may_long\\) 2025," .
					' https://test\.example\.org/wiki/Special:InvalidateEmail/\S+,' .
					' 15 \(may_long\) 2025, 00:00\)#'
				)
			);

		$params = [ 'username' => $username ];
		if ( $shouldSendEvenIfConfirmed ) {
			$params['confirmed'] = true;
		}

		$this->maintenance->loadParamsAndArgs( null, $params );
		$this->maintenance->execute();
	}

	public static function provideShouldSendEmail(): iterable {
		yield 'user with confirmed email' => [
			'hasConfirmedEmail' => true,
			'shouldSendEvenIfConfirmed' => true,
		];

		yield 'user without confirmed email' => [
			'hasConfirmedEmail' => false,
			'shouldSendEvenIfConfirmed' => false,
		];
	}
}
