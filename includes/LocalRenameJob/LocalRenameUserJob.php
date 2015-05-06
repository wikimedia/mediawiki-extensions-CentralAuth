<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends LocalRenameJob {

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
		if ( !isset( $params['reason'] ) ) {
			$params['reason'] = '';
		}
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function doRun() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			throw new Exception( 'Extension:Renameuser is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->updateStatus( 'inprogress' );

		if ( isset( $this->params['session'] ) ) {
			// Don't carry over users or sessions because it's going to be wrong
			// across wikis
			$this->params['session']['userId'] = 0;
			$this->params['session']['sessionId'] = '';
			$callback = RequestContext::importScopedSession( $this->params['session'] );
		}

		if ( isset( $this->params['force'] ) && $this->params['force'] ) {
			// If we're dealing with an invalid username, load the data ourselves to avoid
			// any normalization at all done by User or Title.
			$row = wfGetDB( DB_MASTER )->selectRow(
				'user',
				User::selectFields(),
				array( 'user_name' => $from ),
				__METHOD__
			);
			$oldUser = User::newFromRow( $row );
		} else {
			$oldUser = User::newFromName( $from );
		}

		$rename = new RenameuserSQL(
			$from,
			$to,
			$oldUser->getId(),
			$this->getRenameUser(),
			array(
				'checkIfUserExists' => false,
				'debugPrefix' => 'GlobalRename',
				'reason' => $this->params['reason'],
			)
		);
		if ( !$rename->rename() ) {
			// This should never happen!
			// If it does happen, the user will be locked out of their account
			// until a sysadmin intervenes...
			throw new Exception( 'RenameuserSQL::rename returned false.' );
		}

		if ( $this->params['movepages'] ) {
			$this->movePages( $oldUser );
		}

		if ( $this->params['promotetoglobal'] ) {
			$this->promoteToGlobal();
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
				throw new Exception( "Tried to promote '$newName' to a global account except it doesn't exist locally" );
			} elseif ( $status->hasMessage( 'promote-already-exists' ) ) {
				// Even more wtf.
				throw new Exception( "Tried to prommote '$newName' to a global account except it already exists" );
			}
		}

		$caUser->quickInvalidateCache();
	}

	/**
	 * Queue up jobs to move pages
	 */
	public function movePages( User $oldUser ) {
		$from = $this->params['from'];
		$to = $this->params['to'];

		$fromTitle = $oldUser->getUserPage();
		$toTitle = Title::makeTitleSafe( NS_USER, $to );
		$dbr = wfGetDB( DB_SLAVE );

		$rows = $dbr->select(
			'page',
			array( 'page_namespace', 'page_title' ),
			array(
				'page_namespace IN (' . NS_USER . ',' . NS_USER_TALK . ')',
				'(page_title ' . $dbr->buildLike( $fromTitle->getDBkey() . '/', $dbr->anyString() ) .
				' OR page_title = ' . $dbr->addQuotes( $fromTitle->getDBkey() ) . ')'
			),
			__METHOD__
		);

		$jobParams = array(
			'to' => $to,
			'from' => $from,
			'renamer' => $this->getRenameUser()->getName(),
			'suppressredirects' => $this->params['suppressredirects'],
		);
		if ( isset( $this->params['session'] ) ) {
			$jobParams['session'] = $this->params['session'];
		}
		$jobs = array();

		foreach ( $rows as $row ) {
			$oldPage = Title::newFromRow( $row );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $toTitle->getDBkey(), $row->page_title ) );
			$jobs[] = new LocalPageMoveJob(
				Title::newFromText( 'LocalRenameUserJob' ),
				$jobParams + array( 'pages' => array(
					$oldPage->getPrefixedText() => $newPage->getPrefixedText()
				) )
			);
		}

		JobQueueGroup::singleton()->push( $jobs );
	}

	protected function done() {
		parent::done();
		$caOld = new CentralAuthUser( $this->params['from'] );
		$caOld->quickInvalidateCache();
	}
}
