<?php

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\UsersToRename\UsersToRenameDatabaseUpdates;
use MediaWiki\MassMessage\Job\MassMessageServerSideJob;
use MediaWiki\MediaWikiServices;

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
class SendForceRenameNotification extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->requireExtension( 'MassMessage' );

		$this->addOption( 'message', 'Location of directory with messages', true, true );
		$this->addOption( 'subject', 'Location of directory with subjects', true, true );
		$this->addOption( 'sleep', 'How long to sleep for', false, true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$message = $this->getLocalizedText( $this->getOption( 'message' ) );
		$message = str_replace( '{{WIKI}}', WikiMap::getCurrentWikiId(), $message );
		$message .= " ~~~~~\n<!-- SUL finalisation notification -->";
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralDB( DB_PRIMARY );
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$commonParams = [
			'subject' => $this->getLocalizedText( $this->getOption( 'subject' ) ),
		];

		$services = MediaWikiServices::getInstance();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$namespaceInfo = $services->getNamespaceInfo();
		$jobQueueGroup = $services->getJobQueueGroup();
		$linkBatchFactory = $services->getLinkBatchFactory();

		while ( true ) {
			$jobs = [];
			$markNotified = [];
			$rows = $updates->findUsers( WikiMap::getCurrentWikiId(), 0, $this->mBatchSize );
			if ( $rows->numRows() === 0 ) {
				break;
			}
			$lb = $linkBatchFactory->newLinkBatch();
			foreach ( $rows as $row ) {
				$title = Title::makeTitleSafe( NS_USER_TALK, $row->utr_name );
				if ( !$title ) {
					$this->output( "ERROR: Invalid username for {$row->utr_name}\n" );
					continue;
				}
				$lb->addObj( $title );
			}
			$lb->execute();
			foreach ( $rows as $row ) {
				$title = $namespaceInfo->getCanonicalName( NS_USER_TALK ) . ':' . $row->utr_name;
				$titleObj = Title::newFromText( $title );
				if ( $titleObj->isRedirect() ) {
					// @fixme find a way to notify users with a redirected user-talk
					$this->output( "Skipping {$title} because it is a redirect\n" );
					$updates->markRedirectSkipped( $row->utr_name, $row->utr_wiki );
					continue;
				}
				$jobs[] = new MassMessageServerSideJob(
					Title::newFromText( $title ),
					[
						'title' => $title,
						'message' => str_replace( '{{subst:PAGENAME}}', $row->utr_name, $message )
					] + $commonParams
				);
				$this->output( "Will notify {$row->utr_name}\n" );
				$markNotified[] = $row;
			}

			$count = count( $jobs );
			$this->output( "Queued job for $count users.\n" );
			$jobQueueGroup->push( $jobs );
			foreach ( $markNotified as $row ) {
				$updates->markNotified( $row->utr_name, $row->utr_wiki );
			}
			$this->output( "Waiting for replicas..." );
			// users_to_rename
			$databaseManager->waitForReplication();
			// And on the local wiki!
			$lbFactory->waitForReplication();

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
		$group = MediaWikiServices::getInstance()->getJobQueueGroup();
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

		$this->fatalError( "Could not find a valid localized file for $langCode." );
	}
}

$maintClass = SendForceRenameNotification::class;
require_once RUN_MAINTENANCE_IF_MAIN;
