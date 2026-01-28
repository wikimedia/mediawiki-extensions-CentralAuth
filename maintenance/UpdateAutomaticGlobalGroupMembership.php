<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use BatchRowIterator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use Wikimedia\Message\MessageValue;

class UpdateAutomaticGlobalGroupMembership extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Update membership in automatic global user groups based on local user groups.\n\n" .
			"While membership can also depend on other global groups, this is not yet supported by this script.\n\n" .
			"This script should be executed on all wikis of the wiki farm." );
		$this->addOption( 'local-group', "Name of local group to examine. " .
			"Can be used more than once to examine multiple groups.",
			required: true, withArg: true, multiOccurrence: true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$localGroupsToCheck = $this->getOption( 'local-group' );

		// Query to list users who belong to any of the relevant groups
		$dbr = $this->getReplicaDB();
		$sqb = $dbr->newSelectQueryBuilder()
			->select( 'user_name' )
			->from( 'user' )
			->join( 'user_groups', null, 'ug_user=user_id' )
			->where( [ 'ug_group' => $localGroupsToCheck ] );

		$batches = new BatchRowIterator( $dbr, $sqb, [ 'ug_group', 'ug_user' ], $this->getBatchSize() );

		$ggaService = CentralAuthServices::getGlobalGroupAssignmentService( $this->getServiceContainer() );
		$authority = new UltimateAuthority( User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ) );
		$autoReason = MessageValue::new( 'centralauth-automatic-global-groups-reason-config' );

		$totalAdded = [];
		$totalRemoved = [];

		foreach ( $batches as $rows ) {
			$this->beginTransactionRound( __METHOD__ );
			foreach ( $rows as $row ) {
				$caUser = CentralAuthUser::getInstanceByName( $row->user_name );
				if ( !( $caUser->exists() && $caUser->isAttached() ) ) {
					continue;
				}

				// Set add=[] and remove=[] so that automatic global groups are recalculated
				[ $added, $removed ] =
					$ggaService->saveChangesToUserGroups( $authority, $caUser, [], [], [], '', $autoReason );

				foreach ( $added as $g ) {
					$totalAdded[$g] ??= 0;
					$totalAdded[$g]++;
				}
				foreach ( $removed as $g ) {
					$totalRemoved[$g] ??= 0;
					$totalRemoved[$g]++;
				}
			}
			$this->commitTransactionRound( __METHOD__ );
		}

		foreach ( $totalAdded as $g => $count ) {
			$this->output( "Added $count users to the '$g' user group.\n" );
		}
		foreach ( $totalRemoved as $g => $count ) {
			$this->output( "Removed $count users from the '$g' user group.\n" );
		}
		if ( !$totalAdded && !$totalRemoved ) {
			$this->output( "No changes were made.\n" );
		}
	}
}
