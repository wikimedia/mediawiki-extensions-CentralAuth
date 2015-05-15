<?php

/**
 * Merge a global user
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 * @author Kunal Mehta < legoktm@gmail.com >
 */

class GlobalUserMerge {
	/**
	 * @var User
	 */
	private $performingUser;

	/**
	 * @var CentralAuthUser[]
	 */
	private $oldCAUsers;

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
	 * @var GlobalUserMergeDatabaseUpdates
	 */
	private $databaseUpdates;

	/**
	 * @var GlobalUserMergeLogger
	 */
	private $logger;

	/**
	 * @param User $performingUser
	 * @param CentralAuthUser[] $oldCAUsers
	 * @param CentralAuthUser $newCAUser
	 * @param GlobalRenameUserStatus $renameuserStatus
	 * @param callable $jobQueueGroupGenerator Callable for getting a job queue group for a given wiki
	 * @param GlobalUserMergeDatabaseUpdates $databaseUpdates
	 * @param GlobalUserMergeLogger $logger
	 */
	public function __construct(
		User $performingUser,
		array $oldCAUsers,
		CentralAuthUser $newCAUser,
		GlobalRenameUserStatus $renameuserStatus,
		/* callable */ $jobQueueGroupGenerator,
		GlobalUserMergeDatabaseUpdates $databaseUpdates,
		GlobalUserMergeLogger $logger
	) {
		$this->performingUser = $performingUser;
		$this->oldCAUsers = $oldCAUsers;
		$this->newCAUser = $newCAUser;
		$this->renameuserStatus = $renameuserStatus;
		$this->jobQueueGroupGenerator = $jobQueueGroupGenerator;
		$this->databaseUpdates = $databaseUpdates;
		$this->logger = $logger;
	}

	private function addLogEntry( $reason ) {
		$this->logger->log(
			$this->oldCAUsers,
			$this->newCAUser->getName(),
			$reason
		);
	}

	private function clearCaches() {
		foreach( $this->oldCAUsers as $oldCAUser ) {
			$oldCAUser->quickInvalidateCache();
		}
		$this->newCAUser->quickInvalidateCache();
	}

	public function merge( $reason ) {
		$wikis = array();
		foreach( $this->oldCAUsers as $oldCAUser ) {
			$oldWikis = $oldCAUser->listAttached();
			foreach ( $oldWikis as $wiki ) {
				$wikis[$wiki][] = $oldCAUser->getName();
			}
		}

		$status = $this->setRenameStatuses( $wikis );
		if ( !$status->isOK() ) {
			return $status;
		}

		// Now that we know all users are locked, update globaluser & localuser tables
		foreach ( $this->oldCAUsers as $oldCAUser ) {
			$oldName = $oldCAUser->getName();
			$oldId = $oldCAUser->getId();
			$newName = $this->newCAUser->getName();
			$newId = $this->newCAUser->getId();

			wfDebugLog( 'CentralAuthUserMerge', FormatJson::encode( array(
				'oldname' => $oldName,
				'oldid' => $oldId,
				'newname' => $newName,
				'newid' => $newId,
				'attached' => $oldCAUser->listAttached(),
			) ) );

			$this->databaseUpdates->merge( $oldName, $newName );
			$this->databaseUpdates->mergeGlobalUserGroups( $oldId, $newId );
			$this->databaseUpdates->mergeRenameUserQueue( $oldId, $newId );
			$oldCAUser->removeAntiSpoof();

			Hooks::run( 'CentralAuthGlobalUserMerged',
				array( $oldName, $newName, $oldId, $newId ) );
		}

		$this->clearCaches();

		$this->injectJobs( $wikis );

		$this->addLogEntry( $reason );

		return Status::newGood();
	}

	/**
	 * @param array $wikis
	 *
	 * @return Status
	 */
	private function setRenameStatuses( array $wikis ) {
		$rows = array();
		foreach ( $wikis as $wiki => $users ) {
			foreach ( $users as $user ) {
				// @TODO: This shouldn't know about these column names
				$rows[] = array(
					'ru_wiki' => $wiki,
					'ru_oldname' => $user,
					'ru_newname' => $this->newCAUser->getName(),
					'ru_status' => 'queued'
				);
			}
		}

		$success = $this->renameuserStatus->setStatuses( $rows );
		if ( !$success ) {
			// Race condition: Another admin already started the rename!
			return Status::newFatal( 'centralauth-rename-alreadyinprogress', $this->newCAUser->getName() );
		}

		return Status::newGood();
	}

	/**
	 * Submits the jobs
	 *
	 * @param array $wikis
	 */
	private function injectJobs( array $wikis ) {
		foreach ( $wikis as $wiki => $users ) {
			$job = $this->getJob( $users );
			call_user_func( $this->jobQueueGroupGenerator, $wiki )->push( $job );
		}
	}

	/**
	 * @param array $users
	 *
	 * @return LocalUserMergeJob
	 */
	private function getJob( array $users ) {
		return new LocalUserMergeJob(
			Title::newFromText( 'Global merge job' ),
			array(
				'to' => $this->newCAUser->getName(),
				'renamer' => $this->performingUser->getName(),
				'from' => $users,
			)
		);
	}
}
