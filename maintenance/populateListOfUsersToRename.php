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
		$dbw = CentralAuthUser::getCentralDB();
		$databaseUpdates = new UsersToRenameDatabaseUpdates( $dbw );
		// CentralAuthUser::chooseHomeWiki is expensive and called
		// multiple times, so lets cache it.
		$cache = new HashBagOStuff( array( 'maxKeys' => $this->mBatchSize ) );
		do {
			$rows = $this->doQuery();
			$insertRows = array();
			foreach ( $rows as $row ) {
				$this->lName = $row->name;
				$this->lWiki = $row->wiki;
				$attachableWikis = $cache->get( $row->name );
				if ( !$attachableWikis ) {
					$ca = new CentralAuthUser( $row->name, CentralAuthUser::READ_LATEST );
					$attachableWikis = array();
					$unattached = $ca->queryUnattached();
					if ( $ca->exists() ) {
						$home = $ca->getHomeWiki();
						$attachableWikis[] = $home;
						foreach ( $unattached as $wiki => $info ) {
							if ( $ca->getEmailAuthenticationTimestamp() &&
								$info['email'] === $ca->getEmail() && !is_null( $info['emailAuthenticated'] )
							) {
								$attachableWikis[] = $wiki;
							}
						}
					} else {
						$home = $ca->chooseHomeWiki( $unattached );
						$attachableWikis[] = $home;
						if ( $unattached[$home]['email'] && isset( $unattached[$home]['emailAuthenticated'] ) ) {
							foreach ( $unattached as $wiki => $info ) {
								if ( $wiki !== $home && $unattached[$home]['email'] === $info['email'] && isset( $info['emailAuthenticated'] ) ) {
									$attachableWikis[] = $wiki;
								}
							}
						}
					}
					$cache->set( $row->name, $attachableWikis );
				}

				if ( !in_array( $row->wiki, $attachableWikis )  ) {
					// Unattached account which is not attachable,
					// so they're getting renamed :(
					$this->output( "{$row->name}@{$row->wiki} is going to be renamed.\n" );
					$insertRows[] = (array)$row;
				}
			}
			$databaseUpdates->batchInsert( $insertRows );
			$count = $dbw->affectedRows();
			$this->output( "Inserted $count users who we will rename\n" );

			$this->output( "Waiting for slaves...\n" );
			CentralAuthUser::waitForSlaves();

		} while ( $count !== 0 );
	}
}

$maintClass = 'PopulateListOfUsersToRename';
require_once RUN_MAINTENANCE_IF_MAIN;
