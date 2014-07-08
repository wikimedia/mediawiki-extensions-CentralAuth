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

	public function run() {
		if ( !class_exists( 'SpecialUserMerge' ) ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'Extension:UserMerge is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->setRenameUserStatus( new GlobalRenameUserStatus( $to ) );
		$this->updateStatus( 'inprogress' );

		$this->maybeCreateNewUser( $to );

		foreach ( $from as $olduser ) {
			// Merge the users in order
			$um = new SpecialUserMerge();
			$data = array(
				'delete' => true,
				'olduser' => $olduser,
				'newuser' => $to
			);
			$status = $um->onSubmit( $data );
			if ( !$status->isGood() ) {
				$this->updateStatus( 'failed' );
				throw new MWException( 'SpecialUserMerge::onSubmit returned a bad status: '
					. $status->getWikiText() );
			}
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
		if ( !$user->getId() ) {
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

}
