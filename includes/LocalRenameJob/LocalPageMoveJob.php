<?php

/**
 * Job class to move a page
 *
 * Parameters:
 * 'session' - Array of session data for RequestContext::importScopedSession()
 * 'renamer' - Username of the user who should be attributed for the page move
 * 'pages' (deprecated) - Array of old page title => new page title
 * 'old' - array( namespace id, db key ) of old title
 * 'new' - array( namespace id, db key ) of new title
 * 'from' - Old username
 * 'to' - New username
 * 'suppressredirects' - Whether redirects should be suppressed
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
		if ( isset( $this->params['session'] ) ) {
			$callback = RequestContext::importScopedSession( $this->params['session'] );
		}
		$this->user = User::newFromName( $this->params['renamer'] );
		if ( isset( $this->params['pages'] ) ) {
			// Old calling style for b/c
			foreach ( $this->params['pages'] as $current => $target ) {
				$this->movePage(
					Title::newFromText( $current ),
					Title::newFromText( $target )
				);
			}
		} else {
			$oldTitle = Title::makeTitle( $this->params['old'][0], $this->params['old'][1] );
			$newTitle = Title::makeTitle( $this->params['new'][0], $this->params['new'][1] );
			$this->movePage( $oldTitle, $newTitle );
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
