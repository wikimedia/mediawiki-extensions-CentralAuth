<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

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

	public function run() {
		$this->setRenameUserStatus( new GlobalRenameUserStatus( $this->params['to'] ) );

		// bail if it's already done or in progress
		$status = $this->renameuserStatus->getStatus( GlobalRenameUserStatus::READ_LATEST );
		if ( $status !== 'queued' && $status !== 'failed' ) {
			LoggerFactory::getInstance( 'rename' )->info( 'skipping duplicate rename from {user}', [
				'user' => $this->params['from'],
				'to' => $this->params['to'],
				'status' => $status,
			] );
			return true;
		}

		if ( isset( $this->params['session'] ) ) {
			// Don't carry over users or sessions because it's going to be wrong
			// across wikis
			$this->params['session']['userId'] = 0;
			$this->params['session']['sessionId'] = '';
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( function() use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}
		try {
			$this->doRun();
			$this->addTeardownCallback( [ $this, 'scheduleNextWiki' ] );
		} catch ( Exception $e ) {
			// This will lock the user out of their account until a sysadmin intervenes
			$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$factory->rollbackMasterChanges( __METHOD__ );
			$this->updateStatus( 'failed' );
			$factory->commitMasterChanges( __METHOD__ );
			throw $e;
		}

		return true;
	}

	/**
	 * Actually do the work for the job class
	 */
	abstract protected function doRun();

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
		// If the username is a reserved name, don't worry about the account
		// existing, just use it.
		if ( !User::isUsableName( $user->getName() ) ) {
			return $user;
		}
		$caUser = CentralAuthUser::getMasterInstance( $user );
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
		$this->renameuserStatus->setStatus( wfWikiID(), $status );
	}

	protected function scheduleNextWiki() {
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
