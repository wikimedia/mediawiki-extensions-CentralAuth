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
		CentralAuthSpoofUser::batchRecord( $this->getDB( DB_MASTER ), $items );
	}

	/**
	 * @param int $db
	 * @param array $groups
	 * @param string|bool $wiki
	 * @return IDatabase
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

	protected function waitForSlaves() {
		CentralAuthUtils::waitForSlaves();
	}

	/**
	 * @param string $name
	 * @return CentralAuthSpoofUser
	 */
	protected function makeSpoofUser( $name ) {
		return new CentralAuthSpoofUser( $name );
	}
}

$maintClass = "BatchCAAntiSpoof";
require_once DO_MAINTENANCE;
