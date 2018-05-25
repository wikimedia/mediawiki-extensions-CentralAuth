<?php

use Wikimedia\Rdbms\IDatabase;

/**
 * Update the rows in the CentralAuth tables during a user merge
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalUserMergeDatabaseUpdates {
	/**
	 * @return IDatabase
	 */
	protected function getDB() {
		return CentralAuthUtils::getCentralDB();
	}

	/**
	 * Merge a global user's rows into
	 * another global user's ones.
	 *
	 * @param string $oldname Old global username
	 * @param string $newname New global username
	 * @param int|null $newId New global user ID
	 */
	public function merge( $oldname, $newname, $newId = null ) {
		$dbw = $this->getDB();

		$dbw->startAtomic( __METHOD__ );
		// Delete the old user's globaluser row
		$dbw->delete(
			'globaluser',
			[ 'gu_name' => $oldname ],
			__METHOD__
		);

		// Move localuser rows to the new name
		// if there is no row already there
		// Note that in some cases, an account
		// may not exist at the new name on
		// that wiki yet.
		$dbw->update(
			'localuser',
			[
				'lu_name' => $newname,
				'lu_global_id' => $newId
			],
			[ 'lu_name' => $oldname ],
			__METHOD__,
			[ 'IGNORE' ]
		);

		// Get the list of wikis with local accounts attached to the global account
		$attachedWikis = $dbw->selectFieldValues(
			'localuser',
			'lu_wiki',
			[ 'lu_name' => $newname ]
		);
		// For each attached account, update the lu_local_id field
		$user = CentralAuthUser::newFromId( $newId );
		foreach ( $attachedWikis as $wiki ) {
			$localId = $user->getLocalId( $wiki );
			// Note that $localId will be null in case there is no local account with new name
			// on that wiki yet
			$dbw->update(
				'localuser',
				[ 'lu_local_id' => $localId ],
				[
					'lu_name' => $newname,
					'lu_wiki' => $wiki
				]
			);
		}

		// Delete the ones that are duplicates,
		// we'll use the existing rows
		$dbw->delete(
			'localuser',
			[ 'lu_name' => $oldname ]
		);

		$dbw->endAtomic( __METHOD__ );
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
			[ 'rq_performer' => $toId ],
			[ 'rq_performer' => $fromId ],
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
		$dbw->startAtomic( __METHOD__ );
		$dbw->update(
			'global_user_groups',
			[ 'gug_user' => $toId ],
			[ 'gug_user' => $fromId ],
			__METHOD__,
			[ 'IGNORE' ]
		);
		// Delete any duplicates left over
		$dbw->delete(
			'global_user_groups',
			[ 'gug_user' => $fromId ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
	}
}
