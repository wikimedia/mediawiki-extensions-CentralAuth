<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends Job {
	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function run() {
		$this->renameuserStatus = new GlobalRenameUserStatus( $this->params['from'] );
		try {
			$this->doRename();
		} catch ( Exception $e ) {
			// This will lock the user out of their account
			// until a sysadmin intervenes
			$this->updateStatus( 'failed' );
			throw $e;
		}

		return true;
	}

	public function doRename() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			throw new MWException( 'Extension:Renameuser is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->renameuserStatus = new GlobalRenameUserStatus( $from );
		$this->updateStatus( 'inprogress' );

		$oldUser = User::newFromName( $from );

		$rename = new RenameuserSQL(
			$from,
			$to,
			$oldUser->getId(),
			array( 'checkIfUserExists' => false )
		);
		if ( !$rename->rename() ) {
			// This should never happen!
			// If it does happen, the user will be locked out of their account
			// until a sysadmin intervenes...
			throw new MWException( 'RenameuserSQL::rename returned false.' );
		}
		if ( $this->params['movepages'] ) {
			$this->movePages();
		}
		$this->done();
	}

	/**
	 * Get the user object for the user who is doing the renaming
	 * "Auto-create" if it doesn't exist yet.
	 * @return User
	 */
	public function getRenameUser() {
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

	/**
	 * Logic is mainly borrowed from SpecialRenameuser
	 */
	public function movePages() {
		global $wgUser;

		$from = $this->params['from'];
		$to = $this->params['to'];

		$fromTitle = User::newFromName( $from )->getUserPage();
		$toTitle = Title::makeTitleSafe( NS_USER, $to );
		$dbr = wfGetDB( DB_SLAVE );

		$pages = $dbr->select(
			'page',
			array( 'page_namespace', 'page_title' ),
			array(
				'page_namespace IN (' . NS_USER . ',' . NS_USER_TALK . ')',
				'(page_title ' . $dbr->buildLike( $fromTitle->getDBkey() . '/', $dbr->anyString() ) .
				' OR page_title = ' . $dbr->addQuotes( $fromTitle->getDBkey() ) . ')'
			),
			__METHOD__
		);

		// Need to play with $wgUser so the move log is attributed properly
		$oldUser = $wgUser;
		$wgUser = $this->getRenameUser();

		foreach ( $pages as $row ) {
			$oldPage = Title::newFromRow( $row );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $toTitle->getDBkey(), $row->page_title ) );
			# Do not autodelete or anything, title must not exist
			if ( $newPage->exists() && !$oldPage->isValidMoveTarget( $newPage ) ) {
				wfDebugLog( 'CentralAuthRename', "Could not move $oldPage to $newPage" );
				// @todo log this somewhere publicly?
			} else {
				$msg = wfMessage( 'centralauth-rename-movelog' )
					->params( $from, $to )
					->inContentLanguage()
					->text();
				$errors = $oldPage->moveTo( $newPage, false, $msg, !$this->params['suppressredirects'] );
				if ( is_array( $errors ) ) {
					if ( $errors[0][0] === 'hookaborted' ) {
						// AbuseFilter or TitleBlacklist might be interfering, bug 67875
						wfDebugLog( 'CentralAuthRename', "Page move prevented by hook: {$oldPage->getPrefixedText()} -> {$newPage->getPrefixedText()}" );
					}
				}

			}
		}

		$wgUser = $oldUser; // good manners to cleanup
	}

	public function done() {
		$this->renameuserStatus->done( wfWikiID() );

		$caNew = new CentralAuthUser( $this->params['to'] );
		$caOld = new CentralAuthUser( $this->params['from'] );
		$caNew->quickInvalidateCache();
		$caOld->quickInvalidateCache();
	}

	public function updateStatus( $status ) {
		$this->renameuserStatus->setStatus( wfWikiID(), $status );
	}
}
