<?php

/**
 * Rename a global user
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUser {
	/**
	 * @var User
	 */
	private $performingUser;

	/**
	 * @var User
	 */
	private $oldUser;

	/**
	 * @var CentralAuthUser
	 */
	private $oldCAUser;

	/**
	 * @var User
	 */
	private $newUser;

	/**
	 * @var CentralAuthUser
	 */
	private $newCAUser;

	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	/**
	 * @var callable
	 */
	private $jobQueueGroupGenerator;

	/**
	 * @var GlobalRenameUserDatabaseUpdates
	 */
	private $databaseUpdates;

	/**
	 * @var GlobalRenameUserLogger
	 */
	private $logger;

	/**
	 * @var array
	 */
	private $session;

	/**
	 * @param User $performingUser
	 * @param User $oldUser
	 * @param CentralAuthUser $oldCAUser
	 * @param User $newUser Validated (creatable!) new user
	 * @param CentralAuthUser $newCAUser
	 * @param GlobalRenameUserStatus $renameuserStatus
	 * @param callable $jobQueueGroupGenerator Callable for getting a job queue group for a given wiki
	 * @param GlobalRenameUserDatabaseUpdates $databaseUpdates
	 * @param GlobalRenameUserLogger $logger
	 * @param array $session output of RequestContext::exportSession()
	 */
	public function __construct(
		User $performingUser,
		User $oldUser,
		CentralAuthUser $oldCAUser,
		User $newUser,
		CentralAuthUser $newCAUser,
		GlobalRenameUserStatus $renameuserStatus,
		/* callable */ $jobQueueGroupGenerator,
		GlobalRenameUserDatabaseUpdates $databaseUpdates,
		GlobalRenameUserLogger $logger,
		array $session
	) {
		$this->performingUser = $performingUser;
		$this->oldUser = $oldUser;
		$this->oldCAUser = $oldCAUser;
		$this->newUser = $newUser;
		$this->newCAUser = $newCAUser;
		$this->renameuserStatus = $renameuserStatus;
		$this->jobQueueGroupGenerator = $jobQueueGroupGenerator;
		$this->databaseUpdates = $databaseUpdates;
		$this->logger = $logger;
		$this->session = $session;
	}

	/**
	 * Rename a global user (this assumes that the data has been verified before
	 * and that $newUser is being a creatable user)!
	 *
	 * @param array $options
	 * @return Status
	 */
	public function rename( array $options ) {
		static $keepDetails = [ 'attachedMethod' => true, 'attachedTimestamp' => true ];

		$wikisAttached = array_map(
			function ( $details ) use ( $keepDetails ) {
				return array_intersect_key( $details, $keepDetails );
			},
			$this->oldCAUser->queryAttached()
		);

		$status = $this->setRenameStatuses( array_keys( $wikisAttached ) );
		if ( !$status->isOK() ) {
			return $status;
		}

		// Rename the user centrally and unattach the old user from all
		// attached wikis. Each will be reattached as its LocalRenameUserJob
		// runs.
		$this->databaseUpdates->update(
			$this->oldUser->getName(),
			$this->newUser->getName()
		);

		// Update CA's AntiSpoof if enabled
		if ( class_exists( CentralAuthSpoofUser::class ) ) {
			$spoof = new CentralAuthSpoofUser( $this->newUser->getName() );
			$spoof->update( $this->oldUser->getName() );
		}

		// From this point on all code using CentralAuthUser
		// needs to use the new username, except for
		// the renameInProgress function. Probably.

		// Clear some caches...
		$this->oldCAUser->quickInvalidateCache();
		$this->newCAUser->quickInvalidateCache();

		// If job insertion fails, an exception will cause rollback of all DBs.
		// The job will block on reading renameuser_status until this commits due to it using
		// a locking read and the pending update from setRenameStatuses() above. If we end up
		// rolling back, then the job will abort because the status will not be 'queued'.
		$this->injectLocalRenameUserJobs( $wikisAttached, $options );

		$this->logger->log(
			$this->oldUser->getName(),
			$this->newUser->getName(),
			$options
		);

		return Status::newGood();
	}

	/**
	 * @param array $wikis
	 *
	 * @return Status
	 */
	private function setRenameStatuses( array $wikis ) {
		$rows = [];
		foreach ( $wikis as $wiki ) {
			// @TODO: This shouldn't know about these column names
			$rows[] = [
				'ru_wiki' => $wiki,
				'ru_oldname' => $this->oldUser->getName(),
				'ru_newname' => $this->newUser->getName(),
				'ru_status' => 'queued'
			];
		}

		$success = $this->renameuserStatus->setStatuses( $rows );
		if ( !$success ) {
			// Race condition: Another admin already started the rename!
			return Status::newFatal( 'centralauth-rename-alreadyinprogress', $this->newUser->getName() );
		}

		return Status::newGood();
	}

	/**
	 * @param array $wikisAttached Attached wiki info
	 * @param array $options
	 *
	 * @return Status
	 */
	private function injectLocalRenameUserJobs(
		array $wikisAttached, array $options
	) {
		$job = $this->getJob( $options, $wikisAttached );
		$statuses = $this->renameuserStatus->getStatuses( GlobalRenameUserStatus::READ_LATEST );
		foreach ( $statuses as $wiki => $status ) {
			if ( $status === 'queued' ) {
				call_user_func( $this->jobQueueGroupGenerator, $wiki )->push( $job );
				break;
			}
		}
	}

	/**
	 * @param array $options
	 * @param array $wikisAttached Attached wiki info
	 *
	 * @return Job
	 */
	private function getJob( array $options, array $wikisAttached ) {
		$params = [
			'from' => $this->oldUser->getName(),
			'to' => $this->newUser->getName(),
			'renamer' => $this->performingUser->getName(),
			'reattach' => $wikisAttached,
			'movepages' => $options['movepages'],
			'suppressredirects' => $options['suppressredirects'],
			'promotetoglobal' => false,
			'reason' => $options['reason'],
			'session' => $this->session,
			'force' => isset( $options['force'] ) && $options['force'],
		];

		$title = Title::newFromText( 'Global rename job' ); // This isn't used anywhere!
		return new LocalRenameUserJob( $title, $params );
	}
}
