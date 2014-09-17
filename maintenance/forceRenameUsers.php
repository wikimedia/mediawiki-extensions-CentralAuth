<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * Starts the process of migrating users who have
 * unattached accounts to their new names
 * with globalized accounts.
 *
 * Requires populateUsersToRename.php to be run first
 */
class ForceRenameUsers extends Maintenance {

	public function __construct() {
		$this->mDescription = 'Forcibly renames and migrates unattached accounts to global ones';
		$this->addOption( 'dbname', 'Database name of the wiki to rename users from', true, true );
	}

	private function log( $msg ) {
		wfDebugLog( 'CentralAuthSULRename', $msg );
		$this->output( $msg . '\n' );
	}

	public function execute() {
		$dbw = CentralAuthUser::getCentralDB();
		$dbr = CentralAuthUser::getCentralSlaveDB();
		while ( true ) {
			$rowsToRename = $this->findUsers( $this->getOption( 'dbname' ), $dbr, $dbw );
			if ( !$rowsToRename ) {
				break;
			}

			foreach ( $rowsToRename as $row ) {
				$this->rename( $row, $dbw );
			}
		}
	}

	protected function rename( $row, DatabaseBase $dbw ) {
		$wiki = $row->utr_wiki;
		$name = $row->utr_name;
		$newNamePrefix = "$name~$wiki";
		$newCAUser = new CentralAuthUser( $newNamePrefix );
		$count = 0;
		// Edge case: Someone created User:Foo~wiki manually.
		// So just start appending numbers to the end of the name
		// until we get one that isn't used.
		while ( $newCAUser->exists() ) {
			$count++;
			$newCAUser = new CentralAuthUser( $newNamePrefix . (string)$count );
		}
		$this->log( "Renaming $name to {$newCAUser->getName()}." );

		$statuses = new GlobalRenameUserStatus( $name );
		$success = $statuses->setStatuses( array( array(
			'ru_wiki' => $wiki,
			'ru_oldname' => $name,
			'ru_newname' => $newCAUser->getName(),
			'ru_status' => 'queued'
		) ) );

		if ( !$success ) {
			$this->log( "WARNING: Race condition, renameuser_status already set for {$newCAUser->getName()}. Skipping." );
			return;
		}

		$this->log( "Set renameuser_status for {$newCAUser->getName()}." );

		$job = new LocalRenameUserJob(
			Title::newFromText( 'Global rename job' ),
			array(
				'from' => $name,
				'to' => $newCAUser->getName(),
				'renamer' => 'Maintenance script',
				'movepages' => true,
				'suppressredirects' => true,
				'promotetoglobal' => true,
			)
		);

		JobQueueGroup::singleton( $row->utr_wiki )->push( $job );
		$this->log( "Submitted job for {$newCAUser->getName()}." );
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$updates->markRenamed( $row->utr_name, $row->utr_wiki );
	}

	/**
	 * @param string $wiki
	 * @param DatabaseBase $dbr
	 * @param DatabaseBase $dbw
	 * @return stdClass[]
	 */
	protected function findUsers( $wiki, DatabaseBase $dbr, DatabaseBase $dbw ) {
		$rows = $dbr->select(
			'users_to_rename',
			array( 'utr_name', 'utr_wiki' ),
			array( 'utr_status' => UsersToRenameDatabaseUpdates::NOTIFIED, 'utr_wiki' => $wiki ),
			__METHOD__,
			array( 'LIMIT' => 50 )
		);

		$rowsToRename = array();
		$updates = new UsersToRenameDatabaseUpdates( $dbw );

		foreach ( $rows as $row ) {
			$caUser = new CentralAuthUser( $row->utr_name );
			if ( $caUser->attachedOn( $row->utr_wiki ) ) {
				$this->output( "'{row->utr_name}' has become attached to a global account since the list as last generated." );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} else {
				$rowsToRename[] = $row;
			}
		}

		CentralAuthUser::waitForSlaves(); // Since we might have deleted things...

		return $rowsToRename;
	}
}

$maintClass = 'ForceRenameUsers';
require_once RUN_MAINTENANCE_IF_MAIN;
