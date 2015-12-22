<?php
// Go through all usernames and calculate and record spoof thingies

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/extensions/AntiSpoof/maintenance/BatchAntiSpoofClass.php" );

class BatchCAAntiSpoof extends BatchAntiSpoof {

	/**
	 * @param $items array
	 */
	protected function batchRecord( $items ) {
		CentralAuthSpoofUser::batchRecord( $this->getDB(), $items );
	}

	/**
	 * @return DatabaseBase
	 */
	protected function getDB() {
		return CentralAuthUtils::getCentralDB();
	}

	/**
	 * @return string
	 */
	protected function getTableName() {
		return 'globaluser';
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
	 * @param $name string
	 * @return CentralAuthSpoofUser
	 */
	protected function makeSpoofUser( $name ) {
		return new CentralAuthSpoofUser( $name );
	}
}

$maintClass = "BatchCAAntiSpoof";
require_once( DO_MAINTENANCE );
