<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends LocalCentralAuthJob {
	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function run() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'Extension:Renameuser is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->setRenameUserStatus( new GlobalRenameUserStatus( $from ) );
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
			$this->updateStatus( 'failed' );
			throw new MWException( 'RenameuserSQL::rename returned false.' );
		}
		if ( $this->params['movepages'] ) {
			$this->movePages();
		}
		$this->done();
		return true;
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

	protected function done() {
		parent::done();
		$caOld = new CentralAuthUser( $this->params['from'] );
		$caOld->quickInvalidateCache();
	}
}
