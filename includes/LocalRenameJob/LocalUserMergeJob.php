<?php

use \MediaWiki\MediaWikiServices;

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
		// Make the status update visible to all other transactions immediately
		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$factory->commitMasterChanges( __METHOD__ );

		$toUser = $this->maybeCreateNewUser( $to );

		$renamingUser = $this->getRenameUser();
		foreach ( $from as $olduser ) {
			// Merge the users in order
			$um = new MergeUser(
				User::newFromName( $olduser ),
				$toUser,
				new UserMergeLogger(),
				MergeUser::USE_MULTI_COMMIT
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
			$caOld = CentralAuthUser::getInstanceByName( $from );
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

		$status = Status::wrap( CentralAuthUtils::autoCreateUser( $user ) );
		if ( !$status->isGood() ) {
			$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$factory->rollbackMasterChanges( __METHOD__ );
			$this->updateStatus( 'failed' );
			$factory->commitMasterChanges( __METHOD__ );
			throw new Exception( "autoCreateUser failed for $newName: " .
				$status->getWikiText( null, null, 'en' ) );
		}

		return $user;
	}

}
