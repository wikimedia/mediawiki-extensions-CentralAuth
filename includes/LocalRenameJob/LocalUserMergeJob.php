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
			throw new Exception( 'Extension:UserMerge is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->updateStatus( 'inprogress' );

		$toUser = $this->maybeCreateNewUser( $to );

		$renamingUser = $this->getRenameUser();
		foreach ( $from as $olduser ) {
			// Merge the users in order
			$um = new MergeUser(
				User::newFromName( $olduser ),
				$toUser,
				new UserMergeLogger()
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
	 * @return User
	 * @throws Exception
	 */
	private function maybeCreateNewUser( $newName ) {
		$user = User::newFromName( $newName );
		if ( $user->getId() ) {
			// User already exists, nothing to do.
			return $user;
		}

		$creator = new CentralAuthLocalAccountCreator( $newName );
		$creator->create();
		$user->clearInstanceCache( 'name' );
		$user->load( User::READ_LATEST );
		return $user;
	}

}
