<?php

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
		try {
			$this->doRun();
		} catch ( Exception $e ) {
			// This will lock the user out of their account
			// until a sysadmin intervenes
			$this->updateStatus( 'failed' );
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
		$caUser = CentralAuthUser::getInstance( $user );
		// Race condition where the renamer isn't attached here, but
		// someone creates an account in the meantime and then bad
		// stuff could happen...
		// For the meantime, just use a system account
		if ( !$caUser->attachedOn( wfWikiID() ) && $user->getId() !== 0 ) {
			return User::newFromName( 'Global rename script' );
		} elseif ( $user->getId() == 0 ) {
			// No local user, lets "auto-create" one
			if ( CentralAuthHooks::attemptAddUser( $user ) ) {
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

		$caNew = new CentralAuthUser( $this->params['to'] );
		$caNew->quickInvalidateCache();
	}

	protected function updateStatus( $status ) {
		$this->renameuserStatus->setStatus( wfWikiID(), $status );
	}
}
