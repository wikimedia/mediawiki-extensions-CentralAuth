<?php

use Wikimedia\ScopedCallback;

/**
 * Creates a local account and connects it to the global account.
 * Used to ensure that all users have an attached local account on certain wikis which have some
 * special "central" role (such as $wgMWOAuthCentralWiki for the OAuth extension).
 * @see $wgCentralAuthAutoCreateWikis
 */
class CentralAuthCreateLocalAccountJob extends Job {
	/**
	 * @param Title $title Not used
	 * @param array $params name => user name, from => wiki where the job is created,
	 *   [session] => session data from RequestContext::exportSession()
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'CentralAuthCreateLocalAccountJob', $title, $params );
	}

	/**
	 * Try to create and attach the user.
	 * @throws Exception
	 * @return bool Success
	 */
	public function run() {
		$username = $this->params['name'];
		$from = $this->params['from'];
		$wiki = wfWikiID();

		if ( isset( $this->params['session'] ) ) {
			// restore IP and other request data
			$this->params['session']['userId'] = 0;
			$this->params['session']['sessionId'] = '';
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}

		$user = User::newFromName( $username );
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( $user->getId() !== 0 ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but one already exists\n" );
			return true;
		} elseif ( !$centralUser->exists() ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but no global account exists\n" );
			return true;
		} elseif ( $centralUser->attachedOn( $wiki ) ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but an attached local account already exists\n" );
			return true;
		}

		$success = CentralAuthUtils::autoCreateUser( $user )->isGood();
		if ( $success ) {
			$centralUser->invalidateCache();
		}

		return true;
	}
}
