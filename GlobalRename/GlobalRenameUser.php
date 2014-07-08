<?php

/**
 * Rename a global user
 *
 * @license GNU GPL v2+
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
	 * @var IGlobalRenameUserLogger
	 */
	private $logger;

	/**
	 * @param User $performingUser
	 * @param User $oldUser
	 * @param CentralAuthUser $oldCAUser
	 * @param User $newUser Validated (creatable!) new user
	 * @param CentralAuthUser $newCAUser
	 * @param GlobalRenameUserStatus $renameuserStatus
	 * @param callable $jobQueueGroupGenerator Callable for getting a job queue group for a given wiki
	 * @param GlobalRenameUserDatabaseUpdates $databaseUpdates
	 * @param IGlobalRenameUserLogger $logger
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
		IGlobalRenameUserLogger $logger
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
	}

	private function addLogEntry( $reason ) {
		$this->logger->log(
			$this->oldUser->getName(),
			$this->newUser->getName(),
			$reason
		);

	}

	private function clearCaches() {
		$this->oldCAUser->quickInvalidateCache();
		$this->newCAUser->quickInvalidateCache();
	}

	public function merge( array $options ) {
		$oldWikis = $this->oldCAUser->listAttached();
		$newWikis = $this->newCAUser->listAttached();

		$status = $this->setRenameStatuses( $oldWikis );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->databaseUpdates->remove( $this->oldUser->getName() );

		$this->clearCaches();

		$mergeWikis = array_intersect( $oldWikis, $newWikis );
		$renameWikis = array_intersect( $oldWikis, $mergeWikis );

		$this->injectLocalUserMergeJobs( $mergeWikis, array() );
		$this->injectLocalRenameUserJobs( $renameWikis, array() );

		$this->addLogEntry( $options['reason'] );

		return Status::newGood();
	}

	/**
	 * Rename a global user (this assumes that the data has been verified before
	 * and that $newUser is being a creatable user)!
	 *
	 * @param $options array
	 * @return Status
	 */
	public function rename( array $options ) {
		$wikis = $this->oldCAUser->listAttached();

		$status = $this->setRenameStatuses( $wikis );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->databaseUpdates->update(
			$this->oldUser->getName(),
			$this->newUser->getName()
		);

		// From this point on all code using CentralAuthUser
		// needs to use the new username, except for
		// the renameInProgress function. Probably.

		// Clear some caches...
		$this->clearCaches();

		$this->injectLocalRenameUserJobs( $wikis, $options );

		$this->addLogEntry( $options['reason'] );

		return Status::newGood();
	}

	/**
	 * @param array $wikis
	 *
	 * @return Status
	 */
	private function setRenameStatuses( array $wikis ) {
		$rows = array();
		foreach ( $wikis as $wiki ) {
			// @TODO: This shouldn't know about these column names
			$rows[] = array(
				'ru_wiki' => $wiki,
				'ru_oldname' => $this->oldUser->getName(),
				'ru_newname' => $this->newUser->getName(),
				'ru_status' => 'queued'
			);
		}

		if ( $this->renameuserStatus->setStatuses( $rows ) === 0 ) {
			// Race condition: Another admin already started the rename!
			return Status::newFatal( 'centralauth-rename-alreadyinprogress', $this->newUser->getName() );
		}

		return Status::newGood();
	}

	/**
	 * @param array $options
	 *
	 * @return Job
	 */
	private function getLocalUserMergeJobs( array $options ) {
		//
	}

	private function injectLocalUserMergeJobs( array $wikis, array $options ) {
		$job = $this->getLocalUserMergeJobs( $options );
		// Submit the jobs.
		foreach( $wikis as $wiki ) {
			call_user_func( $this->jobQueueGroupGenerator, $wiki )->push( $job );
		}
	}

	/**
	 * @param array $wikis
	 * @param array $options
	 */
	private function injectLocalRenameUserJobs( array $wikis, array $options ) {
		$job = $this->getLocalRenameUserJob( $options );
		// Submit the jobs.
		foreach( $wikis as $wiki ) {
			call_user_func( $this->jobQueueGroupGenerator, $wiki )->push( $job );
		}
	}

	/**
	 * @param array $options
	 *
	 * @return LocalRenameUserJob
	 */
	private function getLocalRenameUserJob( array $options ) {
		$params = array(
			'from' => $this->oldUser->getName(),
			'to' => $this->newUser->getName(),
			'renamer' => $this->performingUser->getName(),
			'movepages' => $options['movepages'],
			'suppressredirects' => $options['suppressredirects'],
		);

		$title = Title::newFromText( 'Global rename job' ); // This isn't used anywhere!
		return new LocalRenameUserJob( $title, $params );
	}
}
