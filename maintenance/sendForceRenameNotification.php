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
 *   - A file with your message in it, probably using a giant #switch to be localized.
 *     The string "{{WIKI}}" will be replaced by the wiki's database name.
 *   - A directory with localized subject files, named in the format of "$langCode.txt".
 *     A file for "en" must exist as it is the base fallback.
 */
class ForceRenameNotification extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'message', 'Location of message', true, true );
		$this->addOption( 'subject', 'Location of directory with subjects', true, true );
		$this->addOption( 'sleep', 'How long to sleep for', false, true );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		if ( !class_exists( 'MassMessageServerSideJob' ) ) {
			$this->error( 'This script requires the MassMessage extension', 1 );
		}
		$messageLocation = $this->getOption( 'message' );
		$message = file_get_contents( $messageLocation );
		$sleep = (int)$this->getOption( 'sleep', 1 );
		if ( !$message ) {
			$this->error( "Could not read $messageLocation", 1 );
		}
		$message = str_replace( '{{WIKI}}', wfWikiID(), $message );
		$dbw = CentralAuthUser::getCentralDB();
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$commonParams = array(
			'class' => 'MassMessageServerSideJob',
			'data' => array(
				'subject' => $this->getSubject(),
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

	protected function getSubject() {
		$langCode = $this->getConfig()->get( 'LanguageCode' );
		$fallbacks = Language::getFallbacksFor( $langCode );
		array_unshift( $fallbacks, $langCode );
		$dir = $this->getOption( 'subject' );
		foreach ( $fallbacks as $code ) {
			if ( file_exists( "$dir/$code.txt" ) ) {
				return trim( file_get_contents( "$dir/$code.txt" ) );
			}
		}

		$this->error( "Could not find a valid subject for $langCode.", 1 );
	}
}

$maintClass = 'ForceRenameNotification';
require_once RUN_MAINTENANCE_IF_MAIN;
