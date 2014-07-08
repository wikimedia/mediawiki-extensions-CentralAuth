<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
abstract class LocalCentralAuthJob extends Job {
	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

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
		// FIXME:
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
