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
		return CentralAuthUser::getCentralDB();
	}

	/**
	 * Merge a global user's rows into
	 * another global user's ones.
	 *
	 * @param string $oldname
	 * @param string $newname
	 */
	public function merge( $oldname, $newname ) {
		$dbw = $this->getDB();

		$dbw->begin( __METHOD__ );
		// Delete the old user's globaluser row
		$dbw->delete(
			'globaluser',
			array( 'gu_name' => $oldname ),
			__METHOD__
		);

		// Move localuser rows to the new name
		// if there is no row already there
		$dbw->update(
			'localuser',
			array( 'lu_name' => $newname ),
			array( 'lu_name' => $oldname ),
			__METHOD__,
			array( 'IGNORE' )
		);

		// Delete the ones that are duplicates,
		// we'll use the existing rows
		$dbw->delete(
			'localuser',
			array( 'lu_name' => $oldname )
		);

		$dbw->commit( __METHOD__ );
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