<?php
/**
 * Reset the user_token for all users on the wiki. Useful if you believe
 * that your user table was acidentally leaked to an external source.
 *
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
 * This is mostly taken from core's maintenance/resetUserTokens.php
 *
 * @file
 * @ingroup Maintenance
 * @author Chris Steipp <csteipp@wikimedia.org>
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
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Maintenance script to reset the user_token for all users on the wiki.
 *
 * @ingroup Maintenance
 */
class ResetGlobalUserTokens extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Reset the user_token of all users on the wiki. ' .
			'Note that this may log some of them out.' );
		$this->addOption( 'nowarn', "Hides the 5 seconds warning", false, false );
		$this->addOption( 'minid', "Start processing after this gu_id, default is 0", false, true );
		$this->addOption( 'maxid', "Stop processing after this gu_id, " .
			"default is MAX(gu_id) in globalusers", false, true );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		if ( !$this->getOption( 'nowarn' ) ) {
			$this->output(
				"The script is about to reset the user_token for ALL USERS in the database.\n"
			);
			$this->output(
				"This may log some of them out and is not necessary unless you believe your\n"
			);
			$this->output( "user table has been compromised.\n" );
			$this->output( "\n" );
			$this->output(
				"Abort with control-c in the next five seconds " .
					"(skip this countdown with --nowarn) ... "
			);
			$this->countDown( 5 );
		}

		$databaseManager = CentralAuthServices::getDatabaseManager();
		// We list user by user_id from one of the replica database
		$dbr = $databaseManager->getCentralReplicaDB();
		$maxid = $this->getOption( 'maxid', -1 );

		if ( $maxid == -1 ) {
			$maxid = $dbr->newSelectQueryBuilder()
				->select( 'MAX(gu_id)' )
				->from( 'globaluser' )
				->caller( __METHOD__ )
				->fetchField();
		}
		$min = $this->getOption( 'minid', 0 );
		$max = $min + $this->mBatchSize;

		do {
			$result = $dbr->newSelectQueryBuilder()
				->select( [ 'gu_id', 'gu_name' ] )
				->from( 'globaluser' )
				->where( [
					$dbr->expr( 'gu_id', '>', $min ),
					$dbr->expr( 'gu_id', '<=', $max )
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $user ) {
				$this->updateUser( $user->gu_name );
			}

			$min = $max;
			$max = $min + $this->mBatchSize;

			if ( $max > $maxid ) {
				$max = $maxid;
			}

			$this->waitForReplication();

		} while ( $min < $maxid );
	}

	/**
	 * @param string $username
	 */
	private function updateUser( $username ) {
		$user = new CentralAuthUser( $username, IDBAccessObject::READ_LATEST );
		$this->output( 'Resetting user_token for "' . $username . '": ' );
		// Change value
		$user->resetAuthToken();
		$this->output( " OK\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = ResetGlobalUserTokens::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
