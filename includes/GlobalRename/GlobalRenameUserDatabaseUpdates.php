<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;

/**
 * Update the rows in the CentralAuth tables during a rename
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserDatabaseUpdates {
	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/**
	 * @param CentralAuthDatabaseManager $databaseManager
	 */
	public function __construct( CentralAuthDatabaseManager $databaseManager ) {
		$this->databaseManager = $databaseManager;
	}

	/**
	 * @param string $oldname
	 * @param string $newname
	 */
	public function update( $oldname, $newname ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();

		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'globaluser',
			[ 'gu_name' => $newname ],
			[ 'gu_name' => $oldname ],
			__METHOD__
		);

		$dbw->delete(
			'localuser',
			[ 'lu_name' => $oldname ],
			__METHOD__
		);

		$dbw->endAtomic( __METHOD__ );
	}
}
