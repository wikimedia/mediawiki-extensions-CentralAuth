<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateListOfUsersToRename extends Maintenance {

	private $name = '';

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 1000 );
	}

	/**
	 * Fetches unattached accounts,
	 * and attempts to continue. But it will
	 * return duplicate rows. Just make sure
	 * the batch size is more than how many
	 * total wikis you have.
	 *
	 * @return ResultWrapper
	 */
	private function doQuery() {
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$rows = $dbr->select(
			array( 'localnames', 'localuser' ),
			array( 'ln_name AS name', 'ln_wiki AS wiki' ),
			array(
				'ln_name > ' . $dbr->addQuotes( $this->name ),
				'lu_attached_method IS NULL'
			),
			__METHOD__,
			array( 'LIMIT' => $this->mBatchSize ),
			array( 'localuser' => array( 'LEFT JOIN', 'ln_name=lu_name AND ln_wiki=lu_wiki' ) )
		);

		return $rows;
	}

	public function execute() {
		$dbw = CentralAuthUser::getCentralDB();
		$databaseUpdates = new UsersToRenameDatabaseUpdates( $dbw );
		// CentralAuthUser::chooseHomeWiki is expensive and called
		// multiple times, so lets cache it.
		$cache = new MapCacheLRU( $this->mBatchSize );
		do {
			$rows = $this->doQuery();
			$insertRows = array();
			foreach ( $rows as $row ) {
				$this->name = $row->name;
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
			wfWaitForSlaves();

		} while ( $count !== 0 );
	}
}

$maintClass = 'PopulateListOfUsersToRename';
require_once RUN_MAINTENANCE_IF_MAIN;
