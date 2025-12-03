<?php
/**
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
use MediaWiki\Maintenance\Maintenance;

class EmptyGlobalUserGroup extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addArg( 'group', 'Global group to be removed' );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralPrimaryDB();

		$counter = 0;
		$group = $this->getArg( 'group' );

		while ( true ) {
			$rows = $dbw->newSelectQueryBuilder()
				->select( [ 'gug_user' ] )
				->from( 'global_user_groups' )
				->where( [ 'gug_group' => $group ] )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $rows->numRows() === 0 ) {
				break;
			}

			$users = [];
			foreach ( $rows as $row ) {
				$users[] = (int)$row->gug_user;
			}

			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'global_user_groups' )
				->where( $dbw->expr( 'gug_user', '=', $users )
					->and( 'gug_group', '=', $group )
				)
				->caller( __METHOD__ )
				->execute();

			$counter += $dbw->affectedRows();

			$this->output( "Deleted $counter group members so far... waiting for replication to catch up." );
			$this->waitForReplication();
			$this->output( ".. done.\n" );
		}

		$this->output( "All done, $counter users removed from $group in total.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = EmptyGlobalUserGroup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
