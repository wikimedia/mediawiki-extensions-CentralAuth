<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class DeleteEmptyAccounts extends Maintenance {

	/** @var bool */
	protected $fix;

	/** @var bool */
	protected $safe;

	/** @var bool */
	protected $migrate;

	/** @var bool */
	protected $suppressRC;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Delete all global accounts with no attached local accounts, ' .
			'then attempt to migrate a local account' );
		$this->fix = false;
		$this->safe = false;
		$this->migrate = false;
		$this->suppressRC = false;

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

		$user = User::newFromName( 'Maintenance script' );
		$wgUser = $user;
		RequestContext::getMain()->setUser( $user );

		$dbr = CentralAuthUtils::getCentralReplicaDB();

		if ( $this->getOption( 'fix', false ) !== false ) {
			$this->fix = true;
		}
		if ( $this->getOption( 'safe-migrate', false ) !== false ) {
			$this->safe = true;
			$this->migrate = true;
		}
		if ( $this->getOption( 'migrate', false ) !== false ) {
			$this->migrate = true;
		}
		if ( $this->getOption( 'suppressrc', false ) !== false ) {
			$this->suppressRC = true;
		}

		$end = $dbr->selectField( 'globaluser', 'MAX(gu_id)', [], __METHOD__ );
		for ( $cur = 0; $cur <= $end; $cur += $this->mBatchSize ) {
			$this->output( "PROGRESS: $cur / $end\n" );
			$result = $dbr->select(
				[ 'globaluser', 'localuser' ],
				[ 'gu_name' ],
				[
					'lu_name' => null,
					"gu_id >= $cur",
					'gu_id < ' . ( $cur + $this->mBatchSize ),
				],
				__METHOD__,
				[
					'ORDER BY' => 'gu_id',
				],
				[ 'localuser' => [ 'LEFT JOIN', 'gu_name=lu_name' ] ]
			);

			foreach ( $result as $row ) {
				$this->process( $row->gu_name, $user );
			}
			if ( $this->fix ) {
				CentralAuthUtils::waitForReplicas();
			}
		}

		$this->output( "done.\n" );
	}

	private function process( $username, User $deleter ) {
		$central = new CentralAuthUser( $username, CentralAuthUser::READ_LATEST );
		if ( !$central->exists() ) {
			$this->output(
				"ERROR: [$username] Central account does not exist. So how'd we find it?\n"
			);
			return;
		}

		try {
			$unattached = $central->queryUnattached();
		} catch ( Exception $e ) {
			// This might happen due to localnames inconsistencies (bug 67350)
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
				$msg = $status->getErrors()[0]['message'];
				if ( $msg instanceof Message ) {
					$msg = $msg->getKey();
				}
				$this->output( "ERROR: [$username] Delete failed ($msg)\n" );
				return;
			}
			$this->output( "DELETE: [$username] Deleted\n" );
		} else {
			$this->output( "DELETE: [$username] Would delete\n" );
		}

		if ( count( $unattached ) !== 0 && $this->migrate ) {
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

$maintClass = DeleteEmptyAccounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
