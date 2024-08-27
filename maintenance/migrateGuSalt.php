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
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateGuSalt extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Migrate all old type passwords which have their password stored in the gu_salt column'
			. ' to have them being stored in the format :B:salt:password in the gu_password column'
			. ' matching the format of the modern password system.'
		);
		$this->setBatchSize( 30 );
		$this->requireExtension( 'CentralAuth' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'MigrateGuSalt';
	}

	/**
	 * @inheritDoc
	 */
	public function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		if ( !$dbw->tableExists( 'globaluser', __METHOD__ ) ) {
			$this->output( "The globaluser table does not seem to exist.\n" );
			return true;
		}

		if ( !$dbw->fieldExists( 'globaluser', 'gu_salt', __METHOD__ ) ) {
			$this->output( "The gu_salt column does not seem to exist.\n" );
			return true;
		}

		$typeCond = [
			$dbw->expr( 'gu_salt', '!=', '' ),
			$dbw->expr( 'gu_password', IExpression::NOT_LIKE, new LikeValue( ':', $dbw->anyString() ) ),
		];
		$batchSize = $this->getBatchSize();

		$count = 0;
		$minUserId = 0;
		while ( true ) {
			$start = microtime( true );
			$this->beginTransaction( $dbw, __METHOD__ );
			$res = $dbw->newSelectQueryBuilder()
				->select( [ 'gu_id', 'gu_password', 'gu_salt' ] )
				->from( 'globaluser' )
				->where( $dbw->expr( 'gu_id', '>', $minUserId ) )
				->andWhere( $typeCond )
				->orderBy( 'gu_id' )
				->limit( $batchSize )
				->lockInShareMode()
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $res->numRows() === 0 ) {
				$this->commitTransaction( $dbw, __METHOD__ );
				break;
			}

			foreach ( $res as $row ) {
				$minUserId = $row->gu_id;
				$count++;
				$dbw->newUpdateQueryBuilder()
					->update( 'globaluser' )
					->set( [
						'gu_password' => ':B:' . $row->gu_salt . ':' . $row->gu_password,
						'gu_salt' => ''
					] )
					->where( [ 'gu_id' => $row->gu_id ] )
					->caller( __METHOD__ )
					->execute();
			}

			$this->commitTransaction( $dbw, __METHOD__ );

			$this->output( "Last id processed: $minUserId; Actually updated: $count...\n" );
			$delta = microtime( true ) - $start;
			$this->output( sprintf(
				"%4d password salts migrated in %6.2fms (%6.2fms each)\n",
				$res->numRows(),
				$delta * 1000.0,
				( $delta / $res->numRows() ) * 1000.0
			) );
		}

		$this->output( "$count users rows updated.\n" );
		return true;
	}
}

$maintClass = MigrateGuSalt::class;
require_once RUN_MAINTENANCE_IF_MAIN;
