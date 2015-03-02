<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to run global renames that are stuck in the status table with "queued"
 * but failed for whatever reason
 */
class FixStuckGlobalRename extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'oldname', 'Old name' );
		$this->addArg( 'newname', 'New name' );
		$this->addOption( 'logwiki', 'Wiki where the log entry exists', true, true );
	}

	public function execute() {
		$title = Title::newFromText( 'Global rename job' );
		$dbr = wfGetDB( DB_SLAVE, array(), $this->getOption( 'logwiki' ) );
		$logTitle = Title::newFromText( 'Special:CentralAuth' )->getSubpage( $this->getArg( 1 ) );
		$ca = new CentralAuthUser( $this->getArg( 1 ) );
		if ( !$ca->renameInProgressOn( wfWikiID() ) ) {
			$this->error( "{$ca->getName()} does not have a rename in progress on this wiki.", 1 );
		}
		$row = $dbr->selectRow(
			'logging',
			array( 'log_user_text', 'log_comment' ),
			array(
				'log_type' => 'gblrename',
				'log_action' => 'rename',
				'log_namespace' => NS_SPECIAL,
				'log_title' => $logTitle->getDBkey(),
			),
			__METHOD__,
			array( 'ORDER BY log_timestamp DESC')
		);
		if ( $row ) {
			$renamer = $row->log_user_text;
			$comment = $row->log_comment;
			$this->output( "Using $renamer as the renamer.\n" );
		} else {
			$this->output( "Could not find log entry, falling back to system account\n" );
			$renamer = 'Global rename script';
			$comment = '';
		}
		$params = array(
			'from' => $this->getArg( 0 ),
			'to' => $this->getArg( 1 ),
			'renamer' => $renamer,
			// @todo: Figure out what the rename used???
			'movepages' => true,
			'suppressredirects' => false,
			'reason' => $comment,
		);
		foreach ( $params as $key => $value ) {
			$this->output( "$key: $value\n" );
		}
		$job = new LocalRenameUserJob( $title, $params );
		$this->output( "\nStarting to run job...\n" );
		$job->run();
		$this->output( "Done!\n" );
	}
}

$maintClass = 'FixStuckGlobalRename';
require_once RUN_MAINTENANCE_IF_MAIN;
