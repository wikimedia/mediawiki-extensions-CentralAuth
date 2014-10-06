<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends LocalRenameJob {
	/**
	 * How many page moves per each job
	 */
	const MOVES_PER_JOB = 25;

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
		if ( $this->params['movepages'] ) {
			$this->movePages();
		}
		$this->done();
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
	 * Queue up jobs to move pages
	 */
	public function movePages() {
		$from = $this->params['from'];
		$to = $this->params['to'];

		$fromTitle = User::newFromName( $from )->getUserPage();
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
		$jobs = array();
		$pages = array();

		foreach ( $rows as $row ) {
			$oldPage = Title::newFromRow( $row );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $toTitle->getDBkey(), $row->page_title ) );
			$pages[$oldPage->getPrefixedText()] = $newPage->getPrefixedText();
			if ( count( $pages ) > self::MOVES_PER_JOB ) {
				$jobs[] = new LocalPageMoveJob(
					$jobParams + array( 'pages' => $pages )
				);
				$pages = array();
			}
		}

		// Anything left over...
		if ( $pages ) {
			$jobs[] = new LocalPageMoveJob(
				$jobParams + array( 'pages' => $pages )
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
