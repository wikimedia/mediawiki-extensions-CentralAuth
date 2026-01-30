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

	protected function batchRecord( array $items ): void {
		CentralAuthSpoofUser::batchRecord( CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB(), $items );
	}

	/**
	 * @inheritDoc
	 * @return IReadableDatabase
	 * @suppress PhanParamSignatureMismatch
	 */
	protected function getDB( $db, $groups = [], $wiki = false ) {
		return $db === DB_PRIMARY
			? CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB()
			: CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
	}

	protected function getTableName(): string {
		return 'globaluser';
	}

	protected function getPrimaryKey(): string {
		return 'gu_id';
	}

	protected function getUserColumn(): string {
		return 'gu_name';
	}

	protected function makeSpoofUser( string $name ): CentralAuthSpoofUser {
		return CentralAuthServices::getAntiSpoofManager()->getSpoofUser( $name );
	}
}

// @codeCoverageIgnoreStart
$maintClass = BatchCAAntiSpoof::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
