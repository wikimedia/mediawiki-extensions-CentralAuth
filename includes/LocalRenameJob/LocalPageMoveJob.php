<?php

/**
 * Job class to move a set of pages
 */
class LocalPageMoveJob extends Job {
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
	 */
	public function __construct( Title $title, $params ) {
		parent::__construct(
			'LocalPageMoveJob',
			$title,
			$params
		);
	}

	public function run() {
		// Need to set $wgUser so the move log is attributed correctly.
		global $wgUser;
		$oldUser = $wgUser;
		$wgUser = User::newFromName( $this->params['renamer'] );
		foreach ( $this->params['pages'] as $current => $target ) {
			$this->movePage(
				Title::newFromText( $current ),
				Title::newFromText( $target )
			);
		}

		// Cleanup
		$wgUser = $oldUser;
	}

	protected function movePage( Title $oldPage, Title $newPage ) {
		if ( $newPage->exists() && !$oldPage->isValidMoveTarget( $newPage ) ) {
			wfDebugLog( 'CentralAuthRename', "Could not move $oldPage to $newPage" );
			// @todo log this somewhere publicly?
		} else {
			$msg = wfMessage( 'centralauth-rename-movelog' )
				->params( $this->params['from'], $this->params['to'] )
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

}
