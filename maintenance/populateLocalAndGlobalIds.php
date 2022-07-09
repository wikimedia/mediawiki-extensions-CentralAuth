<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;

class PopulateLocalAndGlobalIds extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription(
			"Populate the localuser.lu_local_id and localuser.lu_global_id fields"
		);
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbr = $databaseManager->getCentralDB( DB_REPLICA );
		$dbw = $databaseManager->getCentralDB( DB_PRIMARY );
		$lastGlobalId = -1;

		// Skip people in global rename queue
		$wiki = WikiMap::getCurrentWikiId();
		$globalRenames = $dbr->selectFieldValues(
			'renameuser_status',
			'ru_oldname',
			[],
			__METHOD__
		);

		$ldbr = $databaseManager->getLocalDB( DB_REPLICA, $wiki );

		$this->output( "Populating fields for wiki $wiki... \n" );
		do {
			$rows = $dbr->select(
				[ 'localuser', 'globaluser' ],
				[ 'lu_name', 'gu_id' ],
				[
					// Start from where we left off in last batch
					'gu_id >= ' . $lastGlobalId,
					'lu_wiki' => $wiki,
					// Only pick records not already populated
					'lu_local_id' => null,
					'gu_name = lu_name'
				],
				__METHOD__,
				[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'gu_id ASC' ]
			);
			$numRows = $rows->numRows();

			$globalUidToLocalName = [];
			foreach ( $rows as $row ) {
				if ( in_array( $row->lu_name, $globalRenames ) ) {
					$this->output(
						"User " . $row->lu_name . " not migrated (pending rename)\n"
					);
					continue;
				}
				$globalUidToLocalName[$row->gu_id] = $row->lu_name;
			}
			if ( !$globalUidToLocalName ) {
				continue;
			}

			$localNameToUid = [];
			$localIds = $ldbr->select(
				'user',
				[ 'user_id', 'user_name' ],
				[ 'user_name' => array_values( $globalUidToLocalName ) ],
				__METHOD__
			);
			foreach ( $localIds as $lid ) {
				$localNameToUid[$lid->user_name] = $lid->user_id;
			}
			$updated = 0;
			foreach ( $globalUidToLocalName as $gid => $uname ) {
				// Save progress so we know where to start our next batch
				$lastGlobalId = $gid;
				$result = $dbw->update(
					'localuser',
					[
						'lu_local_id' => $localNameToUid[$uname],
						'lu_global_id' => $gid
					],
					[ 'lu_name' => $uname, 'lu_wiki' => $wiki ],
					__METHOD__
				);
				if ( !$result ) {
					$this->output(
						"Update failed for global user $lastGlobalId for wiki $wiki \n"
					);
				} else {
					// Count number of records actually updated
					$updated++;
				}
			}
			$this->output(
				"Updated $updated records. Last user: $lastGlobalId; Wiki: $wiki \n"
			);
			$databaseManager->waitForReplication();
		} while ( $numRows >= $this->mBatchSize );
		$this->output( "Completed $wiki \n" );
	}

}

$maintClass = PopulateLocalAndGlobalIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
