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
	 * @param int|null $requestType
	 */
	public function update( $oldname, $newname, $requestType = GlobalRenameRequest::RENAME ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();

		$setOptions = [ 'gu_name' => $newname ];
		// Vanish requests need to remove user's email
		if ( $requestType === GlobalRenameRequest::VANISH ) {
			$setOptions[ 'gu_email' ] = '';
		}

		$dbw->startAtomic( __METHOD__ );
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( $setOptions )
			->where( [ 'gu_name' => $oldname ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'localuser' )
			->where( [ 'lu_name' => $oldname ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->endAtomic( __METHOD__ );
	}
}
