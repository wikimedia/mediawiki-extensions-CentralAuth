<?php
/**
 * Wrap old-style password hashes in a stronger hash.
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
 * @file
 * @ingroup Maintenance
 * @author Chris Steipp <csteipp@wikimedia.org>
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to wrap all passwords of a certain type in a specified layered
 * type that wraps around the old type.
 *
 * @since 1.26
 */
class WrapPasswordHash extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Wrap all passwords of a certain type in a new layered type";
		$this->addOption( 'type',
			'Password type to wrap passwords in (must inherit LayeredParameterizedPassword)', true, true );
		$this->addOption( 'verbose', 'Enables verbose output', false, false, 'v' );
		$this->addOption( 'start', 'Start wrapping passwords at gu_id', false, true );
		$this->addOption( 'end', 'End wrapping passwords at gu_id', false, true );
		$this->addOption( 'backup',
			'Backup unwrapped hashes to a local file. Once you have successfully ' .
			'migrated passwords, you should delete this backup.', false, true );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		global $wgAuth;

		if ( !$wgAuth->allowSetLocalPassword() ) {
			$this->error( '$wgAuth does not allow local passwords. Aborting.', 1 );
		}

		$passwordFactory = new PasswordFactory();
		$passwordFactory->init( RequestContext::getMain()->getConfig() );

		$typeInfo = $passwordFactory->getTypes();
		$layeredType = $this->getOption( 'type' );

		// Check that type exists and is a layered type
		if ( !isset( $typeInfo[$layeredType] ) ) {
			$this->error( 'Undefined password type', 1 );
		}

		$passObj = $passwordFactory->newFromType( $layeredType );
		if ( !$passObj instanceof LayeredParameterizedPassword ) {
			$this->error( 'Layered parameterized password type must be used.', 1 );
		}

		// Extract the first layer type
		$typeConfig = $typeInfo[$layeredType];
		$firstType = $typeConfig['types'][0];

		// Get a list of password types that are applicable
		$dbw = CentralAuthUser::getCentralDB();
		$typeCond = 'gu_password' . $dbw->buildLike( ":$firstType:", $dbw->anyString() );

		// Old-style passwords are either :A:<userid>:<hash> or <hash>
		if ( $firstType === 'A' ) {
			$typeCond = $dbw->makeList(
				array( $typeCond, 'LENGTH( gu_password ) = 32' ),
				LIST_OR
			);
		}

		// Setup backup file
		$backupFileName = $this->getOption( 'backup', false );
		$backupFile = false;
		if ( $backupFileName ) {
			umask( 077 );
			$backupFile = fopen( $backupFileName, 'w' );
			if ( !$backupFile ) {
				$this->error( 'Could not open backup, aborting', 1 );
			}
			if ( substr( sprintf( '%o', fileperms( $backupFileName ) ), -4 ) !== '0600'
				&& !chmod( $backupFileName, 0600 )
			) {
				$this->error( 'Could not chmod backup file, aborting', 1 );
			}
		}

		$startUserId = (int) $this->getOption( 'start', 0 );
		$endUserId = (int) $this->getOption( 'end', null );

		if ( $endUserId === 0 ) {
			$endUserId = $dbw->selectField( 'globaluser', 'MAX( gu_id )' );
		}

		do {
			$selectEnd = min( $startUserId + $this->mBatchSize, $endUserId );

			if ( $this->hasOption( 'verbose' ) ) {
				$this->output( "Updating users: $startUserId - $selectEnd\n" );
			}

			$dbw->begin();
			$res = $dbw->select( 'globaluser',
				array( 'gu_id', 'gu_name', 'gu_salt', 'gu_password' ),
				array(
					'gu_id >= ' . $dbw->addQuotes( $startUserId ),
					'gu_id <= ' . $dbw->addQuotes( $selectEnd ),
					$typeCond
				),
				__METHOD__,
				array(
					'ORDER BY' => 'gu_id',
					'LOCK IN SHARE MODE',
				)
			);

			/** @var User[] $updateUsers */
			$updateUsers = array();
			foreach ( $res as $row ) {
				if ( $this->hasOption( 'verbose' ) ) {
					$this->output( "Updating password for user {$row->gu_name} ({$row->gu_id}).\n" );
				}
				if ( $backupFile
					&& !fwrite( $backupFile, "{$row->gu_id}\t{$row->gu_password}\t{$row->gu_salt}\n" )
				) {
					$this->error( 'Could not write backup, aborting', 1 );
				}

				$user = CentralAuthUser::newFromId( $row->gu_id );

				// Last time we do this ugly hack. When migrating users to global
				// accounts we set accounts with plain, local hashes to
				// :A:<userid>:<hash>, so we duplicate that here, even though it's
				// computed identical to a :B: style password.
				if ( preg_match( '/^[0-9a-f]{32}$/', $row->gu_password ) ) {
					$row->gu_password = ":A:{$row->gu_salt}:{$row->gu_password}";
				}

				/** @var ParameterizedPassword $password */
				$password = $passwordFactory->newFromCiphertext( $row->gu_password );
				/** @var LayeredParameterizedPassword $layeredPassword */
				$layeredPassword = $passwordFactory->newFromType( $layeredType );
				$layeredPassword->partialCrypt( $password );

				$updateUsers[] = $user;
				$dbw->update( 'globaluser',
					array( 'gu_password' => $layeredPassword->toString() ),
					array( 'gu_id' => $row->gu_id ),
					__METHOD__
				);
			}

			$dbw->commit();

			// Clear memcached so old passwords are wiped out
			foreach ( $updateUsers as $user ) {
				$user->invalidateCache();
			}

			$startUserId = $selectEnd + 1;

		} while ( $selectEnd < $endUserId );

		if ( $backupFile ) {
			fclose( $backupFile );
		}
	}
}

$maintClass = 'WrapPasswordHash';
require_once RUN_MAINTENANCE_IF_MAIN;
