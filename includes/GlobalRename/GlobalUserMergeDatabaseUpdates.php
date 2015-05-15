<?php

/**
 * Update the rows in the CentralAuth tables during a user merge
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */

class GlobalUserMergeDatabaseUpdates {
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
		// Note that in some cases, an account
		// may not exist at the new name on
		// that wiki yet.
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
	 * Updates renameuser_queue table if enabled
	 * @param int $fromId
	 * @param int $toId
	 */
	public function mergeRenameUserQueue( $fromId, $toId ) {
		global $wgCentralAuthEnableGlobalRenameRequest;
		if ( !$wgCentralAuthEnableGlobalRenameRequest ) {
			return;
		}

		$this->getDB()->update(
			'renameuser_queue',
			array( 'rq_performer' => $toId ),
			array( 'rq_performer' => $fromId ),
			__METHOD__
		);
	}

	/**
	 * Updates global_user_groups table
	 * @param int $fromId
	 * @param int $toId
	 */
	public function mergeGlobalUserGroups( $fromId, $toId ) {
		$dbw = $this->getDB();
		$dbw->update(
			'global_user_groups',
			array( 'gug_user' => $toId ),
			array( 'gug_user' => $fromId ),
			__METHOD__,
			array( 'IGNORE' )
		);
		// Delete any duplicates left over
		$dbw->delete(
			'global_user_groups',
			array( 'gug_user' => $fromId ),
			__METHOD__
		);
	}
}
