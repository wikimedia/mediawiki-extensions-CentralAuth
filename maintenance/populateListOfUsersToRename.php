<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

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
	private $lName = '';
	private $lWiki = '';

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 1000 );
	}

	/**
	 * Fetches unattached accounts and attempts to continue.
	 *
	 * @return ResultWrapper
	 */
	private function doQuery() {
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$rows = $dbr->select(
			array( 'localnames', 'localuser' ),
			array( 'ln_name AS name', 'ln_wiki AS wiki' ),
			array(
				$dbr->makeList(
					array(
						'ln_name > ' . $dbr->addQuotes( $this->lName ),
						'ln_name = ' . $dbr->addQuotes( $this->lName ) . ' AND ln_wiki > ' .
							$dbr->addQuotes( $this->lWiki )
					),
					LIST_OR
				),
				'lu_attached_method IS NULL'
			),
			__METHOD__,
			array(
				'LIMIT' => $this->mBatchSize,
				'ORDER BY' => array( 'ln_name', 'ln_wiki' ),
			),
			array( 'localuser' => array( 'LEFT JOIN', 'ln_name=lu_name AND ln_wiki=lu_wiki' ) )
		);

		return $rows;
	}

	public function execute() {
		global $wgCentralAuthDatabase;

		$dbw = CentralAuthUser::getCentralDB();
		$databaseUpdates = new UsersToRenameDatabaseUpdates( $dbw );
		// CentralAuthUser::chooseHomeWiki is expensive and called
		// multiple times, so lets cache it.
		$cache = new MapCacheLRU( $this->mBatchSize );
		do {
			$rows = $this->doQuery();
			$insertRows = array();
			foreach ( $rows as $row ) {
				$this->lName = $row->name;
				$this->lWiki = $row->wiki;
				if ( $cache->has( $row->name ) ) {
					$home = $cache->get( $row->name );
				} else {
					$ca = new CentralAuthUser( $row->name );
					if ( $ca->exists() ) {
						$home = $ca->getHomeWiki();
					} else {
						$home = $ca->chooseHomeWiki( $ca->queryUnattached() );
					}
					$cache->set( $row->name, $home );
				}

				if ( $home !== $row->wiki ) {
					// Unattached account which is not on the home wiki,
					// so they're getting renamed :(
					$insertRows[] = (array)$row;
				}
			}
			$databaseUpdates->batchInsert( $insertRows );
			$count = $dbw->affectedRows();
			$this->output( "Inserted $count users who we will rename\n" );

			$this->output( "Waiting for slaves...\n" );
			wfWaitForSlaves( false, $wgCentralAuthDatabase );

		} while ( $count !== 0 );
	}
}

$maintClass = 'PopulateListOfUsersToRename';
require_once RUN_MAINTENANCE_IF_MAIN;
