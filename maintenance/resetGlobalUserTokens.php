<?php
/**
 * Reset the user_token for all users on the wiki. Useful if you believe
 * that your user table was acidentally leaked to an external source.
 * See $wgAuthenticationTokenVersion as a faster method if cookies have
 * been leaked but the DB values haven't.
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
	/** @var DatabaseBase */
	protected $dbr;
	/** @var DatabaseBase */
	protected $dbw;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Reset the user_token of all users on the wiki. Note that this may log some of them out.";
		$this->addOption( 'nowarn', "Hides the 5 seconds warning", false, false );
		$this->addOption( 'minid', "Start processing after this gu_id, default is 0", false, true );
		$this->addOption( 'maxid', "Stop processing after this gu_id, default is MAX(gu_id) in globalusers", false, true );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		if ( !$this->getOption( 'nowarn' ) ) {
			$this->output( "The script is about to reset the user_token for ALL USERS in the database.\n" );
			$this->output( "This will log all of them out and is not necessary unless you believe your\n" );
			$this->output( "user table has been compromised.\n" );
			$this->output( "\n" );
			$this->output( "Abort with control-c in the next five seconds (skip this countdown with --nowarn) ... " );
			wfCountDown( 5 );
		}

		$this->dbr = CentralAuthUtils::getCentralSlaveDB();
		$this->dbw = CentralAuthUtils::getCentralDB();
		$maxid = $this->getOption( 'maxid', -1 );
		if ( $maxid == -1 ) {
			$maxid = $this->dbr->selectField( 'globaluser', 'MAX(gu_id)', array(), __METHOD__ );
		}
		$min = $this->getOption( 'minid', 0 );
		$max = $min + $this->mBatchSize;

		do {
			$result = $this->dbr->select( 'globaluser',
				array( 'gu_id', 'gu_name' ),
				array( 'gu_id > ' . $this->dbr->addQuotes( $min ),
					'gu_id <= ' . $this->dbr->addQuotes( $max )
				),
				__METHOD__
			);

			$ids = $names = array();
			foreach ( $result as $user ) {
				$ids[] = $user->gu_id;
				$names[] = $user->gu_name;
			}

			$this->output( 'Resetting token for users ' . min( $ids ) . '..' . max( $ids) . '... ' );
			$this->updateUsers( $ids, $names );

			$min = $max;
			$max = $min + $this->mBatchSize;

			if ( $max > $maxid ) {
				$max = $maxid;
			}

			CentralAuthUtils::waitForSlaves();

		} while ( $min < $maxid );

	}

	private function updateUsers( array $ids, array $names ) {
		$salt = MWCryptRand::generateHex( 32 );
		$this->dbw->query(
			'UPDATE globaluser SET '
				. "gu_auth_token = md5(concat(gu_name, '$salt')), "
				. 'gu_cas_token = gu_cas_token + 1 '
			. 'WHERE gu_id IN (' . $this->dbw->makeList( $ids ) . ')',
			__METHOD__
		);
		foreach ( $names as $name ) {
			$user = new CentralAuthUser( $name );
			$user->quickInvalidateCache();
		}
		$missed = count( $ids ) - $this->dbw->affectedRows();
		if ( $missed ) {
			$this->output( "failed for $missed users!\n" );
		} else {
			$this->output( "OK\n" );
		}
	}
}

$maintClass = "ResetGlobalUserTokens";
require_once RUN_MAINTENANCE_IF_MAIN;
