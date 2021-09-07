<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\ScopedCallback;

/**
 * Base class for jobs that change a user's
 * name. Intended to be run on local wikis
 * indvidually.
 */
abstract class LocalRenameJob extends Job {
	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	/**
	 * @param Title $title
	 * @param array $params
	 */
	public function __construct( Title $title, $params ) {
		parent::__construct( $this->command, $title, $params );
	}

	public function run() {
		$this->setRenameUserStatus( new GlobalRenameUserStatus( $this->params['to'] ) );

		// Bail if it's already done or in progress. Use a locking read to block until the
		// transaction adding this job is done, so we can see its changes. This is similar to
		// the trick that the RenameUser extension does.
		$status = $this->renameuserStatus->getStatus( GlobalRenameUserStatus::READ_LOCKING );
		// Clear any REPEATABLE-READ snapshot in case the READ_LOCKING blocked above. We want
		// regular non-locking SELECTs to see all the changes from that transaction we waited on.
		// Making a new transaction also reduces deadlocks from the locking read.
		$fnameTrxOwner = get_class( $this ) . '::' . __FUNCTION__; // T145596
		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$factory->commitPrimaryChanges( $fnameTrxOwner );

		if ( empty( $this->params['ignorestatus'] ) ) {
			if ( $status !== 'queued' && $status !== 'failed' ) {
				LoggerFactory::getInstance( 'CentralAuthRename' )
					->info( 'skipping duplicate rename from {user}', [
						'user' => $this->params['from'],
						'to' => $this->params['to'],
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
		if ( !$caUser->attachedOn( wfWikiID() ) && $user->getId() !== 0 ) {
			return User::newFromName( 'Global rename script' );
		} elseif ( $user->getId() == 0 ) {
			// No local user, lets "auto-create" one
			if ( CentralAuthUtils::autoCreateUser( $user )->isGood() ) {
				return User::newFromName( $user->getName() ); // So the internal cache is reloaded
			} else {
				// Auto-creation didn't work, fallback on the system account.
				return User::newFromName( 'Global rename script' );
			}
		} else {
			// Account is attached and exists, just use it :)
			return $user;
		}
	}

	protected function done() {
		$this->renameuserStatus->done( wfWikiID() );

		$caNew = CentralAuthUser::getInstanceByName( $this->params['to'] );
		$caNew->quickInvalidateCache();
	}

	protected function updateStatus( $status ) {
		$this->renameuserStatus->updateStatus( wfWikiID(), $status );
	}

	/**
	 * @param bool $status See Job::addTeardownCallback
	 */
	protected function scheduleNextWiki( $status ) {
		if ( $status === false ) {
			// This will lock the user out of their account until a sysadmin intervenes.
			$this->updateStatus( 'failed' );
			// Bail out just in case the error would affect all renames and continuing would
			// just put all wikis of the user in failure state. Running the rename for this
			// wiki again (e.g. with fixStuckGlobalRename.php) will resume the job chain.
			return;
		}

		$job = new static( $this->getTitle(), $this->getParams() );
		$nextWiki = null;
		$statuses = $this->renameuserStatus->getStatuses( GlobalRenameUserStatus::READ_LATEST );
		foreach ( $statuses as $wiki => $status ) {
			if ( $status === 'queued' && $wiki !== wfWikiID() ) {
				$nextWiki = $wiki;
				break;
			}
		}
		if ( $nextWiki ) {
			JobQueueGroup::singleton( $nextWiki )->push( $job );
		}
	}
}
