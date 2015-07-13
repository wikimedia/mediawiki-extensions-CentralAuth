<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\UsersToRename\UsersToRenameDatabaseUpdates;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

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

	private LoggerInterface $logger;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Forcibly renames and migrates unattached accounts to global ones' );
		$this->addOption( 'reason', 'Reason to use for log summaries', true, true );
		$this->setBatchSize( 25 );
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	private function log( string $msg ): void {
		$this->logger->debug( "ForceRenameUsers: $msg" );
		$this->output( $msg . "\n" );
	}

	public function execute() {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		while ( true ) {
			$this->output( "Querying for new users to rename...\n" );
			$rowsToRename = $this->findUsers( WikiMap::getCurrentWikiId(), $dbw );
			if ( !$rowsToRename ) {
				$this->output( "Breaking loop..." );
				break;
			}

			foreach ( $rowsToRename as $row ) {
				$this->rename( $row, $dbw );
			}
			$this->waitForReplication();
			$count = $this->getCurrentRenameCount( $dbw );
			while ( $count > 30 ) {
				$this->output( "There are currently $count renames queued, pausing...\n" );
				sleep( 5 );
				$count = $this->getCurrentRenameCount( $dbw );
			}
		}
		$this->output( "broken!\n" );
	}

	protected function getCurrentRenameCount( IDatabase $dbw ): int {
		$row = $dbw->newSelectQueryBuilder()
			->select( 'COUNT(*) as count' )
			->from( 'renameuser_status' )
			->caller( __METHOD__ )
			->fetchRow();
		return (int)$row->count;
	}

	protected function rename( stdClass $row, IDatabase $dbw ): void {
		$wiki = $row->utr_wiki;
		$name = $row->utr_name;
		$services = $this->getServiceContainer();
		$userNameUtils = $services->getUserNameUtils();
		$newNamePrefix = $userNameUtils->getCanonical(
			// Some database names have _'s in them, replace with dashes -
			$name . '~' . str_replace( '_', '-', $wiki ),
			UserNameUtils::RIGOR_USABLE
		);
		if ( !$newNamePrefix ) {
			$this->log( "ERROR: New name '$name~$wiki' is not valid" );
			return;
		}
		$this->log( "Beginning rename of $newNamePrefix" );
		$newCAUser = new CentralAuthUser( $newNamePrefix, IDBAccessObject::READ_LATEST );
		$count = 0;
		// Edge case: Someone created User:Foo~wiki manually.
		// So just start appending numbers to the end of the name
		// until we get one that isn't used.
		while ( $newCAUser->exists() ) {
			$count++;
			$newCAUser = new CentralAuthUser(
				$newNamePrefix . (string)$count,
				IDBAccessObject::READ_LATEST
			);
		}
		if ( $newNamePrefix !== $newCAUser->getName() ) {
			$this->log( "WARNING: New name is now {$newCAUser->getName()}" );
		}
		$this->log( "Renaming $name to {$newCAUser->getName()}." );

		$success = CentralAuthServices::getGlobalRenameFactory( $services )
			->newGlobalRenameUserStatus( $name )
			->setStatuses( [ [
				'ru_wiki' => $wiki,
				'ru_oldname' => $name,
				'ru_newname' => $newCAUser->getName(),
				'ru_status' => 'queued'
			] ] );

		if ( !$success ) {
			$this->log( "WARNING: Race condition, renameuser_status already set for " .
				"{$newCAUser->getName()}. Skipping." );
			return;
		}

		$this->log( "Set renameuser_status for {$newCAUser->getName()}." );

		$job = new LocalRenameUserJob(
			Title::newFromText( 'Global rename job' ),
			[
				'from' => $name,
				'to' => $newCAUser->getName(),
				'renamer' => User::MAINTENANCE_SCRIPT_USER,
				'movepages' => true,
				'suppressredirects' => true,
				'promotetoglobal' => true,
				'reason' => $this->getOption( 'reason' ),
			]
		);

		$services->getJobQueueGroupFactory()->makeJobQueueGroup( $row->utr_wiki )->push( $job );
		$this->log( "Submitted job for {$newCAUser->getName()}." );
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$updates->markRenamed( $row->utr_name, $row->utr_wiki );
	}

	/**
	 * @return stdClass[]
	 */
	protected function findUsers( string $wiki, IDatabase $dbw ): array {
		$rowsToRename = [];
		$updates = new UsersToRenameDatabaseUpdates( $dbw );
		$rows = $updates->findUsers(
			$wiki, UsersToRenameDatabaseUpdates::NOTIFIED, $this->mBatchSize
		);
		$userNameUtils = $this->getServiceContainer()->getUserNameUtils();

		foreach ( $rows as $row ) {
			$user = User::newFromName( $row->utr_name );
			$caUser = new CentralAuthUser( $row->utr_name, IDBAccessObject::READ_LATEST );

			if ( !$user ) {
				$this->log( "Error: Invalid username: {$row->utr_name}" );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} elseif ( !$user->getId() ) {
				$this->log(
					"'{$row->utr_name}' has been renamed since the last was list generated."
				);
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} elseif ( $caUser->attachedOn( $row->utr_wiki ) ) {
				$this->log( "'{$row->utr_name}' has become attached to a global account since " .
					"the list as last generated." );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} elseif ( !$userNameUtils->isUsable( $row->utr_name ) ) {
				// Reserved for a system account, ignore
				$this->log( "'{$row->utr_name}' is a reserved username, skipping." );
				$updates->remove( $row->utr_name, $row->utr_wiki );
			} else {
				$rowsToRename[] = $row;
			}
		}

		return $rowsToRename;
	}
}

// @codeCoverageIgnoreStart
$maintClass = ForceRenameUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
