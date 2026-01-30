<?php
/**
 * Create missing local users for existing global user accounts.
 *
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\RawSQLExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\ScopedCallback;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Given a starting date, check for global users created on or
 * later than that date, that do not have local accounts on the
 * wiki this script is run on, and attempt to create them,
 * using the user agent and ip address from the creation
 * of the local account on their home wiki, if that info
 * is available, or skipping the account creation otherwise.
 * Errors will be shown if a missing account cannot be created.
 */
class BackfillLocalAccounts extends Maintenance {

	private ?User $performer;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addOption( 'startdate', 'Backfill for global users created later than this date', true, true );
		$this->addOption( 'dryrun', 'Display commands that would be run instead of running them', false, false );
		$this->addOption( 'verbose', 'Display extra progress information while running', false, false );
		$this->setBatchSize( $this->getOption( 'batch-size', 1000 ) );
	}

	/**
	 * Get the smallest global uid registered on or after the specified start date
	 */
	private function getStartUID( IReadableDatabase $cadb, string $startdate ): int|false {
		return $cadb->newSelectQueryBuilder()
			->select( 'gu_id' )
			->from( 'globaluser' )
			->where( $cadb->expr( 'gu_registration', '>=', $startdate ) )
			->orderBy( 'gu_id', SelectQueryBuilder::SORT_ASC )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Get the maximum global uid in the central auth database
	 */
	private function getMaxUID( IReadableDatabase $cadb ): int|false {
		return $cadb->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Create a local account on the wiki this script is running on,
	 * for the specific username
	 */
	private function createLocalAccount( string $username, bool $verbose ): void {
		$performer = $this->performer ?? new UltimateAuthority(
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);
		$status = CentralAuthServices::getForcedLocalCreationService()->attemptAutoCreateLocalUserFromName(
			$username,
			$performer,
			"Backfilled by autocreation script"
		);

		if ( !$status->isGood() ) {
			$this->error( "autoCreateUser failed for $username:" );
			$this->error( $status );
			return;
		}

		if ( $verbose ) {
			$this->output( "User '$username' created\n" );
		}
	}

	/**
	 * Make sure the user does not already exist locally,
	 * and get the home wiki for the user, if possible
	 *
	 * @return ?string home wiki of user, or null on error/missing
	 * @throws RuntimeException
	 */
	public function checkUserAndGetHomeWiki( string $gu_name, UserFactory $userFactory ): ?string {
		$user = $userFactory->newFromName( $gu_name );
		if ( !$user ) {
			$this->error( "Bad user name " . $gu_name );
		}
		$globalUser = CentralAuthUser::getInstanceByName( $gu_name );
		$homeWiki = $globalUser->getHomeWiki();
		if ( !$homeWiki ) {
			$this->output( "Skipping user name " . $gu_name . " , missing home wiki\n" );
			return null;
		}
		return $homeWiki;
	}

	/**
	 * Return session info containing the ip address and user agent for the specified
	 * username, if found, or null otherwise
	 *
	 * @return array{userId: 0, ip: string, headers: array, sessionId: ''}|null
	 */
	private function getFakeSession(
		AccountCreationDetailsLookup $accountLookup,
		IReadableDatabase $dbr,
		string $gu_name,
		string $gu_registration,
		bool $verbose
	): ?array {
		$fakeSession = null;

		$accountInfo = null;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			$accountInfo = $accountLookup->getAccountCreationIPAndUserAgent( $gu_name, $dbr );
			if ( $accountInfo === null ) {
				// maybe this account was created by someone else; we'll try to get the
				// performer info instead
				[ $performer, $logId ] = $accountLookup->findPerformerAndLogId( $dbr, $gu_name, $gu_registration );
				if ( $performer ) {
					$accountInfo = $accountLookup->getAccountCreationIPAndUserAgent(
						$performer,
						$dbr,
						$logId
					);
				}
			}
		}

		if ( $accountInfo !== null ) {
			$fakeSession = [
				// no user in the local wiki, no session either
				'userId' => 0,
				'sessionId' => '',
				// the useful bits are here
				'ip' => $accountInfo['ip'],
				'headers' => [ 'User-Agent' => $accountInfo['agent'] ] ];
			if ( $verbose ) {
				$this->output( "Using ip {$fakeSession['ip']} and agent {$fakeSession['headers']['User-Agent']} \n" );
			}
		}

		return $fakeSession;
	}

	/**
	 * @return array{0:int,1:IResultWrapper}
	 */
	protected function getGlobalUserBatch(
		IReadableDatabase $cadb, int $batchStartUID, int $maxGlobalUID, string $wikiID
	): array {
		$subQuery = $cadb->newSelectQueryBuilder()
			->select( '1' )
			->from( 'localuser' )
			->where( 'lu_name = gu_name' )
			->andWhere( [ 'lu_wiki' => $wikiID ] );

		$result = null;
		do {
			$result = $cadb->newSelectQueryBuilder()
				->select( [ 'gu_name', 'gu_id', 'gu_registration' ] )
				->from( 'globaluser' )
				->where( $cadb->expr( 'gu_id', '>=', $batchStartUID ) )
				->andWhere( $cadb->expr( 'gu_id', '<', $batchStartUID + $this->mBatchSize ) )
				// we want to filter out rows where there is already a corresponding
				// local user on the wiki where this script is being run
				->andWhere( new RawSQLExpression( 'NOT EXISTS(' . $subQuery->getSQL() . ')' ) )
				->orderBy( 'gu_id', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__ )
				->fetchResultSet();

			$batchStartUID += $this->mBatchSize;
			if ( $batchStartUID > $maxGlobalUID ) {
				break;
			}
		} while ( !$result->numRows() );
		return [ (int)$batchStartUID, $result ];
	}

	/**
	 * Retrieve global users in batches with uid in the specified
	 * range, and if we can find their home wiki, create a
	 * local user on the wiki where this script runs, with the
	 * user's ip and user agent from account creation on their
	 * home wiki if available, otherwise skip
	 */
	public function checkAndCreateAccounts(
		IReadableDatabase $cadb,
		UserFactory $userFactory,
		AccountCreationDetailsLookup $accountLookup,
		LBFactory $lbFactory,
		bool $dryrun,
		bool $verbose,
		int $startGlobalUID,
		int $maxGlobalUID,
		string $wikiID
	): void {
		$createdUsers = 0;

		$dbw = $this->getPrimaryDB();
		do {
			[ $startGlobalUID, $result ] = $this->getGlobalUserBatch( $cadb, $startGlobalUID, $maxGlobalUID, $wikiID );

			foreach ( $result as $row ) {
				$homeWiki = $this->checkUserAndGetHomeWiki( $row->gu_name, $userFactory );
				if ( !$homeWiki ) {
					continue;
				}

				// get the user agent and ip address with which the user account was created on
				// their home wiki, if available, and create a local account for that user,
				// with that user agent and ip
				$dbr = $lbFactory->getReplicaDatabase( $homeWiki );

				if ( $dryrun ) {
					$this->output( "Would create user $row->gu_name from guid "
						. $row->gu_id . " and home wiki "
						. "$homeWiki\n" );
				} else {
					$fakeSession = $this->getFakeSession(
						$accountLookup, $dbr, $row->gu_name, $row->gu_registration, $verbose );

					if ( !$fakeSession ) {
						if ( $verbose ) {
							$this->output( "Skipping user $row->gu_name creation, no IP/UA info available\n" );
						}
						continue;
					}
					$callback = RequestContext::importScopedSession( $fakeSession );
					// Use per-user transactions to ensure CheckUser's deferred logging
					// (via onTransactionPreCommitOrIdle) runs while the fake session
					// with the correct IP is still active.
					$this->beginTransaction( $dbw, __METHOD__ );
					// Dig down far enough and this uses User::addToDatabase() which relies on
					// MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase()
					// which should be the same as our $dbw arg to begin/commitTransaction(). But I don't like it.
					$this->createLocalAccount( $row->gu_name, $verbose );
					$this->commitTransaction( $dbw, __METHOD__ );
					ScopedCallback::consume( $callback );
					$createdUsers++;
				}
			}
			if ( $startGlobalUID > $maxGlobalUID ) {
				break;
			}
		} while ( true );
		if ( $verbose ) {
			$this->output( "Created users: {$createdUsers}, done.\n" );
		}
	}

	public function execute() {
		$this->performer = User::newSystemUser( CentralAuthHooks::BACKFILL_ACCOUNT_CREATOR, [ 'steal' => true ] );
		if ( !$this->performer ) {
			$this->fatalError(
				"ERROR - unable to get/create system user " . CentralAuthHooks::BACKFILL_ACCOUNT_CREATOR
			);
		}

		$cadb = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		$services = $this->getServiceContainer();
		$userFactory = $services->getUserFactory();

		$verbose = $this->hasOption( 'verbose' );

		$date = new ConvertibleTimestamp( strtotime( $this->getOption( 'startdate' ) ) );
		$startdateTS = $date->getTimestamp( TS_MW );

		$maxGlobalUID = $this->getMaxUID( $cadb );
		$startGlobalUID = $this->getStartUID( $cadb, $startdateTS );
		if ( !$maxGlobalUID || !$startGlobalUID ) {
			$this->output( "No accounts eligible for autocreation\n" );
			return;
		}

		if ( $verbose ) {
			$this->output( "Starting guid: $startGlobalUID\n" );
			$this->output( "Ending guid: $maxGlobalUID\n" );
		}

		$this->checkAndCreateAccounts(
			$cadb,
			$userFactory,
			$services->get( 'AccountCreationDetailsLookup' ),
			$services->getDBLoadBalancerFactory(),
			$this->hasOption( 'dryrun' ),
			$verbose,
			$startGlobalUID,
			$maxGlobalUID,
			WikiMap::getCurrentWikiId()
		);
	}

}

// @codeCoverageIgnoreStart
$maintClass = BackfillLocalAccounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
