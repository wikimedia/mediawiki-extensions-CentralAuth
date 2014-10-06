<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends LocalRenameJob {
	/**
	 * @var bool
	 */
	private $setAFOverride;

	/**
	 * @var bool
	 */
	private $setTBOverride;

	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	public function __construct( $title, $params, $id = 0 ) {
		// For back-compat
		if ( !isset( $params['promotetoglobal'] ) ) {
			$params['promotetoglobal'] = false;
		}
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function doRun() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			throw new MWException( 'Extension:Renameuser is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

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

		if ( $this->params['promotetoglobal'] ) {
			$this->promoteToGlobal();
		}

		if ( $this->params['movepages'] ) {
			$this->movePages();
		}
		$this->done();
	}

	private function promoteToGlobal() {
		$newName = $this->params['to'];
		$caUser = new CentralAuthUser( $newName );
		$status = $caUser->promoteToGlobal( wfWikiID() );
		if ( !$status->isOK() ) {
			if ( $status->hasMessage( 'promote-not-on-wiki' ) ) {
				// Eh, what?
				throw new MWException( "Tried to promote '$newName' to a global account except it doesn't exist locally" );
			} elseif ( $status->hasMessage( 'promote-already-exists' ) ) {
				// Even more wtf.
				throw new MWException( "Tried to prommote '$newName' to a global account except it already exists" );
			}
		}

		$caUser->quickInvalidateCache();
	}

	/**
	 * FIXME: on a of scale one to evil, this is super evil
	 */
	private function disableMoveBlockingThings() {
		global $wgUser;
		if ( !$wgUser->isAllowed( 'abusefilter-bypass' ) ) {
			$wgUser->mRights[] = 'abusefilter-bypass';
			$this->setAFOverride = true;
		}

		if ( !$wgUser->isAllowed( 'tboverride' ) ) {
			$wgUser->mRights[] = 'tboverride';
			$this->setTBOverride = true;
		}
	}

	private function enableMoveBlockingThings() {
		global $wgUser;
		$rights = array();
		if ( $this->setAFOverride ) {
			$rights[] = 'abusefilter-bypass';
			$this->setAFOverride = false;
		}
		if ( $this->setTBOverride ) {
			$rights[] = 'tboverride';
			$this->setTBOverride = false;
		}

		$wgUser->mRights = array_diff( $wgUser->mRights, $rights );
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
					// AbuseFilter or TitleBlacklist might be interfering, bug 67875
					if ( $errors[0][0] === 'hookaborted' ) {
						wfDebugLog( 'CentralAuthRename', "Page move prevented by hook: {$oldPage->getPrefixedText()} -> {$newPage->getPrefixedText()}" );
						$this->disableMoveBlockingThings();
						// Do it again
						$oldPage->moveTo( $newPage, false, $msg, !$this->params['suppressredirects'] );
						$this->enableMoveBlockingThings();
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
