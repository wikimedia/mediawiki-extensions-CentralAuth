<?php
/**
 * @license GPL-2.0-or-later
 *
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

/**
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class PurgeExpiredGlobalRights extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralPrimaryDB();

		$counter = 0;

		while ( true ) {
			$rows = $dbw->newSelectQueryBuilder()
				->select( [ 'gug_user', 'gug_group' ] )
				->from( 'global_user_groups' )
				->where( [ $dbw->expr( 'gug_expiry', '<', $dbw->timestamp() ), ] )
				->orderBy( 'gug_expiry' )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $rows->numRows() === 0 ) {
				break;
			}

			$conds = [];
			foreach ( $rows as $row ) {
				$conds[] = $dbw->expr( 'gug_user', '=', (int)$row->gug_user )
					->and( 'gug_group', '=', $row->gug_group );
			}

			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'global_user_groups' )
				->where( [ $dbw->orExpr( $conds ) ] )
				->caller( __METHOD__ )
				->execute();

			$counter += $dbw->affectedRows();

			$this->output( "Purged $counter expired group memberships so far... waiting for replication to catch up." );
			$this->waitForReplication();
			$this->output( ".. done.\n" );
		}

		$this->output( "All done, purged $counter rows total.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeExpiredGlobalRights::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
