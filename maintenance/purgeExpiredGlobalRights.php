<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
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
