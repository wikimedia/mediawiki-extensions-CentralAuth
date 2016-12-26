<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class CreateLocalAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->mDescription = 'Creates a local account on this wiki for a global user';
		$this->addArg( 'username', 'User name', true );
	}

	public function execute() {
		if ( !class_exists( 'CentralAuthUser' ) ) {
			$this->error( "CentralAuth isn't enabled on this wiki\n", 1 );
		}

		$username = $this->getArg( 0 );

		$user = User::newFromName( $username );
		if ( $user === false ) {
			$this->error( "'$username' is an invalid username\n", 1 );
		}
		// Normalize username
		$username = $user->getName();
		if ( $user->getId() ) {
			$this->error( "User '$username' already exists\n", 1 );
		} else {
			$central = CentralAuthUser::getInstance( $user );
			if ( !$central->exists() ) {
				$this->error( "No such global user: '$username'\n", 1 );
			}

			$status = CentralAuthUtils::autoCreateUser( $user );
			if ( !$status->isGood() ) {
				$this->error( "autoCreateUser failed for $username: " . $status->getWikiText( null, null, 'en' ) );
			}

			# Update user count
			$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssUpdate->doUpdate();
			$this->output( "User '$username' created\n" );
		}
	}
}

$maintClass = 'CreateLocalAccount';
require_once( DO_MAINTENANCE );
