<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to notify users listed in the users_to_rename table
 * that they will be renamed. Requires the MassMessage extension
 */
class ForceRenameNotification extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'message', 'Location of message', true, true );
		$this->addOption( 'sleep', 'How long to sleep for', false, true );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		if ( !class_exists( 'MassMessageServerSideJob' ) ) {
			$this->error( 'This script requires the MassMessage extension', 1 );
		}
		$message = file_get_contents( $this->getOption( 'message' ) );
		$sleep = (int)$this->getOption( 'sleep', 1 );
		if ( !$message ) {
			$this->error( "Could not read $message", 1 );
		}
		$message = str_replace( '{{WIKI}}', wfWikiID(), $message );
		$dbw = CentralAuthUser::getCentralDB();
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$commonParams = array(
			'class' => 'MassMessageServerSideJob',
			'data' => array(
				'subject' => '???', // @fixme
				'message' => $message,
			),
		);
		while ( true ) {
			$pages = array();
			$markNotified = array();
			$rows = $updates->findUsers( wfWikiID(), 0, $this->mBatchSize );
			if ( $rows->numRows() === 0 ) {
				break;
			}
			foreach ( $rows as $row ) {
				$pages[] = array(
					'title' => 'User talk:' . $row->utr_name,
					'wiki' => wfWikiID(),
				);
				$this->output( "Will notify {$row->utr_name}\n" );
				$markNotified[] = $row;
			}

			$job = new MassMessageSubmitJob(
				Title::newFromText( __CLASS__ ),
				$commonParams + array( 'pages' => $pages )
			);
			$count = count( $pages );
			$this->output( "Queued job for $count users.\n" );
			JobQueueGroup::singleton()->push( $job );
			foreach ( $markNotified as $row ) {
				$updates->markNotified( $row->utr_name, $row->utr_wiki );
			}
			$this->output( "Sleeping for $sleep seconds...\n" );
			sleep( $sleep );
		}
	}
}

$maintClass = 'PopulateGlobalRenameLogSearch';
require_once RUN_MAINTENANCE_IF_MAIN;
