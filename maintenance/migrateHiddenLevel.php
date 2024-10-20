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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;

/**
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class MigrateHiddenLevel extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralPrimaryDB();

		$lastUser = $dbw->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();

		for ( $min = 0; $min <= $lastUser; $min += $this->getBatchSize() ) {
			$max = $min + $this->getBatchSize();
			$this->output( "Now processing global users with id between $min and $max...\n" );

			$dbw->newUpdateQueryBuilder()
				->update( 'globaluser' )
				->set( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_LISTS ] )
				->where( [
					'gu_hidden' => 'lists',
					$dbw->expr( 'gu_id', '>=', $min ),
					$dbw->expr( 'gu_id', '<=', $max ),
				] )
				->caller( __METHOD__ )
				->execute();

			$dbw->newUpdateQueryBuilder()
				->update( 'globaluser' )
				->set( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED ] )
				->where( [
					'gu_hidden' => 'suppressed',
					$dbw->expr( 'gu_id', '>=', $min ),
					$dbw->expr( 'gu_id', '<=', $max ),
				] )
				->caller( __METHOD__ )
				->execute();

			$this->waitForReplication();
		}

		$this->output( "Done.\n" );
	}
}

$maintClass = MigrateHiddenLevel::class;
require_once RUN_MAINTENANCE_IF_MAIN;
