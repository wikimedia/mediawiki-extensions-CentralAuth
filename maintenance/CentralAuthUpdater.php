<?php

use Wikimedia\Rdbms\Database;

/**
 * @author Melos
 *
 * This class is needed to run updates for external CentralAuth db defined with
 * global $wgCentralAuthDatabase.
 */
class CentralAuthUpdater extends DatabaseUpdater {

	/**
	 * @param Database $mainDb
	 * @param Database $CentralAuthUpdaterDb
	 * @param bool $shared
	 * @param Maintenance|null $maintenance
	 *
	 * @throws MWException
	 * @return DatabaseUpdater
	 */
	public static function newForCentralAuthUpdaterDB(
		Database $mainDb,
		Database $CentralAuthUpdaterDb,
		$shared = false,
		Maintenance $maintenance = null
	) {
		$updater = parent::newForDB(
			$mainDb,
			$shared,
			$maintenance
		);

		$updater->extensionUpdates = [];

		$updater->db = $CentralAuthUpdaterDb;
		$updater->db->setFlag( DBO_DDLMODE );
		$updater->maintenance->setDB( $updater->db );

		return $updater;
	}

	/**
	 * @suppress PhanTypeMissingReturn
	 * @return null
	 */
	protected function getCoreUpdateList() {
		// not used but is abstract and must be implemented
	}

	/**
	 * Really do the updates we care about.
	 * Passed as a callable into DatabaseUpdater::addExtensionUpdate in the
	 * LoadExtensionSchemaUpdates hook
	 *
	 * @param DatabaseUpdater $updater
	 *        Automatically added when called through DatabaseUpdater::addExtensionUpdate
	 * @param DatabaseUpdater $CentralAuthUpdater Our actual updater object
	 */
	public static function realDoUpdates(
		DatabaseUpdater $updater,
		DatabaseUpdater $CentralAuthUpdater
	) {
		$CentralAuthUpdater->doUpdates( [ 'extensions' ] );
	}

}
