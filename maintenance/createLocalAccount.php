<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Status\Status;

class CreateLocalAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Creates a local account on this wiki for a global user' );
		$this->addArg( 'username', 'User name', true );
	}

	public function execute() {
		$username = $this->getArg( 0 );
		$status = CentralAuthServices::getForcedLocalCreationService()
			->attemptAutoCreateLocalUserFromName( $username );

		if ( !$status->isGood() ) {
			$this->error( "autoCreateUser failed for $username: " .
				Status::wrap( $status )->getWikiText( false, false, 'en' ) );
			return;
		}

		$this->output( "User '$username' created\n" );
	}
}

$maintClass = CreateLocalAccount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
