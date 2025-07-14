<?php
// Go through all usernames and calculate and record spoof thingies

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use BatchAntiSpoof;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthSpoofUser;
use Wikimedia\Rdbms\IReadableDatabase;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/extensions/AntiSpoof/maintenance/BatchAntiSpoofClass.php";
// @codeCoverageIgnoreEnd

class BatchCAAntiSpoof extends BatchAntiSpoof {

	/**
	 * @param array $items
	 */
	protected function batchRecord( $items ) {
		CentralAuthSpoofUser::batchRecord( CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB(), $items );
	}

	/**
	 * @param int $db
	 * @param string|string[] $groups
	 * @param string|bool $wiki
	 * @return IReadableDatabase
	 * @suppress PhanParamSignatureMismatch
	 */
	protected function getDB( $db, $groups = [], $wiki = false ) {
		if ( $db === DB_PRIMARY ) {
			return CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		} else {
			return CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		}
	}

	/** @inheritDoc */
	protected function getTableName() {
		return 'globaluser';
	}

	/** @inheritDoc */
	protected function getPrimaryKey() {
		return 'gu_id';
	}

	/** @inheritDoc */
	protected function getUserColumn() {
		return 'gu_name';
	}

	/**
	 * @param string $name
	 * @return CentralAuthSpoofUser
	 */
	protected function makeSpoofUser( $name ) {
		return CentralAuthServices::getAntiSpoofManager()->getSpoofUser( $name );
	}
}

// @codeCoverageIgnoreStart
$maintClass = BatchCAAntiSpoof::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
