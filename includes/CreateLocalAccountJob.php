<?php

/**
 * Creates a local account and connects it to the global account.
 * Used to ensure that all users have an attached local account on certain wikis which have some
 * special "central" role (such as $wgMWOAuthCentralWiki for the OAuth extension).
 * @see $wgCentralAuthAutoCreateWikis
 */
class CentralAuthCreateLocalAccountJob extends Job {
	/**
	 * @param Title $title Not used
	 * @param array $params name => user name, from => wiki where the job is created
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

		$user = User::newFromName( $username );

		$canCacheFailure = false;
		$status = CentralAuthUser::attemptAddUser( $user, $canCacheFailure );

		if ( !$status->isOK() ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but got error: " . $status->getWikiText() . "\n" );
			// report the job as successful if the error is cacheable, ie. the operation should
			// not be retried
			return $canCacheFailure;
		}
		return true;
	}
}
