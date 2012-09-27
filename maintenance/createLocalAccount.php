<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class CreateLocalAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Creates a local account on this wiki for a global user';
		$this->addArg( 'username', 'User name', true );
	}

	public function execute() {
		$username = $this->getArg( 0 );
		if ( !User::isValidUserName( $username ) ) {
			$this->error( "'$username' is an invalid username\n'" );
			die( 1 );
		}

		$user = User::newFromName( $username );
		// Normalize username
		$username = $user->getName();
		if ( $user->getId() ) {
			$this->error( "User '$username' already exists\n'" );
		} else {
			global $wgAuth;

			$central = CentralAuthUser::getInstance( $user );
			if ( !$central->exists() ) {
				$this->error( "No such global user: '$username'\n" );
				die( 1 );
			}

			$user->loadDefaults( $username );
			$user->addToDatabase();
			$user->addNewUserLogEntryAutoCreate();

			$wgAuth->initUser( $user, true );
			$wgAuth->updateUser( $user );

			# Notify hooks (e.g. Newuserlog)
			wfRunHooks( 'AuthPluginAutoCreate', array( $user ) );

			# Update user count
			$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssUpdate->doUpdate();
		}
	}
}

$maintClass = 'CreateLocalAccount';
require_once( DO_MAINTENANCE );
