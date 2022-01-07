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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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
		$dbw = $databaseManager->getCentralDB( DB_PRIMARY );

		$counter = 0;

		while ( true ) {
			$rows = $dbw->select(
				[ 'global_user_groups' ],
				[ 'gug_user', 'gug_group', ],
				[ 'gug_expiry < ' . $dbw->addQuotes( $dbw->timestamp() ), ],
				__METHOD__,
				[
					'LIMIT' => $this->getBatchSize(),
					'ORDER BY' => 'gug_expiry',
				]
			);

			if ( $rows->numRows() === 0 ) {
				break;
			}

			$conds = [];
			foreach ( $rows as $row ) {
				$conds[] = $dbw->makeList( [
					'gug_user'  => (int)$row->gug_user,
					'gug_group' => $row->gug_group,
				], $dbw::LIST_AND );
			}

			$dbw->delete(
				'global_user_groups',
				$dbw->makeList( $conds, $dbw::LIST_OR ),
				__METHOD__
			);

			$counter += $dbw->affectedRows();

			$this->output( "Purged $counter expired group memberships so far... waiting for replication to catch up." );
			$databaseManager->waitForReplication();
			$this->output( ".. done.\n" );
		}

		$this->output( "All done, purged $counter rows total.\n" );
	}
}

$maintClass = PurgeExpiredGlobalRights::class;
require_once RUN_MAINTENANCE_IF_MAIN;
