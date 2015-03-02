<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to run global renames that are stuck in the status table with "queued" or "in progress"
 * but failed for whatever reason
 */
class FixStuckGlobalRename extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'oldname', 'Old name' );
		$this->addArg( 'newname', 'New name' );
		$this->addOption( 'logwiki', 'Wiki where the log entry exists', true, true );
		$this->addDescription( 'Unstuck global rename on a single wiki' );
	}

	public function execute() {
		$oldName = User::getCanonicalName( $this->getArg( 0 ) );
		$newName = User::getCanonicalName( $this->getArg( 1 ) );
		if ( $oldName === false || $newName === false ) {
			$this->error( 'Inalid name', 1 );
		}

		$logTitle = Title::newFromText( 'Special:CentralAuth' )->getSubpage( $newName );
		$ca = new CentralAuthUser( $newName );
		if ( !$ca->renameInProgressOn( wfWikiID() ) ) {
			$this->error( "{$ca->getName()} does not have a rename in progress on this wiki.", 1 );
		}

		$dbr = wfGetDB( DB_SLAVE, [], $this->getOption( 'logwiki' ) );
		$queryData = DatabaseLogEntry::getSelectQueryData();
		$row = $dbr->selectRow(
			$queryData['tables'],
			$queryData['fields'],
			array_merge( $queryData['conds'], [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
				'log_namespace' => NS_SPECIAL,
				'log_title' => $logTitle->getDBkey(),
			] ),
			__METHOD__,
			array_merge( $queryData['options'], [ 'ORDER BY' => 'log_timestamp DESC' ] ),
			$queryData['join_conds']
		);

		// try to guess the options if the log record does not contain them
		$movepages = true;
		$suppressredirects = false;
		if ( $row ) {
			$logEntry = DatabaseLogEntry::newFromRow( $row );
			$renamer = $logEntry->getPerformer()->getName();
			$comment = $logEntry->getComment();
			$logParams = $logEntry->getParameters();
			if ( isset( $logParams['movepages'] ) ) {
				$movepages = $logParams['movepages'];
			}
			if ( isset( $logParams['suppressredirects'] ) ) {
				$suppressredirects = $logParams['suppressredirects'];
			}
			$this->output( "Using $renamer as the renamer.\n" );
		} else {
			$this->output( "Could not find log entry, falling back to system account\n" );
			$renamer = 'Global rename script';
			$comment = '';
		}
		$params = [
			'from' => $oldName,
			'to' => $newName,
			'renamer' => $renamer,
			'movepages' => $movepages,
			'suppressredirects' => $suppressredirects,
			'reason' => $comment,
			// no way to recover localuser attachment details, faking it
			'reattach' => [ wfWikiID() => [
				'attachedMethod' => 'admin',
				'attachedTimestamp' => wfTimestamp( TS_MW ),
			] ],
		];
		foreach ( $params as $key => $value ) {
			if ( $key === 'reattach' ) {
				continue;
			}
			$this->output( "$key: $value\n" );
		}
		$title = Title::newFromText( 'Global rename job' );
		$job = new LocalRenameUserJob( $title, $params );
		$this->output( "\nStarting to run job...\n" );
		$job->run();
		$this->output( "Done!\n" );
	}
}

$maintClass = 'FixStuckGlobalRename';
require_once RUN_MAINTENANCE_IF_MAIN;
