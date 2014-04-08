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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * Maintenance script to reset the user_token for all users on the wiki.
 *
 * @ingroup Maintenance
 */
class ResetGlobalUserTokens extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Reset the user_token of all users on the wiki. Note that this may log some of them out.";
		$this->addOption( 'nowarn', "Hides the 5 seconds warning", false, false );
		$this->setBatchSize( 1000 );
	}

	public function execute() {

		if ( !$this->getOption( 'nowarn' ) ) {
			$this->output( "The script is about to reset the user_token for ALL USERS in the database.\n" );
			$this->output( "This may log some of them out and is not necessary unless you believe your\n" );
			$this->output( "user table has been compromised.\n" );
			$this->output( "\n" );
			$this->output( "Abort with control-c in the next five seconds (skip this countdown with --nowarn) ... " );
			wfCountDown( 5 );
		}

		// We list user by user_id from one of the slave database
		$dbr = CentralAuthUser::getCentralDB( DB_SLAVE );

		$where = array();

		$maxid = $dbr->selectField( 'globaluser', 'MAX(gu_id)', array(), __METHOD__ );

		$min = 0;
		$max = $this->mBatchSize;

		do {
			$result = $dbr->select( 'globaluser',
				array( 'gu_id', 'gu_name' ),
				array_merge(
					$where,
					array( 'gu_id > ' . $dbr->addQuotes( $min ),
						'gu_id <= ' . $dbr->addQuotes( $max )
					)
				),
				__METHOD__
			);

			foreach ( $result as $user ) {
				$this->updateUser( $user->gu_name );
			}

			$min = $max;
			$max = $min + $this->mBatchSize;

			wfWaitForSlaves();

		} while ( $min <= $maxid );

	}

	private function updateUser( $username ) {
		$user = new CentralAuthUser( $username );
		$this->output( 'Resetting user_token for "' . $username . '": ' );
		// Change value
		$user->resetAuthToken();
		$this->output( " OK\n" );
	}
}

$maintClass = "ResetGlobalUserTokens";
require_once RUN_MAINTENANCE_IF_MAIN;
