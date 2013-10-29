<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends Job {

	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function run() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			throw new MWException( 'Extension:Renameuser is not installed' );
		}
		$this->updateStatus( 'inprogress' );
		$from = $this->params['from'];
		$to = $this->params['to'];
		$oldUser = User::newFromName( $from );
		//$contribs = User::newFromName( $from )->getEditCount();
		$rename = new RenameuserSQL(
			$from,
			$to,
			$oldUser->getId(),
			array( 'checkIfUserExists' => false )
		);
		if ( !$rename->rename() ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'Rename failed...?' );
		}
		if ( $this->params['movepages'] ) {
			$this->movePages();
		}
		$this->done();
		return true;
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
		if ( !$caUser->attachedOn( wfWikiID() ) && $user->getId() !== 0 ) {
			// @todo find a better name
			return User::newFromName( 'Global rename script' );
		} elseif ( $user->getId() == 0 ) {
			// No local user, lets "auto-create" one
			$user->addToDatabase(); // @todo Figure out if this actually works!
			$caUser->attach( wfWikiID() );
			return User::newFromName( $user->getName() ); // So the internal cache is reloaded
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

		foreach ( $pages as $row ) {
			$oldPage = Title::newFromRow( $row );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $toTitle->getDBkey(), $row->page_title ) );
			# Do not autodelete or anything, title must not exist
			if ( $newPage->exists() && !$oldPage->isValidMoveTarget( $newPage ) ) {
				wfDebugLog( 'CentralAuthRename', "Couldnt move $oldPage to $newPage" );
				// @todo log this somewhere publicly?
			} else {
				$msg = wfMessage( 'centralauth-rename-logsummary' )
					->params( $from, $to )
					->inContentLanguage()
					->text();
				// Need to play with $wgUser so the move log
				// is attributed properly
				$oldUser = $wgUser;
				$wgUser = $this->getRenameUser();
				$oldPage->moveTo( $newPage, false, $msg, !$this->params['suppressredirects'] );
				$wgUser = $oldUser; // good maners to cleanup
			}
		}
	}

	public function done() {
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->delete(
			'renameuser_status',
			array( 'ru_oldname' => $this->params['from'], 'ru_wiki' => wfWikiID() ),
			__METHOD__
		);
	}

	public function updateStatus( $status ) {
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->update(
			'renameuser_status',
			array( 'ru_status' => $status ),
			array( 'ru_oldname' => $this->params['from'], 'ru_wiki' => wfWikiID() ),
			__METHOD__
		);
	}
}
