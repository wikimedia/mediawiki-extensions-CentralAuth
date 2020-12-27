<?php

use MediaWiki\Logger\LoggerFactory;

/**
 * Merge a global user
 *
 * @license GPL-2.0-or-later
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
	 * @var array
	 */
	private $session;

	/** @var CentralAuthHookRunner */
	private $hookRunner;

	/**
	 * @param User $performingUser
	 * @param CentralAuthUser[] $oldCAUsers
	 * @param CentralAuthUser $newCAUser
	 * @param GlobalRenameUserStatus $renameuserStatus
	 * @param callable $jobQueueGroupGenerator Callable for getting a job queue group for a given wiki
	 * @param GlobalUserMergeDatabaseUpdates $databaseUpdates
	 * @param GlobalUserMergeLogger $logger
	 * @param array $session
	 * @param CentralAuthHookRunner $hookRunner
	 */
	public function __construct(
		User $performingUser,
		array $oldCAUsers,
		CentralAuthUser $newCAUser,
		GlobalRenameUserStatus $renameuserStatus,
		/* callable */ $jobQueueGroupGenerator,
		GlobalUserMergeDatabaseUpdates $databaseUpdates,
		GlobalUserMergeLogger $logger,
		array $session,
		CentralAuthHookRunner $hookRunner
	) {
		$this->performingUser = $performingUser;
		$this->oldCAUsers = $oldCAUsers;
		$this->newCAUser = $newCAUser;
		$this->renameuserStatus = $renameuserStatus;
		$this->jobQueueGroupGenerator = $jobQueueGroupGenerator;
		$this->databaseUpdates = $databaseUpdates;
		$this->logger = $logger;
		$this->session = $session;
		$this->hookRunner = $hookRunner;
	}

	private function addLogEntry( $reason ) {
		$this->logger->log(
			$this->oldCAUsers,
			$this->newCAUser->getName(),
			$reason
		);
	}

	private function clearCaches() {
		foreach ( $this->oldCAUsers as $oldCAUser ) {
			$oldCAUser->quickInvalidateCache();
		}
		$this->newCAUser->quickInvalidateCache();
	}

	public function merge( $reason ) {
		$wikis = [];
		foreach ( $this->oldCAUsers as $oldCAUser ) {
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

			$logger = LoggerFactory::getInstance( 'CentralAuthUserMerge' );
			$logger->info( "Merged '{oldname}' into '{newname}'", [
				'oldname' => $oldName,
				'oldid' => $oldId,
				'newname' => $newName,
				'newid' => $newId,
				'attached' => $oldCAUser->listAttached(),
			] );

			$this->databaseUpdates->merge( $oldName, $newName, $newId );
			$this->databaseUpdates->mergeGlobalUserGroups( $oldId, $newId );
			$this->databaseUpdates->mergeRenameUserQueue( $oldId, $newId );
			$oldCAUser->removeAntiSpoof();

			$this->hookRunner->onCentralAuthGlobalUserMerged(
				$oldName, $newName, $oldId, $newId );
		}

		$this->clearCaches();
		// If job insertion fails, an exception will cause rollback of all DBs.
		// The job will block on reading renameuser_status until this commits due to it using
		// a locking read and the pending update from setRenameStatuses() above. If we end up
		// rolling back, then the job will abort because the status will not be 'queued'.
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
		$rows = [];
		foreach ( $wikis as $wiki => $users ) {
			foreach ( $users as $user ) {
				// @TODO: This shouldn't know about these column names
				$rows[] = [
					'ru_wiki' => $wiki,
					'ru_oldname' => $user,
					'ru_newname' => $this->newCAUser->getName(),
					'ru_status' => 'queued'
				];
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
			[
				'to' => $this->newCAUser->getName(),
				'renamer' => $this->performingUser->getName(),
				'from' => $users,
				'session' => $this->session,
			]
		);
	}
}
