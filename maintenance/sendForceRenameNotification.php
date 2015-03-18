<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Script to notify users listed in the users_to_rename table
 * that they will be renamed. Requires the MassMessage extension
 *
 * Setup:
 *   - A directory with localized message files, named in the format of "$langCode.txt".
 *     The string "{{WIKI}}" will be expanded to the current database name
 *   - A directory with localized subject files, named in the format of "$langCode.txt".
 *     A file for "en" must exist as it is the base fallback.
 */
class ForceRenameNotification extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'message', 'Location of directory with messages', true, true );
		$this->addOption( 'subject', 'Location of directory with subjects', true, true );
		$this->addOption( 'sleep', 'How long to sleep for', false, true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		if ( !class_exists( 'MassMessageServerSideJob' ) ) {
			$this->error( 'This script requires the MassMessage extension', 1 );
		}
		$message = $this->getLocalizedText( $this->getOption( 'message' ) );
		$message = str_replace( '{{WIKI}}', wfWikiID(), $message );
		$message .= " ~~~~~\n<!-- SUL finalisation notification -->";
		$dbw = CentralAuthUser::getCentralDB();
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$commonParams = array(
			'subject' => $this->getLocalizedText( $this->getOption( 'subject' ) ),
		);
		while ( true ) {
			$jobs = array();
			$markNotified = array();
			$rows = $updates->findUsers( wfWikiID(), 0, $this->mBatchSize );
			if ( $rows->numRows() === 0 ) {
				break;
			}
			foreach ( $rows as $row ) {
				$title = 'User talk:' . $row->utr_name;
				$jobs[] = new MassMessageServerSideJob(
					Title::newFromText( $title ),
					array(
						'title' => $title,
						'message' => str_replace( '{{subst:PAGENAME}}', $row->utr_name, $message )
					) + $commonParams
				);
				$this->output( "Will notify {$row->utr_name}\n" );
				$markNotified[] = $row;
			}

			$count = count( $jobs );
			$this->output( "Queued job for $count users.\n" );
			JobQueueGroup::singleton()->push( $jobs );
			foreach ( $markNotified as $row ) {
				$updates->markNotified( $row->utr_name, $row->utr_wiki );
			}
			$this->output( "Waiting for slaves..." );
			CentralAuthUser::waitForSlaves(); // users_to_rename
			wfWaitForSlaves(); // And on the local wiki!
			$this->output( " done.\n" );
			$queued = $this->getQueuedCount();
			while ( $queued > 100000 ) {
				$this->output( "Currently $queued jobs, sleeping for 5 seconds...\n" );
				sleep( 5 );
				$queued = $this->getQueuedCount();
			}
		}
	}

	protected function getQueuedCount() {
		$group = JobQueueGroup::singleton();
		$queue = $group->get( 'MassMessageServerSideJob' );
		$pending = $queue->getSize();
		$claimed = $queue->getAcquiredCount();
		$abandoned = $queue->getAbandonedCount();
		$active = max( $claimed - $abandoned, 0 );

		$queued = $active + $pending;
		return $queued;
	}

	protected function getLocalizedText( $dir ) {
		$langCode = $this->getConfig()->get( 'LanguageCode' );
		$fallbacks = Language::getFallbacksFor( $langCode );
		array_unshift( $fallbacks, $langCode );
		foreach ( $fallbacks as $code ) {
			if ( file_exists( "$dir/$code.txt" ) ) {
				return trim( file_get_contents( "$dir/$code.txt" ) );
			}
		}

		$this->error( "Could not find a valid localized file for $langCode.", 1 );
	}
}

$maintClass = 'ForceRenameNotification';
require_once RUN_MAINTENANCE_IF_MAIN;
