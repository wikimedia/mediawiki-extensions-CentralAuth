<?php
// Go through all usernames and calculate and record spoof thingies

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/extensions/AntiSpoof/maintenance/BatchAntiSpoofClass.php" );

class BatchCAAntiSpoof extends BatchAntiSpoof {

	/**
	 * @param $items array
	 */
	protected function batchRecord( $items ) {
		CentralAuthSpoofUser::batchRecord( $items );
	}

	/**
	 * @return DatabaseBase
	 */
	protected function getDB() {
		return CentralAuthUser::getCentralDB();
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
