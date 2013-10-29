<?php

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends Job {

	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalRenameUserJob', $title, $params, $id );
	}

	public function run() {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			throw new MWException( 'Extension:Renameuser is not installed' );
		}
		$this->updateStatus( 'inprogress' );
		$from = $this->params['from'];
		$to = $this->params['to'];
		$oldUser = User::newFromName( $from );
		//$contribs = User::newFromName( $from )->getEditCount();
		$rename = new RenameuserSQL(
			$from,
			$to,
			$oldUser->getId(),
			array( 'checkIfUserExists' => false )
		);
		if ( !$rename->rename() ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'Rename failed...?' );
		}
		// FIXME: add logging here maybe?

		$this->done();
		return true;
	}

	public function done() {
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->delete(
			'renameuser_status',
			array( 'oldname' => $this->params['from'], 'wiki' => wfWikiID() ),
			__METHOD__
		);
	}

	public function updateStatus( $status ) {
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->update(
			'renameuser_status',
			array( 'status' => $status ),
			array( 'oldname' => $this->params['from'], 'wiki' => wfWikiID() ),
			__METHOD__
		);
	}
}
