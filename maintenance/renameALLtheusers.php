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
class RenameALLTheUsers extends Maintenance {

	private function log( $msg ) {
		wfDebugLog( 'CentralAuthSULRename', $msg );
		$this->output( $msg . '\n' );
	}

	public function execute() {
		$dbw = CentralAuthUser::getCentralDB();
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$rowsToRename = $this->findUsers( $dbr, $dbw );

		foreach ( $rowsToRename as $row ) {
			$this->rename( $row, $dbw );
		}
	}

	protected function rename( $row, DatabaseBase $dbw ) {
		$wiki = $row->utr_wiki;
		$name = $row->utr_name;
		$newNamePrefix = "{$row->utr_name}~{$row->utr_wiki}";
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
		$statuses->setStatuses( array( array(
			'ru_wiki' => $wiki,
			'ru_oldname' => $name,
			'ru_newname' => $newCAUser->getName(),
			'ru_status' => 'queued'
		) ) );
		$this->log( "Set statuses for {$newCAUser->getName()}." );

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
	 * @param DatabaseBase $dbr
	 * @param DatabaseBase $dbw
	 * @return stdClass[]
	 */
	protected function findUsers( DatabaseBase $dbr, DatabaseBase $dbw ) {
		$rows = $dbr->select(
			'users_to_rename',
			array( 'utr_name', 'utr_wiki' ),
			array( 'utr_status' => UsersToRenameDatabaseUpdates::RENAMED ),
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

		CentralAuthUser::waitForSlaves(); // Since we deleted things...

		return $rowsToRename;
	}
}
