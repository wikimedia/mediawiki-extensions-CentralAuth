<?php

use MediaWiki\MediaWikiServices;
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
	 * Static flag for when we're moving a page; this is currently only read by the
	 * AbuseFilterShouldFilterAction hook handler to avoid filtering our page moves.
	 * @var bool
	 */
	public static $moveInProgress = false;
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
		$permissionManager = MediaWikiServices::getInstance()
			->getPermissionManager();

		// Mark user page moves as bot on rename user process T97659
		if ( !$permissionManager->userHasRight( $this->user, 'bot' ) ) {
			$guard = $permissionManager->addTemporaryUserRights( $this->user,  'bot' );

			// Remove it at the end of the job process
			$this->addTeardownCallback( static function () use ( &$guard ) {
				ScopedCallback::consume( $guard );
			} );
		}

		if ( isset( $this->params['session'] ) ) {
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( static function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
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
		return true;
	}

	protected function movePage( Title $oldPage, Title $newPage ) {
		$mp = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage( $oldPage, $newPage );

		$valid = $mp->isValidMove();
		if ( !$valid->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Invalid page move: {$oldPage} -> {$newPage}" );
			return;
		}

		$msg = wfMessage( 'centralauth-rename-movelog' )
			->params( $this->params['from'], $this->params['to'] )
			->inContentLanguage()
			->text();

		self::$moveInProgress = true;
		try {
			$status = $mp->move( $this->user, $msg, !$this->params['suppressredirects'] );
		} finally {
			self::$moveInProgress = false;
		}
		if ( isset( $status ) && !$status->isOK() ) {
			wfDebugLog( 'CentralAuthRename', "Page move failed: {$oldPage} -> {$newPage}" );
		}
	}
}
