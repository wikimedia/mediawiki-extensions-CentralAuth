<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserValidator;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\TempUser\Pattern;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserRigorOptions;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IExpression;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RenameUsersMatchingPattern extends Maintenance {

	private CentralAuthDatabaseManager $dbManager;
	private UserFactory $userFactory;
	private GlobalRenameFactory $globalRenameFactory;
	private GlobalRenameUserValidator $validator;

	/** @var User */
	private $performer;

	/** @var string */
	private $reason;

	/** @var bool */
	private $dryRun;

	/** @var bool */
	private $suppressRedirect;

	/** @var bool */
	private $skipPageMoves;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Rename global users with a name matching a pattern. ' .
			'This can be used to migrate to a temporary user (IP masking) configuration.' );
		$this->addOption( 'from', 'A username pattern where $1 is ' .
			'the wildcard standing in for any number of characters. All users ' .
			'matching this pattern will be renamed.', true, true );
		$this->addOption( 'to', 'A username pattern where $1 is ' .
			'the part of the username matched by $1 in --from. Users will be ' .
			' renamed to this pattern.', true, true );
		$this->addOption( 'performer', 'Performer of the rename action', false, true );
		$this->addOption( 'reason', 'Reason of the rename', false, true );
		$this->addOption( 'suppress-redirect', 'Don\'t create redirects when moving pages' );
		$this->addOption( 'skip-page-moves', 'Don\'t move associated user pages' );
		$this->addOption( 'dry-run', 'Don\'t actually rename the ' .
			'users, just report what it would do.' );
		$this->setBatchSize( 1000 );
	}

	private function initServices() {
		$services = $this->getServiceContainer();
		$this->dbManager = CentralAuthServices::getDatabaseManager();
		$this->userFactory = $services->getUserFactory();
		$this->globalRenameFactory = $services->get( 'CentralAuth.GlobalRenameFactory' );
		$this->validator = $services->get( 'CentralAuth.GlobalRenameUserValidator' );
	}

	public function execute() {
		$this->initServices();

		$fromPattern = new Pattern( 'from', $this->getOption( 'from' ) );
		$toPattern = new Pattern( 'to', $this->getOption( 'to' ) );

		$batchSize = $this->getBatchSize();

		if ( $this->getOption( 'performer' ) === null ) {
			$performer = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		} else {
			$performer = $this->userFactory->newFromName( $this->getOption( 'performer' ) );
		}
		if ( !$performer ) {
			$this->error( "Unable to get performer account" );
			return false;
		}
		$this->performer = $performer;

		$this->reason = $this->getOption( 'reason', '' );

		$this->suppressRedirect = $this->getOption( 'suppress-redirect' );
		$this->skipPageMoves = $this->getOption( 'skip-page-moves' );
		$this->dryRun = $this->getOption( 'dry-run' );

		$dbr = $this->dbManager->getCentralReplicaDB();
		$batchConds = [];
		$numRenamed = 0;

		do {
			$res = $dbr->newSelectQueryBuilder()
				->select( 'gu_name' )
				->from( 'globaluser' )
				->where( $dbr->expr( 'gu_name', IExpression::LIKE, $fromPattern->toLikeValue( $dbr ) ) )
				->andWhere( $batchConds )
				->orderBy( 'gu_name' )
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				$oldName = $row->gu_name;
				$batchConds = [ $dbr->expr( 'gu_name', '>', $oldName ) ];
				$variablePart = $fromPattern->extract( $oldName );
				if ( $variablePart === null ) {
					$this->output( "Username \"fromName\" matched the LIKE " .
						"but does not seem to match the pattern" );
					continue;
				}
				$newName = $toPattern->generate( $variablePart );
				$numRenamed += $this->renameUser( $oldName, $newName ) ? 1 : 0;
				$this->waitForJobs();
				$this->waitForReplication();
			}
		} while ( $res->numRows() === $batchSize );
		$this->output( "Renamed $numRenamed user(s)\n" );
		return true;
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @return bool True if the user was renamed
	 */
	private function renameUser( $oldName, $newName ) {
		$oldUser = $this->userFactory->newFromName( $oldName, UserRigorOptions::RIGOR_NONE );
		$newUser = $this->userFactory->newFromName( $newName, UserRigorOptions::RIGOR_CREATABLE );
		if ( !$oldUser ) {
			$this->output( "Unable to rename \"$oldName\": invalid username\n" );
			return false;
		}
		if ( !$newUser ) {
			$this->output( "Unable to rename \"$oldName\" to \"$newName\": invalid target username\n" );
			return false;
		}

		$status = $this->validator->validate( $oldUser, $newUser );
		if ( !$status->isOK() ) {
			$this->output( "Unable to rename \"$oldName\" to \"$newName\": " );
			$this->error( $status );
			return false;
		}

		$oldCaUser = new CentralAuthUser( $oldName, IDBAccessObject::READ_LATEST );

		$data = [
			'movepages' => !$this->skipPageMoves,
			'suppressredirects' => $this->suppressRedirect,
			'reason' => $this->reason,
			'force' => true,
		];

		$globalRenameUser = $this->globalRenameFactory->newGlobalRenameUser(
			$this->performer,
			$oldCaUser,
			$newName
		);

		if ( $this->dryRun ) {
			$this->output( "Would rename \"$oldName\" to \"$newName\"\n" );
			return true;
		} else {
			$status = $globalRenameUser->rename( $data );
			if ( $status->isGood() ) {
				$this->output( "Successfully queued rename of \"$oldName\" to \"$newName\"\n" );
				return true;
			} else {
				$this->output( "Error renaming \"$oldName\" to \"$newName\": " );
				$this->error( $status );
				return false;
			}
		}
	}

	/**
	 * Wait until fewer than 15 rename jobs are pending
	 */
	private function waitForJobs() {
		while ( true ) {
			$count = $this->dbManager->getCentralPrimaryDB()->newSelectQueryBuilder()
				->from( 'renameuser_status' )
				->limit( 15 )
				->caller( __METHOD__ )
				->fetchRowCount();
			if ( $count < 15 ) {
				break;
			}
			sleep( 5 );
		}
	}
}

$maintClass = RenameUsersMatchingPattern::class;
require_once RUN_MAINTENANCE_IF_MAIN;
