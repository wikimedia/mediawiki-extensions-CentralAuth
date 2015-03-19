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
		$this->user = User::newFromName( $this->params['renamer'] );
		foreach ( $this->params['pages'] as $current => $target ) {
			$this->movePage(
				Title::newFromText( $current ),
				Title::newFromText( $target )
			);
		}
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

		$status = $mp->move( $this->user, $msg, !$this->params['suppressredirects'] );
		if ( !$status->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Page move failed: {$oldPage->getPrefixedText()} -> {$newPage->getPrefixedText()}" );
		}
	}
}
