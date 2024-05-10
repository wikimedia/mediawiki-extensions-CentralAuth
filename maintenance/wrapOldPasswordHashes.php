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

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * CentralAuth version of WrapOldPasswords
 */
class WrapOldPasswordHashes extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Wrap all passwords of a certain type in a new layered type. '
			. 'The script runs in dry-run mode by default (use --update to update rows)' );
		$this->addOption( 'type',
			'Password type to wrap passwords in (must inherit LayeredParameterizedPassword)', true, true );
		$this->addOption( 'verbose', 'Enables verbose output', false, false, 'v' );
		$this->addOption( 'update', 'Actually wrap passwords', false, false, 'u' );
		$this->setBatchSize( 3 );
		$this->requireExtension( 'CentralAuth' );
	}

	public function execute() {
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();

		$typeInfo = $passwordFactory->getTypes();
		$layeredType = $this->getOption( 'type' );

		// Check that type exists and is a layered type
		if ( !isset( $typeInfo[$layeredType] ) ) {
			$this->fatalError( 'Undefined password type: ' . $layeredType );
		}

		$passObj = $passwordFactory->newFromType( $layeredType );
		if ( !$passObj instanceof LayeredParameterizedPassword ) {
			$this->fatalError( 'Layered parameterized password type must be used.' );
		}

		// Extract the first layer type
		$typeConfig = $typeInfo[$layeredType];
		$firstType = $typeConfig['types'][0];

		$update = $this->hasOption( 'update' );

		$databaseManager = CentralAuthServices::getDatabaseManager();

		// Get a list of password types that are applicable
		$dbw = $databaseManager->getCentralPrimaryDB();
		$typeCond = $dbw->expr( 'gu_password', IExpression::LIKE, new LikeValue( ":$firstType:", $dbw->anyString() ) );
		$batchSize = $this->getBatchSize();

		$count = 0;
		$minUserId = 0;
		while ( true ) {
			if ( $update ) {
				$this->beginTransaction( $dbw, __METHOD__ );
			}

			$start = microtime( true );
			$res = $dbw->select(
				'globaluser',
				[ 'gu_id', 'gu_name', 'gu_password' ],
				[
					$dbw->expr( 'gu_id', '>', $minUserId ),
					$typeCond
				],
				__METHOD__,
				[
					'ORDER BY' => 'gu_id',
					'LIMIT' => $batchSize,
					'LOCK IN SHARE MODE',
				]
			);

			if ( $res->numRows() === 0 ) {
				if ( $update ) {
					$this->commitTransaction( $dbw, __METHOD__ );
				}
				break;
			}

			/** @var CentralAuthUser[] $updateUsers */
			$updateUsers = [];
			foreach ( $res as $row ) {
				$user = CentralAuthUser::getPrimaryInstanceByName( $row->gu_name );

				/** @var ParameterizedPassword $password */
				$password = $passwordFactory->newFromCiphertext( $row->gu_password );
				'@phan-var ParameterizedPassword $password';
				/** @var LayeredParameterizedPassword $layeredPassword */
				$layeredPassword = $passwordFactory->newFromType( $layeredType );
				'@phan-var LayeredParameterizedPassword $layeredPassword';
				$layeredPassword->partialCrypt( $password );
				if ( $this->hasOption( 'verbose' ) ) {
					$this->output(
						"Updating password for user {$row->gu_name} ({$row->gu_id}) from " .
						"type {$password->getType()} to {$layeredPassword->getType()}.\n"
					);
				}

				if ( $update ) {
					$count++;
					$updateUsers[] = $user;
					$dbw->update(
						'globaluser',
						[ 'gu_password' => $layeredPassword->toString() ],
						[ 'gu_id' => $row->gu_id ],
						__METHOD__
					);
				}

				$minUserId = $row->gu_id;
			}

			if ( $update ) {
				$this->commitTransaction( $dbw, __METHOD__ );

				// Clear memcached so old passwords are wiped out
				foreach ( $updateUsers as $user ) {
					$user->invalidateCache();
				}
			}

			$this->output( "Last id processed: $minUserId; Actually updated: $count...\n" );
			$delta = microtime( true ) - $start;
			$this->output( sprintf(
				"%4d passwords wrapped in %6.2fms (%6.2fms each)\n",
				$res->numRows(),
				$delta * 1000.0,
				( $delta / $res->numRows() ) * 1000.0
			) );
		}

		if ( $update ) {
			$this->output( "$count users rows updated.\n" );
		} else {
			$this->output( "$count user rows found using old password formats. "
				. "Run script again with --update to update these rows.\n" );
		}
	}
}

$maintClass = WrapOldPasswordHashes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
