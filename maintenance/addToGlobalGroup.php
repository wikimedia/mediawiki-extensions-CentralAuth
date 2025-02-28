<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Specials\SpecialUserRights;
use MediaWiki\User\User;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$specialPage = new SpecialGlobalGroupMembership(
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserNamePrefixSearch(),
			$this->getServiceContainer()->getUserNameUtils(),
			CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() ),
			CentralAuthServices::getGlobalGroupLookup( $this->getServiceContainer() )
		);

		$username = $this->getArg( 0 );
		$group = $this->getArg( 1 );
		$groupsToAdd = $this->getOption( 'remove' ) ? [] : [ $group ];
		$groupsToRemove = $this->getOption( 'remove' ) ? [ $group ] : [];
		$expiry = [];
		if ( $this->hasOption( 'expiry' ) ) {
			$parsedExpiry = SpecialUserRights::expiryToTimestamp( $this->getOption( 'expiry' ) );
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
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $performingUser );
		$context->setAuthority( new UltimateAuthority( $performingUser ) );
		$specialPage->setContext( $context );

		$user = $userFactory->newFromName( $username );
		if ( !$user ) {
			$this->fatalError( 'Invalid username' );
		}
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( !$centralUser->exists() ) {
			$this->fatalError( 'User does not exist centrally' );
		}

		[ $added, $removed ] = $specialPage->doSaveUserGroups(
			$centralUser,
			$groupsToAdd,
			$groupsToRemove,
			$this->getOption( 'reason' ),
			[],
			$expiry
		);

		if ( $added || $removed ) {
			$this->output( "Success\n" );
		} else {
			$this->output( "No changes made\n" );
		}
	}

}

$maintClass = AddToGlobalGroup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
