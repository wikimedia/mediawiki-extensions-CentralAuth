<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IDBAccessObject;

class DeleteEmptyAccounts extends Maintenance {

	protected bool $fix;

	protected bool $safe;

	protected bool $migrate;

	protected bool $suppressRC;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Delete all global accounts with no attached local accounts, ' .
			'then attempt to migrate a local account' );

		$this->addOption( 'fix', 'Actually update the database. Otherwise, ' .
			'only prints what would be done.', false, false );
		$this->addOption( 'migrate', 'Migrate a local account; the winner is picked using ' .
			'CentralAuthUser::attemptAutoMigration defaults', false, false );
		$this->addOption( 'safe-migrate', 'Migrate a local account, only if all accounts ' .
			'can be attached', false, false );
		$this->addOption( 'suppressrc', 'Do not send entries to RC feed', false, false );
		$this->setBatchSize( 500 );
	}

	public function execute() {
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgUser
		global $wgUser;

		$original = $wgUser;

		$user = User::newFromName( User::MAINTENANCE_SCRIPT_USER );
		$wgUser = $user;
		RequestContext::getMain()->setUser( $user );

		$dbr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		$this->fix = $this->hasOption( 'fix' );
		$this->safe = $this->hasOption( 'safe-migrate' );
		$this->migrate = $this->hasOption( 'safe-migrate' ) || $this->hasOption( 'migrate' );
		$this->suppressRC = $this->hasOption( 'suppressrc' );

		$end = $dbr->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();

		for ( $cur = 0; $cur <= $end; $cur += $this->mBatchSize ) {
			$this->output( "PROGRESS: $cur / $end\n" );
			$result = $dbr->newSelectQueryBuilder()
				->select( 'gu_name' )
				->from( 'globaluser' )
				->leftJoin( 'localuser', null, 'gu_name=lu_name' )
				->where( [
					'lu_name' => null,
					$dbr->expr( 'gu_id', '>=', $cur ),
					$dbr->expr( 'gu_id', '<', $cur + $this->mBatchSize ),
				] )
				->orderBy( 'gu_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$this->process( $row->gu_name, $user );
			}
			if ( $this->fix ) {
				$this->waitForReplication();
			}
		}

		$this->output( "done.\n" );

		// Restore old $wgUser value
		$wgUser = $original;
	}

	private function process( string $username, User $deleter ): void {
		$central = new CentralAuthUser( $username, IDBAccessObject::READ_LATEST );
		if ( !$central->exists() ) {
			$this->output(
				"ERROR: [$username] Central account does not exist. So how'd we find it?\n"
			);
			return;
		}

		try {
			$unattached = $central->queryUnattached();
		} catch ( Exception ) {
			// This might happen due to localnames inconsistencies (T69350)
			$this->output( "ERROR: [$username] Fetching unattached accounts failed.\n" );
			return;
		}

		foreach ( $unattached as $wiki => $local ) {
			if ( $local['email'] === '' && $local['password'] === '' ) {
				$this->output( "SKIP: [$username] Account on $wiki has no password or email\n" );
				return;
			}
		}

		if ( $this->fix ) {
			$reason = wfMessage( 'centralauth-delete-empty-account' )->inContentLanguage()->text();
			$status = $central->adminDelete( $reason, $deleter );
			if ( !$status->isGood() ) {
				$this->output( "ERROR: [$username] Delete failed:\n" );
				$this->error( $status );
				return;
			}
			$this->output( "DELETE: [$username] Deleted\n" );
		} else {
			$this->output( "DELETE: [$username] Would delete\n" );
		}

		if ( $this->migrate && count( $unattached ) !== 0 ) {
			if ( $this->fix ) {
				$central = CentralAuthUser::newUnattached( $username, true );
				if ( $central->storeAndMigrate( [], !$this->suppressRC, $this->safe ) ) {
					$unattachedAfter = count( $central->queryUnattached() );
					$this->output(
						"MIGRATE: [$username] Success; $unattachedAfter left unattached\n"
					);
				} else {
					$this->output( "MIGRATE: [$username] Fail\n" );
				}
			} else {
				$this->output( "MIGRATE: [$username] Would attempt\n" );
			}
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = DeleteEmptyAccounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
