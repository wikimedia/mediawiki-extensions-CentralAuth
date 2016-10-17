<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PopulateLocalAndGlobalIds extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Populate the localuser.lu_local_id and localuser.lu_global_id fields" );
		$this->setBatchSize( 100 );
		// lName and lWiki help tell us where we are in each db select
		$lName = '';
		$lWiki = '';
		$caUser = null;
	}

	/**
	 * Get local ID for given user on given wiki
	 *
	 * @param string $wiki Wiki ID
	 * @param string $name Username
	 * @return Local user ID
	 */
	public function getLocalId( $wiki, $name ) {
		$dbr = wfGetDB( DB_SLAVE, [], $wiki );
		$id = $dbr->selectField( 'user', 'user_id', [ 'user_name' => $name ] );
		return $id;
	}

	public function execute() {
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$dbw = CentralAuthUtils::getCentralDB();
		do {
			// Fetch rows from localuser table in batches of size mBatchSize
			$rows = $dbr->select(
						'localuser',
						[ 'lu_wiki AS wiki', 'lu_name AS name' ],
						$dbr->makeList(
							// Make sure we don't re-populate populated fields
							'lu_local_id is NULL',
							[
								$dbr->makeList(
									[
										'lu_name > ' . $dbr->addQuotes( $this->lName ),
										'lu_name = ' . $dbr->addQuotes( $this->lName ) . ' AND lu_wiki > ' .
											$dbr->addQuotes( $this->lWiki )
									],
									LIST_OR
								),
							],
							LIST_AND
						),
						__METHOD__,
						[
							'LIMIT' => $this->mBatchSize,
							'ORDER BY' => array( 'lu_name', 'lu_wiki' ),
						]
					);
			// Process fetched rows
			foreach ( $rows as $row ) {
				if ( $row->name !== $this->lName ) {
					$this->caUser = new CentralAuthUser( $this->lName );
					$this->lName = $row->name;
				}
				$this->lWiki = $row->wiki;
				$localId = $this->caUser->getLocalId( $this->lWiki );
				$globalId = $this->caUser->getId();
				// Update localuser table with local and global ID values
				$result = $dbw->update(
					'localuser',
					[ 'lu_local_id' => $localId, 'lu_global_id' => $globalId ],
					[ 'lu_name' => $this->lName, 'lu_wiki' => $this->lWiki ]
				);
				if ( !$result ) {
					$this->output( "Update failed for user $lName for wiki $lWiki" );
				}
			}
			$numRows = $rows->numRows();
			$this->output( "Updated $numRows records.\n" );
			if (  $numRows < $this->mBatchSize ) {
				break;
			}
		} while ( true );
		$this->output( "Done.\n" );
	}

}

