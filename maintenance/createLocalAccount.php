<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;

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
			->attemptAutoCreateLocalUserFromName(
				$username,
				new UltimateAuthority( User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ) )
			);

		if ( !$status->isGood() ) {
			$this->error( "autoCreateUser failed for $username:" );
			$this->error( $status );
			return;
		}

		$this->output( "User '$username' created\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = CreateLocalAccount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
