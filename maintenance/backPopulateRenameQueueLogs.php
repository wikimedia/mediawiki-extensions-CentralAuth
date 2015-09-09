<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to back-populate logs for promote to global renames
 * done through Special:GlobalRenameQueue
 */
class BackPopulateRenameQueueLogs extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	public function execute() {
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$rows = $dbr->select(
				array( 'renameuser_queue' ),
				array( 'rq_id', 'rq_name', 'rq_wiki', 'rq_newname', 'rq_completed_ts', 'rq_performer' ),
				array(
					'rq_wiki IS NOT NULL',
					'rq_status' => 'approved',
					'rq_completed_ts < 20150408153600'
				),
				__METHOD__
		);

		$count = 0;
		foreach ( $rows as $row ) {
			$performer = CentralAuthUser::newFromId( $row->rq_performer );
			$localUser = User::newFromName( $performer->getName() );
			$logEntry = new ManualLogEntry( 'gblrename', 'promote' );
			$logEntry->setPerformer( $localUser );

			$logEntry->setTarget(
				Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $row->rq_newname )
			);

			// Use a generic summary
			$logEntry->setComment( '[[m:Special:GlobalRenameQueue/' . (string)$row->rq_id . '|per request]]' );

			$logEntry->setParameters( array(
				'4::olduser' => $row->rq_name,
				'5::newuser' => $row->rq_newname,
				'6::oldwiki' => $row->rq_wiki,
			) );

			$logEntry->setRelations( array(
				'oldname' => $row->rq_name,
			) );
			$logEntry->setTimestamp( $row->rq_completed_ts );

			$logid = $logEntry->insert();
			$this->output( "Inserted log entry#$logid\n" );
			$count++;
			if ( $count % 10 == 0 ) {
				wfWaitForSlaves();
			}
		}

	}
}

$maintClass = 'BackPopulateRenameQueueLogs';
require_once RUN_MAINTENANCE_IF_MAIN;
