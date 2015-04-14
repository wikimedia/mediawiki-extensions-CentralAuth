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
 * This script should be run on each wiki individually.
 *
 * Requires populateUsersToRename.php to be run first
 */
class ForceRenameUsers extends Maintenance {

	public function __construct() {
		$this->mDescription = 'Forcibly renames and migrates unattached accounts to global ones';
		$this->setBatchSize( 10 );
	}

	private function log( $msg ) {
		wfDebugLog( 'CentralAuthSULRename', $msg );
		$this->output( $msg . "\n" );
	}

	public function execute() {
		$dbw = CentralAuthUser::getCentralDB();
		while ( true ) {
			$rowsToRename = $this->findUsers( wfWikiID(), $dbw );
			if ( !$rowsToRename ) {
				break;
			}

			foreach ( $rowsToRename as $row ) {
				$this->rename( $row, $dbw );
			}
			CentralAuthUser::waitForSlaves();
			$count = $this->getCurrentRenameCount( $dbw );
			while ( $count > 50 ) {
				$this->output( "There are currently $count renames queued, pausing..." );
				sleep( 5 );
				$count = $this->getCurrentRenameCount( $dbw );
			}
		}
	}

	protected function getCurrentRenameCount( DatabaseBase $dbw ) {
		$row = $dbw->selectRow(
			array( 'renameuser_status'),
			array( 'COUNT(*) as count' ),
			array(),
			__METHOD__
		);
		return (int)$row->count;
	}

	protected function rename( $row, DatabaseBase $dbw ) {
		$wiki = $row->utr_wiki;
		$name = $row->utr_name;
		$newNamePrefix = "$name~$wiki";
		$this->output( "Beginning rename of $newNamePrefix" );
		$newCAUser = new CentralAuthUser( $newNamePrefix );
		$count = 0;
		// Edge case: Someone created User:Foo~wiki manually.
		// So just start appending numbers to the end of the name
		// until we get one that isn't used.
		while ( $newCAUser->exists() ) {
			$count++;
			$newCAUser = new CentralAuthUser( $newNamePrefix . (string)$count );
		}
		if ( $newNamePrefix !== $newCAUser->getName() ) {
			$this->output( "WARNING: New name is now {$newCAUser->getName()}" );
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

		// @todo set a reason for log entries
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
	 * @param DatabaseBase $dbw
	 * @return stdClass[]
	 */
	protected function findUsers( $wiki, DatabaseBase $dbw ) {
		$rowsToRename = array();
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$rows = $updates->findUsers( $wiki, UsersToRenameDatabaseUpdates::NOTIFIED, $this->mBatchSize );

		foreach ( $rows as $row ) {
			$caUser = new CentralAuthUser( $row->utr_name );
			if ( $caUser->attachedOn( $row->utr_wiki ) ) {
				$this->output( "'{row->utr_name}' has become attached to a global account since the list as last generated." );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} elseif ( !User::isUsableName( $row->utr_name ) ) {
				// Reserved for a system account, ignore
				$this->output( "'{row->utr_name}' is a reserved username, skipping." );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} else {
				$rowsToRename[] = $row;
			}
		}

		return $rowsToRename;
	}
}

$maintClass = 'ForceRenameUsers';
require_once RUN_MAINTENANCE_IF_MAIN;
