<?php

/**
 * Job class to move a set of pages
 */
class LocalPageMoveJob extends Job {
	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param array $params
	 */
	public function __construct( $params ) {
		parent::__construct(
			'LocalPageMoveJob',
			Title::newFromText( 'Global rename local page move job' ),
			$params
		);
	}

	public function run() {
		// Need to set $wgUser so the move log is attributed correctly.
		global $wgUser;
		$oldUser = $wgUser;
		$this->user = User::newFromName( $this->params['renamer'] );
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
		$mp = new MovePage( $oldPage, $newPage );
		$valid = $mp->isValidMove();
		if ( !$valid->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Invalid page move: {$oldPage->getPrefixedText()} -> {$newPage->getPrefixedText()}" );
			return;
		}

		$msg = wfMessage( 'centralauth-rename-movelog' )
			->params( $this->params['from'], $this->params['to'] )
			->inContentLanguage()
			->text();

		$status = $mp->move( $this->user, $msg, true );
		if ( !$status->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Page move failed: {$oldPage->getPrefixedText()} -> {$newPage->getPrefixedText()}" );
		}
	}
}
