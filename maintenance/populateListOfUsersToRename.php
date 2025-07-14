<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\UsersToRename\UsersToRenameDatabaseUpdates;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IResultWrapper;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Populates the users_to_rename table.
 *
 * Before running this, you should run migratePass0,
 * checkLocalNames, and checkLocalUser to make sure
 * all of your tables are in sync.
 *
 * The expectation is the table is populated at some determined
 * point in time, at which point you can start sending out
 * notifications to users who are going to lose their accounts.
 */
class PopulateListOfUsersToRename extends Maintenance {

	/** @var string|null */
	private $lName = '';
	/** @var string|null */
	private $lWiki = '';

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 1000 );
	}

	/**
	 * Fetches unattached accounts and attempts to continue.
	 *
	 * @return IResultWrapper
	 */
	private function doQuery() {
		$dbr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'name' => 'ln_name', 'wiki' => 'ln_wiki' ] )
			->from( 'localnames' )
			->leftJoin( 'localuser', null, [ 'ln_name=lu_name', 'ln_wiki=lu_wiki' ] )
			->where( [
				$dbr->buildComparison( '>', [
					'ln_name' => $this->lName,
					'ln_wiki' => $this->lWiki,
				] ),
				'lu_attached_method' => null,
			] )
			->orderBy( [ 'ln_name', 'ln_wiki' ] )
			->limit( $this->mBatchSize )
			->caller( __METHOD__ )
			->fetchResultSet();

		return $rows;
	}

	public function execute() {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$databaseUpdates = new UsersToRenameDatabaseUpdates( $dbw );
		// CentralAuthUser::chooseHomeWiki is expensive and called
		// multiple times, so lets cache it.
		$cache = new HashBagOStuff( [ 'maxKeys' => $this->mBatchSize ] );
		do {
			$rows = $this->doQuery();
			$insertRows = [];
			foreach ( $rows as $row ) {
				$this->lName = $row->name;
				$this->lWiki = $row->wiki;
				$attachableWikis = $cache->get( $row->name );
				if ( !$attachableWikis ) {
					$ca = new CentralAuthUser( $row->name, IDBAccessObject::READ_LATEST );
					$attachableWikis = [];
					$unattached = $ca->queryUnattached();
					if ( $ca->exists() ) {
						$home = $ca->getHomeWiki();
						$attachableWikis[] = $home;
						foreach ( $unattached as $wiki => $info ) {
							if ( $ca->getEmailAuthenticationTimestamp() &&
								$info['email'] === $ca->getEmail() &&
								$info['emailAuthenticated'] !== null
							) {
								$attachableWikis[] = $wiki;
							}
						}
					} else {
						$home = $ca->chooseHomeWiki( $unattached );
						$attachableWikis[] = $home;
						if ( $unattached[$home]['email'] &&
							isset( $unattached[$home]['emailAuthenticated'] )
						) {
							foreach ( $unattached as $wiki => $info ) {
								if ( $wiki !== $home &&
									$unattached[$home]['email'] === $info['email'] &&
									isset( $info['emailAuthenticated'] )
								) {
									$attachableWikis[] = $wiki;
								}
							}
						}
					}
					$cache->set( $row->name, $attachableWikis );
				}

				if ( !in_array( $row->wiki, $attachableWikis ) ) {
					// Unattached account which is not attachable,
					// so they're getting renamed :(
					$this->output( "{$row->name}@{$row->wiki} is going to be renamed.\n" );
					$insertRows[] = (array)$row;
				}
			}
			$databaseUpdates->batchInsert( $insertRows );
			$count = $dbw->affectedRows();
			$this->output( "Inserted $count users who we will rename\n" );

			$this->output( "Waiting for replicas...\n" );
			$this->waitForReplication();

		} while ( $count !== 0 );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateListOfUsersToRename::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
