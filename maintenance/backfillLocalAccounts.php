<?php
/**
 * Create missing local users for existing global user accounts.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use ExtensionRegistry;
use Maintenance;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Status\Status;
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
 * is available, or falling back to some default values otherwise.
 * Errors will be shown if a missing account cannot be created.
 */
class BackfillLocalAccounts extends Maintenance {
	public const ACCOUNT_CREATOR = "MediaWikiAccountBackfiller";
	private const DEFAULT_USER_AGENT = '';
	private const DEFAULT_IP = "127.0.0.1";

	/** @var string */
	private $startdate;

	/** @var UserFactory */
	private $userFactory;

	/** @var User|null */
	private $performer;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addOption( 'startdate', 'Backfill for global users created later than this date', true, true );
		$this->addOption( 'dryrun', 'Display commands that would be run instead of running them', false, false );
		$this->addOption( 'verbose', 'Display extra progress information while running', false, false );
		$this->setBatchSize( $this->getOption( 'batch-size', 1000 ) );
	}

	/**
	 * get the smallest global uid registered on or after the specified start date
	 *
	 * @param IReadableDatabase $cadb
	 * @param string $startdate
	 *
	 * @return int|false global uid
	 */
	private function getStartUID( $cadb, $startdate ) {
		$startid = $cadb->newSelectQueryBuilder()
			->select( 'gu_id' )
			->from( 'globaluser' )
			->where( $cadb->expr( 'gu_registration', '>=', $startdate ) )
			->orderBy( 'gu_id', SelectQueryBuilder::SORT_ASC )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();
		return $startid;
	}

	/**
	 * get the maximum global uid in the central auth database
	 *
	 * @param IReadableDatabase $cadb
	 *
	 * @return int|null global uid
	 */
	private function getMaxUID( $cadb ) {
		$maxid = $cadb->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();
		return $maxid;
	}

	/**
	 * create a local account on the wiki this script is running on,
	 * for the specific user name
	 *
	 * @param string $username
	 * @param bool $verbose
	 */
	private function createLocalAccount( $username, $verbose ) {
		$status = CentralAuthServices::getForcedLocalCreationService()
			->attemptAutoCreateLocalUserFromName(
				$username, $this->performer,
				"Backfilled by autocreation script" );

		if ( !$status->isGood() ) {
			$this->error( "autoCreateUser failed for $username: " .
				Status::wrap( $status )->getWikiText( false, false, 'en' ) );
			return;
		}

		if ( $verbose ) {
			$this->output( "User '$username' created\n" );
		}
	}

	/**
	 * make sure the user does not already exist locally,
	 * and get the home wiki for the user, if possible
	 *
	 * @param string $gu_name
	 * @param UserFactory $userFactory
	 * @param bool $verbose
	 *
	 * @return string|null home wiki of user, or null on error/missing
	 * @throws RuntimeException
	 */
	public function checkUserAndGetHomeWiki( $gu_name, $userFactory, $verbose ) {
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
	 * return session info containing the ip address and user agent for the specified
	 * user name, if found, or default info as a fallback
	 *
	 * @param AccountCreationDetailsLookup $accountLookup
	 * @param IReadableDatabase $dbr
	 * @param string $gu_name
	 * @param bool $verbose
	 *
	 * @return array{userId: 0, ip: string, headers: array, sessionId: ''}
	 */
	private function getFakeSession( $accountLookup, $dbr, $gu_name, $verbose ) {
		$fakeSession = [
			// can't put a real user id in there, we don't have one on the local wiki yet!
			'userId' => 0,
			// there's no session id so...
			'sessionId' => ''
		];

		$accountInfo = null;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			$accountInfo = $accountLookup->getAccountCreationIPAndUserAgent( $gu_name, $dbr );
		}

		if ( $accountInfo == null ) {
			$fakeSession['ip'] = self::DEFAULT_IP;
			$fakeSession['headers'] = [ 'User-Agent' => self::DEFAULT_USER_AGENT ];
		} else {
			$fakeSession['ip'] = $accountInfo['ip'];
			$fakeSession['headers'] = [ 'User-Agent' => $accountInfo['agent'] ];
		}

		if ( $verbose ) {
			$this->output( "Using ip {$fakeSession['ip']} and agent {$fakeSession['headers']['User-Agent']} \n" );
		}
		return $fakeSession;
	}

	/**
	 * @param IReadableDatabase $cadb
	 * @param int $batchStartUID
	 * @param int $maxGlobalUID
	 * @param string $wikiID
	 *
	 * @return array{0:int,1:IResultWrapper}
	 */
	protected function getGlobalUserBatch( $cadb, $batchStartUID, $maxGlobalUID, $wikiID ) {
		$subQuery = $cadb->newSelectQueryBuilder()
			->select( '1' )
			->from( 'localuser' )
			->where( 'lu_name = gu_name' )
			->andWhere( [ 'lu_wiki' => $wikiID ] );

		$result = null;
		do {
			$result = $cadb->newSelectQueryBuilder()
				->select( [ 'gu_name', 'gu_id' ] )
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
		return [ intval( $batchStartUID ), $result ];
	}

	/**
	 * retrieve global users in batches with uid in the specified
	 * range, and if we can find their home wiki, create a
	 * local user on the wiki where this script runs, with the
	 * user's ip and user agent from account creation on their
	 * home wiki if available, otherwise with default values
	 *
	 * @param IReadableDatabase $cadb
	 * @param UserFactory $userFactory
	 * @param AccountCreationDetailsLookup $accountLookup
	 * @param LBFactory $lbFactory
	 * @param bool $dryrun
	 * @param bool $verbose
	 * @param int $startGlobalUID
	 * @param int $maxGlobalUID
	 * @param string $wikiID
	 *
	 */
	public function checkAndCreateAccounts( $cadb, $userFactory, $accountLookup, $lbFactory,
		$dryrun, $verbose,
		$startGlobalUID, $maxGlobalUID, $wikiID ) {
		$createdUsers = 0;
		$currentUID = $startGlobalUID;

		$dbw = $this->getPrimaryDB();
		do {
			[ $startGlobalUID, $result ] = $this->getGlobalUserBatch( $cadb, $startGlobalUID, $maxGlobalUID, $wikiID );

			$this->beginTransaction( $dbw, __METHOD__ );
			foreach ( $result as $row ) {
				$homeWiki = $this->checkUserAndGetHomeWiki( $row->gu_name, $userFactory, $verbose );
				if ( !$homeWiki ) {
					continue;
				}

				// get the user agent and ip address with which the user account was created on
				// their home wiki, if available, or default values otherwise, and create a local
				// account for that user, with that user agent and ip

				$dbr = $lbFactory->getReplicaDatabase( $homeWiki );

				if ( $dryrun ) {
					$this->output( "Would create user $row->gu_name from guid "
						. strval( $row->gu_id ) . " and home wiki "
						. "$homeWiki\n" );
				} else {
					$fakeSession = $this->getFakeSession( $accountLookup, $dbr, $row->gu_name, $verbose );

					$callback = RequestContext::importScopedSession( $fakeSession );
					// Dig down far enough and this uses User::addToDatabase() which relies on
					// MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase()
					// which should be the same as our $dbw arg to begin/commitTransaction(). But I don't like it.
					$this->createLocalAccount( $row->gu_name, $verbose );
					ScopedCallback::consume( $callback );
					$createdUsers++;
				}
			}
			$this->commitTransaction( $dbw, __METHOD__ );
			if ( $startGlobalUID > $maxGlobalUID ) {
				break;
			}
		} while ( true );
		if ( $verbose ) {
			$this->output( "Created users: {$createdUsers}, done.\n" );
		}
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$this->userFactory = $services->getUserFactory();

		$dryrun = $this->hasOption( 'dryrun' );
		$verbose = $this->hasOption( 'verbose' );

		$date = new ConvertibleTimestamp( strtotime( $this->getOption( 'startdate' ) ) );
		$this->startdate = $date->getTimestamp( TS_MW );

		$this->performer = User::newSystemUser( self::ACCOUNT_CREATOR, [ 'steal' => true ] );
		if ( !$this->performer ) {
			$this->fatalError( "ERROR - unable to get/create system user " . self::ACCOUNT_CREATOR );
		}

		$cadb = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		$maxGlobalUID = $this->getMaxUID( $cadb );
		$startGlobalUID = $this->getStartUID( $cadb, $this->startdate );
		if ( !$maxGlobalUID || !$startGlobalUID ) {
			$this->output( "No accounts eligible for autocreation\n" );
			return;
		}

		if ( $verbose ) {
			$this->output( "Starting guid: $startGlobalUID\n" );
			$this->output( "Ending guid: $maxGlobalUID\n" );
		}

		$lbFactory = $services->getDBLoadBalancerFactory();

		$wikiID = WikiMap::getCurrentWikiId();
		$this->checkAndCreateAccounts(
			$cadb,
			$this->userFactory,
			$services->get( 'AccountCreationDetailsLookup' ),
			$lbFactory,
			$dryrun, $verbose,
			$startGlobalUID, $maxGlobalUID, $wikiID );
	}

}

$maintClass = BackfillLocalAccounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
