<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class CreateLocalAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Creates a local account on this wiki for a global user';
		$this->addArg( 'username', 'User name', true );
	}

	public function execute() {
		if ( !class_exists( 'CentralAuthUser' ) ) {
			$this->error( "CentralAuth isn't enabled on this wiki\n", 1 );
		}

		$username = $this->getArg( 0 );
		$creator = new CentralAuthLocalAccountCreator( $username );
		try {
			$creator->create();
		} catch ( Exception $e ) {
			$this->error( $e->getMessage(), 1 );
		}
		$this->output( "User '$username' created\n" );
	}
}

$maintClass = 'CreateLocalAccount';
require_once( DO_MAINTENANCE );
