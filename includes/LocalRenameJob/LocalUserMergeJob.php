<?php

/**
 * Job class to merge a user locally
 * This is intended to be run on each wiki individually
 */
class LocalUserMergeJob extends LocalRenameJob {
	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalUserMergeJob', $title, $params, $id );
	}

	public function doRun() {
		if ( !class_exists( 'MergeUser' ) ) {
			throw new MWException( 'Extension:UserMerge is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->updateStatus( 'inprogress' );

		$this->maybeCreateNewUser( $to );

		$renamingUser = $this->getRenameUser();
		foreach ( $from as $olduser ) {
			// Merge the users in order
			$um = new MergeUser(
				User::newFromName( $olduser ),
				User::newFromName( $to ),
				new UserMergeNoopLogger()
			);
			$um->merge( $renamingUser );
			$um->delete( $renamingUser, 'wfMessage' );
		}

		$this->done();
		return true;
	}

	protected function done() {
		parent::done();
		foreach ( $this->params['from'] as $from ) {
			$caOld = new CentralAuthUser( $from );
			$caOld->quickInvalidateCache();
		}
	}

	/**
	 * It's possible that the final global user doesn't
	 * have an account here yet. So let's create one, and
	 * then merge into it.
	 *
	 * @param string $newName
	 * @throws MWException
	 */
	private function maybeCreateNewUser( $newName ) {
		global $wgAuth;
		$user = User::newFromName( $newName );
		if ( $user->getId() ) {
			// User already exists, nothing to do.
			return;
		}

		// Logic from CentralAuthHooks::attemptAddUser
		$user->loadDefaults( $newName );
		$status = $user->addToDatabase();
		if ( !$status->isOK() ) {
			$this->updateStatus( 'failed' );
			throw new MWException( "User::addToDatabase failed for $newName: {$status->getWikiText()}" );
		}
		$wgAuth->initUser( $user, true );
		wfRunHooks( 'AuthPluginAutoCreate', array( $user ) );
	}

}
