<?php
// Go through all usernames and calculate and record spoof thingies

use Wikimedia\Rdbms\IDatabase;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/extensions/AntiSpoof/maintenance/BatchAntiSpoofClass.php";

class BatchCAAntiSpoof extends BatchAntiSpoof {

	/**
	 * @param array $items
	 */
	protected function batchRecord( $items ) {
		CentralAuthSpoofUser::batchRecord( $this->getDB( DB_PRIMARY ), $items );
	}

	/**
	 * @param int $db
	 * @param string|string[] $groups
	 * @param string|bool $wiki
	 * @return IDatabase
	 * @suppress PhanParamSignatureMismatch
	 */
	protected function getDB( $db, $groups = [], $wiki = false ) {
		return CentralAuthUtils::getCentralDB();
	}

	/**
	 * @return string
	 */
	protected function getTableName() {
		return 'globaluser';
	}

	protected function getPrimaryKey() {
		return 'gu_id';
	}

	/**
	 * @return string
	 */
	protected function getUserColumn() {
		return 'gu_name';
	}

	/**
	 * @param string $name
	 * @return CentralAuthSpoofUser
	 */
	protected function makeSpoofUser( $name ) {
		return new CentralAuthSpoofUser( $name );
	}
}

$maintClass = BatchCAAntiSpoof::class;
require_once RUN_MAINTENANCE_IF_MAIN;
