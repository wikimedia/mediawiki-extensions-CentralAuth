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
	 * Largely follows CentralAuthHooks::attemptAddUser() which cannot be directly reused because
	 * it assumes a web context.
	 * @throws Exception
	 * @return bool Success
	 */
	public function run() {
		global $wgAuth;

		$username = $this->params['name'];
		$from = $this->params['from'];
		$wiki = wfWikiID();

		$user = User::newFromName( $username );
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( !$wgAuth->autoCreate() ) {
			// denied by configuration
			return true;
		} elseif ( $user->getId() !== 0 ) {
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

		$user->loadDefaults( $username );

		$abortMessage = '';
		if ( !Hooks::run( 'AbortAutoAccount', array( $user, &$abortMessage ) ) ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but denied by other extension: $abortMessage\n" );
			return true;
		} elseif ( $user->getName() !== $username ) {
			throw new Exception( "AbortAutoAccount hook tried to change the user name" );
		}

		$status = $user->addToDatabase();
		if ( !$status->isOK() ) {
			wfDebugLog( 'CentralAuth', __CLASS__ . ": tried to create local account for $username "
				. "on $wiki from $from but User::addToDatabase() failed with status: "
				. $status->getWikiText() . "\n" );
			return false;
		}

		$wgAuth->initUser( $user, true );

		Hooks::run( 'AuthPluginAutoCreate', array( $user ) );

		# Update user count
		$statsUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
		$statsUpdate->doUpdate();

		return true;
	}
}
