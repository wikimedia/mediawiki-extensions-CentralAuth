<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupAssignmentService;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class AddToGlobalGroup extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addArg( 'user', 'Username' );
		$this->addArg( 'group', 'Group name' );
		$this->addOption( 'remove', 'Remove rather than add' );
		$this->addOption( 'expiry', 'Group membership expiry time as a strtotime-compatible timestamp or \'never\'. '
			. 'Can be used both for new and existing group memberships.', false, true );
		$this->addOption( 'reason', 'Reason for the action', true, true );
		$this->addDescription( 'Add a user to a global group, remove them, or modify their expiry.' );
	}

	public function execute() {
		$username = $this->getArg( 0 );
		$group = $this->getArg( 1 );
		$groupsToAdd = $this->getOption( 'remove' ) ? [] : [ $group ];
		$groupsToRemove = $this->getOption( 'remove' ) ? [ $group ] : [];
		$expiry = [];
		if ( $this->hasOption( 'expiry' ) ) {
			$parsedExpiry = UserGroupAssignmentService::expiryToTimestamp( $this->getOption( 'expiry' ) );
			if ( $parsedExpiry === false ) {
				$this->fatalError( 'Invalid expiry' );
			}
			$expiry = [ $group => $parsedExpiry ];
		}
		$reason = $this->getOption( 'reason' );

		$performingUser = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		if ( !$performingUser ) {
			$this->fatalError( 'Maintenance user unavailable' );
		}
		$performingAuthority = new UltimateAuthority( $performingUser );

		$services = $this->getServiceContainer();
		$userFactory = $services->getUserFactory();
		$user = $userFactory->newFromName( $username );
		if ( !$user ) {
			$this->fatalError( 'Invalid username' );
		}
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( !$centralUser->exists() ) {
			$this->fatalError( 'User does not exist centrally' );
		}

		$globalGroupAssignmentService = CentralAuthServices::getGlobalGroupAssignmentService( $services );

		[ $added, $removed ] = $globalGroupAssignmentService->saveChangesToUserGroups(
			$performingAuthority,
			$centralUser,
			$groupsToAdd,
			$groupsToRemove,
			$expiry,
			$reason,
		);

		if ( $added || $removed ) {
			$this->output( "Success\n" );
		} else {
			$this->output( "No changes made\n" );
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = AddToGlobalGroup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
