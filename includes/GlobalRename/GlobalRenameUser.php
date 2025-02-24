<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use Job;
use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Rename a global user
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUser {

	/**
	 * @var UserIdentity
	 */
	private $performingUser;

	/**
	 * @var UserIdentity
	 */
	private $oldUser;

	/**
	 * @var CentralAuthUser
	 */
	private $oldCAUser;

	/**
	 * @var UserIdentity
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

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	/**
	 * @var GlobalRenameUserDatabaseUpdates
	 */
	private $databaseUpdates;

	/**
	 * @var GlobalRenameUserLogger
	 */
	private $logger;

	private CentralAuthAntiSpoofManager $caAntiSpoofManager;

	private ?array $session = null;

	private ?UserIdentity $lockPerformingUser;

	/**
	 * @param UserIdentity $performingUser
	 * @param UserIdentity $oldUser
	 * @param CentralAuthUser $oldCAUser
	 * @param UserIdentity $newUser Validated (creatable!) new user
	 * @param CentralAuthUser $newCAUser
	 * @param GlobalRenameUserStatus $renameuserStatus
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param GlobalRenameUserDatabaseUpdates $databaseUpdates
	 * @param GlobalRenameUserLogger $logger
	 * @param CentralAuthAntiSpoofManager $caAntiSpoofManager
	 */
	public function __construct(
		UserIdentity $performingUser,
		UserIdentity $oldUser,
		CentralAuthUser $oldCAUser,
		UserIdentity $newUser,
		CentralAuthUser $newCAUser,
		GlobalRenameUserStatus $renameuserStatus,
		JobQueueGroupFactory $jobQueueGroupFactory,
		GlobalRenameUserDatabaseUpdates $databaseUpdates,
		GlobalRenameUserLogger $logger,
		CentralAuthAntiSpoofManager $caAntiSpoofManager
	) {
		$this->performingUser = $performingUser;
		$this->oldUser = $oldUser;
		$this->oldCAUser = $oldCAUser;
		$this->newUser = $newUser;
		$this->newCAUser = $newCAUser;
		$this->renameuserStatus = $renameuserStatus;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->databaseUpdates = $databaseUpdates;
		$this->logger = $logger;
		$this->caAntiSpoofManager = $caAntiSpoofManager;
	}

	/**
	 * Set session data to use with this rename.
	 *
	 * @param array $session
	 * @return self
	 */
	public function withSession( array $session ): self {
		$this->session = $session;
		return $this;
	}

	/**
	 * Override the performer to be credited with account locking for vanishes.
	 *
	 * @param UserIdentity $lockPerformingUser
	 * @return self
	 */
	public function withLockPerformingUser( UserIdentity $lockPerformingUser ): self {
		$this->lockPerformingUser = $lockPerformingUser;
		return $this;
	}

	/**
	 * Rename a global user (this assumes that the data has been verified before
	 * and that $newUser is being a creatable user)!
	 *
	 * @param array $options
	 * @return Status
	 */
	public function rename( array $options ) {
		if ( $this->oldUser->getName() === $this->newUser->getName() ) {
			return Status::newFatal( 'centralauth-rename-same-name' );
		}

		static $keepDetails = [ 'attachedMethod' => true, 'attachedTimestamp' => true ];

		$wikisAttached = array_map(
			static function ( $details ) use ( $keepDetails ) {
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
			$this->newUser->getName(),
			$options['type'] ?? GlobalRenameRequest::RENAME
		);

		// Update CA's AntiSpoof
		$this->caAntiSpoofManager
			->getSpoofUser( $this->newUser->getName() )
			->update( $this->oldUser->getName() );

		if (
			isset( $options['type'] ) &&
			$options['type'] === GlobalRenameRequest::VANISH &&
			!$this->newCAUser->isLocked()
		) {
			$this->newCAUser->adminLock();
			$this->newCAUser->logAction(
				'setstatus',
				$this->lockPerformingUser ?? $this->performingUser,
				// Reason in this context should be the public log entry, not
				// the private reason stated by the user.
				$options['reason'] ?? '',
				[ 'added' => [ 'locked' ], 'removed' => [] ],
				false,
				false
			);
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
	 */
	private function injectLocalRenameUserJobs(
		array $wikisAttached, array $options
	) {
		$job = $this->getJob( $options, $wikisAttached );
		$statuses = $this->renameuserStatus->getStatuses( IDBAccessObject::READ_LATEST );
		foreach ( $statuses as $wiki => $status ) {
			if ( $status === 'queued' ) {
				$this->jobQueueGroupFactory->makeJobQueueGroup( $wiki )->push( $job );
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
			'force' => isset( $options['force'] ) && $options['force'],
		];
		if ( $this->session !== null ) {
			$params['session'] = $this->session;
		}

		// This isn't used anywhere!
		$title = Title::newFromText( 'Global rename job' );
		return new LocalRenameUserJob( $title, $params );
	}
}
