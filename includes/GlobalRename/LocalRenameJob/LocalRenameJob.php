<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob;

use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\ScopedCallback;

/**
 * Base class for jobs that change a user's
 * name. Intended to be run on local wikis
 * indvidually.
 *
 * Parameters:
 * - from: current username
 * - to: new username to rename to
 * - renamer: username of the performer
 * - ignorestatus: when true, the rename will be done even if another job is supposed to be
 *   already doing it. This should only be used for stuck renames.
 * - session: array of session data from RequestContext::exportSession()
 */
abstract class LocalRenameJob extends Job {

	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	private LoggerInterface $logger;

	private bool $markedAsDone = false;

	/**
	 * @param Title $title
	 * @param array $params
	 */
	public function __construct( Title $title, $params ) {
		parent::__construct( $this->command, $title, $params );
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	/**
	 * @throws Exception
	 */
	public function run(): bool {
		$this->setRenameUserStatus(
			CentralAuthServices::getGlobalRenameFactory()
				->newGlobalRenameUserStatus( $this->params['to'] )
		);

		// Bail if it's already done or in progress. Use a locking read to block until the
		// transaction adding this job is done, so we can see its changes. This is similar to
		// the trick that the RenameUser extension does.
		$status = $this->renameuserStatus->getStatus( IDBAccessObject::READ_LOCKING );
		// Clear any REPEATABLE-READ snapshot in case the READ_LOCKING blocked above. We want
		// regular non-locking SELECTs to see all the changes from that transaction we waited on.
		// Making a new transaction also reduces deadlocks from the locking read.
		// T145596
		$fnameTrxOwner = get_class( $this ) . '::' . __FUNCTION__;
		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$factory->commitPrimaryChanges( $fnameTrxOwner );

		if ( empty( $this->params['ignorestatus'] ) ) {
			if ( $status !== 'queued' && $status !== 'failed' ) {
				$this->logger->info( 'Skipping duplicate rename from {oldName} to {newName}', [
					'oldName' => $this->params['from'],
					'newName' => $this->params['to'],
					'component' => 'GlobalRename',
					'status' => $status,
				] );
				return true;
			}
		}

		if ( isset( $this->params['session'] ) ) {
			// Don't carry over users or sessions because it's going to be wrong
			// across wikis
			$this->params['session']['userId'] = 0;
			$this->params['session']['sessionId'] = '';
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( static function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}
		try {
			$this->doRun( $fnameTrxOwner );
			$this->addTeardownCallback( [ $this, 'scheduleNextWiki' ] );
		} catch ( Exception $e ) {
			// This will lock the user out of their account until a sysadmin intervenes
			$factory->rollbackPrimaryChanges( $fnameTrxOwner );
			$this->updateStatus( 'failed' );
			$factory->commitPrimaryChanges( $fnameTrxOwner );
			// Record job failure in CentralAuth channel (T217211)
			$this->logger->error( 'Failed to rename {oldName} to {newName} ({error})', [
				'oldName' => $this->params['from'],
				'newName' => $this->params['to'],
				'component' => 'GlobalRename',
				'error' => $e->getMessage()
			] );
			throw $e;
		}

		return true;
	}

	/**
	 * Actually do the work for the job class
	 * @param string $fnameTrxOwner Caller name
	 */
	abstract protected function doRun( $fnameTrxOwner );

	protected function setRenameUserStatus( GlobalRenameUserStatus $status ) {
		$this->renameuserStatus = $status;
	}

	/**
	 * Get the user object for the user who is doing the renaming
	 * "Auto-create" if it doesn't exist yet.
	 * @return User
	 */
	protected function getRenameUser() {
		$user = User::newFromName( $this->params['renamer'] );
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		// If the username is a reserved name, don't worry about the account
		// existing, just use it.
		if ( !$userNameUtils->isUsable( $user->getName() ) ) {
			return $user;
		}

		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		// Race condition where the renamer isn't attached here, but
		// someone creates an account in the meantime and then bad
		// stuff could happen...
		// For the meantime, just use a system account
		if ( !$caUser->attachedOn( WikiMap::getCurrentWikiId() ) && $user->getId() !== 0 ) {
			return User::newSystemUser( 'Global rename script', [ 'steal' => true ] );
		}

		if ( $user->getId() == 0 ) {
			// No local user, lets "auto-create" one
			if ( CentralAuthServices::getUtilityService()->autoCreateUser( $user )->isGood() ) {
				// So the internal cache is reloaded
				return User::newFromName( $user->getName() );
			}

			// Auto-creation didn't work, fallback on the system account.
			return User::newSystemUser( 'Global rename script', [ 'steal' => true ] );
		}

		// Account is attached and exists, just use it :)
		return $user;
	}

	protected function done() {
		$this->renameuserStatus->done( WikiMap::getCurrentWikiId() );
		$this->markedAsDone = true;

		$caNew = CentralAuthUser::getInstanceByName( $this->params['to'] );
		$caNew->quickInvalidateCache();
	}

	/**
	 * @param string $status
	 */
	protected function updateStatus( $status ) {
		$this->renameuserStatus->updateStatus( WikiMap::getCurrentWikiId(), $status );
	}

	/**
	 * @param bool $status See Job::addTeardownCallback
	 */
	protected function scheduleNextWiki( $status ) {
		if ( $status === false ) {
			if ( !$this->markedAsDone ) {
				// This will lock the user out of their account until a sysadmin intervenes.
				$this->updateStatus( 'failed' );
				// Bail out just in case the error would affect all renames and continuing would
				// just put all wikis of the user in failure state. Running the rename for this
				// wiki again (e.g. with fixStuckGlobalRename.php) will resume the job chain.
				return;
			} else {
				// The rename was completed, but some follow-up step failed (e.g. a deferred update or an
				// onTransactionCommitOrIdle() callback), potentially leaving the user in an inconsistent
				// state. This sucks, but we have no way to rollback the rename or to un-mark it as done.
				// We also have no way to know *what* failed at this point, all we get is a boolean
				// true/false $status, but the exception should have been recorded by the job entry point.
				// Log a warning and continue renaming on the other wikis. (T399090)
				$this->logger->warning( 'Renamed {oldName} to {newName}, but some follow-up step failed', [
					'oldName' => $this->params['from'],
					'newName' => $this->params['to'],
					'component' => 'GlobalRename',
				] );
			}
		}

		$job = new static( $this->getTitle(), $this->getParams() );
		$nextWiki = null;
		$statuses = $this->renameuserStatus->getStatuses( IDBAccessObject::READ_LATEST );
		foreach ( $statuses as $wiki => $status ) {
			if ( $status === 'queued' && $wiki !== WikiMap::getCurrentWikiId() ) {
				$nextWiki = $wiki;
				break;
			}
		}
		if ( $nextWiki ) {
			MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $nextWiki )->push( $job );
		}
	}
}
