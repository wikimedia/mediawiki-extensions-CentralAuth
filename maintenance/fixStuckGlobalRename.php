<?php

use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\SelectQueryBuilder;

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
		$this->requireExtension( 'CentralAuth' );
		$this->addArg( 'oldname', 'Old name' );
		$this->addArg( 'newname', 'New name' );
		$this->addOption( 'logwiki', 'Wiki where the log entry exists', true, true );
		$this->addOption( 'ignorestatus', 'Ignore rename status. Don\'t do this when the rename '
			. 'jobs might still be running.' );
		$this->addOption( 'create-system-user', 'Create the system user "Global rename script" '
			. 'if it is missing, then exit without doing anything else.' );
		$this->addDescription( 'Unstuck global rename on a single wiki' );
	}

	/**
	 * @inheritDoc
	 */
	public function validateParamsAndArgs() {
		if ( $this->parameters->getOption( 'create-system-user' ) ) {
			// Skip validation of other parameters
			return;
		}
		parent::validateParamsAndArgs();
	}

	public function execute() {
		global $wgLocalDatabases;

		if ( $this->getOption( 'create-system-user' ) ) {
			// This option is used to fix misattributed log entries caused by T344632.
			User::newSystemUser( 'Global rename script', [ 'steal' => true ] );
			return;
		}

		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$oldName = $userNameUtils->getCanonical( $this->getArg( 0 ) );
		$newName = $userNameUtils->getCanonical( $this->getArg( 1 ) );
		if ( $oldName === false || $newName === false ) {
			$this->fatalError( 'Invalid name' );
		}

		$logTitle = Title::newFromText( 'Special:CentralAuth' )->getSubpage( $newName );
		$ca = new CentralAuthUser( $newName );
		if ( !$ca->renameInProgressOn( WikiMap::getCurrentWikiId() ) ) {
			$this->fatalError( "{$ca->getName()} does not have a rename in progress on this wiki." );
		}

		$dbr = wfGetDB( DB_REPLICA, [], $this->getOption( 'logwiki' ) );
		$queryData = DatabaseLogEntry::getSelectQueryData();
		$row = $dbr->newSelectQueryBuilder()
			->tables( $queryData['tables'] )
			->select( $queryData['fields'] )
			->where( $queryData['conds'] )
			->andWhere( [
				'log_type' => 'gblrename',
				'log_action' => 'rename',
				'log_namespace' => NS_SPECIAL,
				'log_title' => $logTitle->getDBkey(),
			] )
			->options( $queryData['options'] )
			->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_DESC )
			->joinConds( $queryData['join_conds'] )
			->caller( __METHOD__ )
			->fetchRow();

		// try to guess the options if the log record does not contain them
		$movepages = true;
		$suppressredirects = false;
		if ( $row ) {
			$logEntry = DatabaseLogEntry::newFromRow( $row );
			$renamer = $logEntry->getPerformerIdentity()->getName();
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
			'ignorestatus' => $this->getOption( 'ignorestatus', false ),
			// No way to recover localuser attachment details, faking it.
			// $wgLocalDatabases will contain wikis where the user does not have an account.
			// That's fine, LocalRenameUserJob will only use the one that matches WikiMap::getCurrentWikiId().
			// FIXME the real attachment info should be saved & restored instead, see T215107
			'reattach' => array_fill_keys( $wgLocalDatabases, [
				'attachedMethod' => 'admin',
				'attachedTimestamp' => wfTimestamp( TS_MW ),
			] ),
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
		$status = $job->run();
		$job->teardown( $status );
		$this->output( $status ? "Done!\n" : "Failed!\n" );
	}
}

$maintClass = FixStuckGlobalRename::class;
require_once RUN_MAINTENANCE_IF_MAIN;
