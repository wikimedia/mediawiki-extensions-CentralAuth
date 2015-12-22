<?php

/**
 * Update the rows in the CentralAuth tables during a rename
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */

class GlobalRenameUserDatabaseUpdates {
	/**
	 * @return DatabaseBase
	 */
	protected function getDB() {
		return CentralAuthUtils::getCentralDB();
	}

	/**
	 * @param string $oldname
	 * @param string $newname
	 */
	public function update( $oldname, $newname ) {
		$dbw = $this->getDB();

		$dbw->begin( __METHOD__ );
		$dbw->update(
			'globaluser',
			array( 'gu_name' => $newname ),
			array( 'gu_name' => $oldname ),
			__METHOD__
		);

		$dbw->update(
			'localuser',
			array( 'lu_name' => $newname ),
			array( 'lu_name' => $oldname ),
			__METHOD__
		);

		$dbw->commit( __METHOD__ );
	}
}
