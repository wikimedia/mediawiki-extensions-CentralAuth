<?php
/**
 * Checks if members of restricted global groups (in the centralauth scope) are
 * still eligible for their group memberships. If not, and the group is configured
 * with automatic demotion, the user is removed from the group.
 *
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

class DemoteIneligibleCentralUsers extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Demote global users who no longer meet conditions for global group membership' );
		$this->addOption( 'dry-run', 'Perform a dry run' );
	}

	public function execute() {
		$dryRun = $this->hasOption( 'dry-run' );

		$services = $this->getServiceContainer();

		$centralWiki = $this->getConfig()->get( CAMainConfigNames::CentralAuthCentralWiki )
			?? WikiMap::getCurrentWikiId();
		if ( WikiMap::getCurrentWikiId() !== $centralWiki ) {
			$this->fatalError( "This script must be run on the CentralAuth central wiki ($centralWiki)." );
		}

		$restrictedGroups = $services->getRestrictedUserGroupConfigReader()->getConfig(
			$centralWiki,
			GlobalGroupAssignmentService::RESTRICTION_SCOPE
		);

		$demotableGroups = array_filter(
			$restrictedGroups,
			static fn ( $restriction ) => $restriction->allowsAutomaticDemotion()
		);
		if ( !$demotableGroups ) {
			$this->output( "No global groups are configured for automatic demotion, exiting.\n" );
			return;
		}

		$databaseManager = CentralAuthServices::getDatabaseManager( $services );
		$centralDbr = $databaseManager->getCentralReplicaDB();
		$groupMembers = $centralDbr->newSelectQueryBuilder()
			->select( [ 'gu_name', 'gug_group' ] )
			->from( 'globaluser' )
			->join( 'global_user_groups', null, 'gug_user = gu_id' )
			->where( [ 'gug_group' => array_keys( $demotableGroups ) ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$restrictedGroupsByUser = [];
		foreach ( $groupMembers as $row ) {
			$restrictedGroupsByUser[$row->gu_name][] = $row->gug_group;
		}
		if ( !$restrictedGroupsByUser ) {
			$this->output( "No members found in demotable groups, exiting.\n" );
			return;
		}

		$actorQueryBuilder = $services->getActorStore()->newSelectQueryBuilder();
		$localUsers = $actorQueryBuilder->whereUserNames( array_keys( $restrictedGroupsByUser ) )
			->caller( __METHOD__ )
			->fetchUserIdentities();

		$nameToIdentity = [];
		foreach ( $localUsers as $userIdentity ) {
			$nameToIdentity[$userIdentity->getName()] = $userIdentity;
		}

		// These will store users that are to be demoted
		$caUsers = [];
		$groupsToRemove = [];

		$userRequirementsChecker = $services->getUserRequirementsConditionChecker();
		$userFactory = $services->getUserFactory();

		foreach ( $restrictedGroupsByUser as $userName => $groups ) {
			if ( !isset( $nameToIdentity[$userName] ) ) {
				$nameToIdentity[$userName] = UserIdentityValue::newAnonymous( $userName );
			}
			$userIdentity = $nameToIdentity[$userName];

			$groupsToRemoveForUser = [];
			foreach ( $groups as $group ) {
				$groupConditions = $demotableGroups[$group]->getMemberConditions();
				if (
					$userRequirementsChecker->recursivelyCheckCondition( $groupConditions, $userIdentity )
					|| $userFactory->newFromUserIdentity( $userIdentity )->isSystemUser()
				) {
					continue;
				}
				$groupsToRemoveForUser[] = $group;
			}

			if ( $groupsToRemoveForUser ) {
				$caUsers[$userName] = CentralAuthUser::getPrimaryInstanceByName( $userName );
				$groupsToRemove[$userName] = $groupsToRemoveForUser;
			}
		}

		if ( !$groupsToRemove ) {
			$this->output( "No ineligible users found, exiting.\n" );
			return;
		}

		$numUsers = count( $groupsToRemove );
		if ( $dryRun ) {
			$this->output( "DRY RUN: $numUsers users would be affected normally\n" );
		} else {
			$this->output( "Removing groups from $numUsers users...\n" );
		}

		$performingUser = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$performingAuthority = new UltimateAuthority( $performingUser );
		$globalGroupAssignmentService = CentralAuthServices::getGlobalGroupAssignmentService( $services );

		foreach ( $groupsToRemove as $userName => $removeGroups ) {
			$groupsList = implode( ', ', $removeGroups );
			if ( !$dryRun ) {
				$logReason = wfMessage( 'restrictedgroups-autodemotion-log-reason' )
					->params( $userName )
					->numParams( count( $removeGroups ) )
					->inContentLanguage()
					->text();
				$globalGroupAssignmentService->saveChangesToUserGroups(
					$performingAuthority, $caUsers[$userName], [], $removeGroups, [], $logReason );
				$this->output( "Removed groups from $userName: $groupsList\n" );
			} else {
				$this->output( "Would remove groups from $userName: $groupsList\n" );
			}
		}

		if ( $dryRun ) {
			$this->output( "DRY RUN: Would remove groups from $numUsers users\n" );
		} else {
			$this->output( "Finished processing. Removed groups from $numUsers users\n" );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = DemoteIneligibleCentralUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
