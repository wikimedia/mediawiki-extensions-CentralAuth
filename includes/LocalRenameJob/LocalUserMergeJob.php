<?php

use MediaWiki\MediaWikiServices;

/**
 * Job class to merge a user locally
 * This is intended to be run on each wiki individually
 */
class LocalUserMergeJob extends LocalRenameJob {
	public function __construct( Title $title, array $params ) {
		$this->command = 'LocalUserMergeJob';

		parent::__construct( $title, $params );
	}

	public function doRun( $fnameTrxOwner ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UserMerge' ) ) {
			throw new Exception( 'Extension:UserMerge is not installed' );
		}

		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->updateStatus( 'inprogress' );
		// Make the status update visible to all other transactions immediately
		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$factory->commitPrimaryChanges( $fnameTrxOwner );

		$toUser = $this->maybeCreateNewUser( $to );
		if ( $toUser instanceof Status ) {
			$factory->rollbackPrimaryChanges( $fnameTrxOwner );
			$this->updateStatus( 'failed' );
			// Make the status update visible to all other transactions immediately
			$factory->commitPrimaryChanges( $fnameTrxOwner );
			throw new RuntimeException( "autoCreateUser failed for '{$to}': " .
				$toUser->getWikiText( false, false, 'en' ) );
		}

		$renamingUser = $this->getRenameUser();
		foreach ( $from as $olduser ) {
			// Merge the users in order
			$um = new MergeUser(
				User::newFromName( $olduser ),
				$toUser,
				new UserMergeLogger(),
				MergeUser::USE_MULTI_COMMIT
			);
			$um->merge( $renamingUser, $fnameTrxOwner );
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
	 * @return User|Status Returns status on failure
	 */
	private function maybeCreateNewUser( $newName ) {
		$user = User::newFromName( $newName );
		if ( $user->getId() ) {
			// User already exists, nothing to do.
			return $user;
		}

		$status = Status::wrap( CentralAuthUtils::autoCreateUser( $user ) );
		if ( !$status->isGood() ) {
			return $status;
		}

		return $user;
	}

}
