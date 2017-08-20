<?php

use Wikimedia\ScopedCallback;

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
		try {
			if ( isset( $this->params['session'] ) ) {
				$callback = RequestContext::importScopedSession( $this->params['session'] );
				$this->addTeardownCallback( function () use ( &$callback ) {
					ScopedCallback::consume( $callback );
				} );
			}
			$this->user = User::newFromName( $this->params['renamer'] );
			// Mark user page moves as bot on rename user process T97659
			if ( !$this->user->isAllowed( 'bot' ) ) {
				$this->user->mRights[] = 'bot';
				$safeRemoveBotRight = true;
			}
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
		} finally {
			// We always want to remove the bot right added above.
			// So we remove it at the end of the job process via addTeardownCallback
			// otherwise some page move action couldn't be marked as bot
			if ( isset( $safeRemoveBotRight ) ) {
				$this->addTeardownCallback( function () {
					unset( $this->user->mRights[array_search( 'bot', $this->user->mRights )] );
				} );
			}
		}
		return true;
	}

	protected function movePage( Title $oldPage, Title $newPage ) {
		$mp = new MovePage( $oldPage, $newPage );
		$valid = $mp->isValidMove();
		if ( !$valid->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Invalid page move: {$oldPage} -> {$newPage}" );
			return;
		}

		$msg = wfMessage( 'centralauth-rename-movelog' )
			->params( $this->params['from'], $this->params['to'] )
			->inContentLanguage()
			->text();

		$status = $mp->move( $this->user, $msg, !$this->params['suppressredirects'] );
		if ( !$status->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Page move failed: {$oldPage} -> {$newPage}" );
		}
	}
}
