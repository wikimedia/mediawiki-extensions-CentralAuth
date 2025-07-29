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

	private CentralAuthDatabaseManager $databaseManager;

	public function __construct( CentralAuthDatabaseManager $databaseManager ) {
		$this->databaseManager = $databaseManager;
	}

	/**
	 * @param string $oldname
	 * @param string $newname
	 * @param int|null $type
	 */
	public function update( $oldname, $newname, $type = GlobalRenameRequest::RENAME ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();

		$data = [ 'gu_name' => $newname ];
		if ( $type === GlobalRenameRequest::VANISH ) {
			// Vanish requests need to remove user's email
			$data[ 'gu_email' ] = '';
		}

		// NOTE: This is essentially a bulk version of CentralAuthUser::adminUnattach.
		$dbw->startAtomic( __METHOD__ );

		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( $data )
			->where( [ 'gu_name' => $oldname ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'localuser' )
			->where( [ 'lu_name' => $oldname ] )
			->caller( __METHOD__ )
			->execute();

		// We've unattaching all local users, so clear the global edit count (T313900).
		// It will be recalculated when we re-attach the renamed user in LocalRenameUserJob.
		$id = $dbw->newSelectQueryBuilder()
			->select( 'gu_id' )
			->from( 'globaluser' )
			->where( [ 'gu_name' => $oldname ] )
			->caller( __METHOD__ )
			->fetchField();
		$dbw->newUpdateQueryBuilder()
			->update( 'global_edit_count' )
			->set( [ 'gec_count' => 0 ] )
			->where( [ 'gec_user' => $id ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->endAtomic( __METHOD__ );
	}
}
