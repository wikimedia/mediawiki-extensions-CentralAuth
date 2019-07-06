<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class CreateLocalAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Creates a local account on this wiki for a global user' );
		$this->addArg( 'username', 'User name', true );
	}

	public function execute() {
		$username = $this->getArg( 0 );

		$user = User::newFromName( $username );
		if ( $user === false ) {
			$this->fatalError( "'$username' is an invalid username\n" );
		}
		// Normalize username
		$username = $user->getName();
		if ( $user->getId() ) {
			$this->fatalError( "User '$username' already exists\n" );
		} else {
			$central = CentralAuthUser::getInstance( $user );
			if ( !$central->exists() ) {
				$this->fatalError( "No such global user: '$username'\n" );
			}

			$status = CentralAuthUtils::autoCreateUser( $user );
			if ( !$status->isGood() ) {
				$this->error( "autoCreateUser failed for $username: " .
					Status::wrap( $status )->getWikiText( null, null, 'en' ) );
			}

			# Update user count
			$ssUpdate = SiteStatsUpdate::factory( [ 'users' => 1 ] );
			$ssUpdate->doUpdate();
			$this->output( "User '$username' created\n" );
		}
	}
}

$maintClass = CreateLocalAccount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
