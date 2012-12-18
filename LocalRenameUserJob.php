<?php
// This is inserted into the job queue of each wiki which needs renaming done, and actually just calls RenameuserSQL::rename() which may start a job of it's own...
class CentralAuthLocalRenameUserJob extends Job {
	public function __construct( $title, $params ) {
		parent::__construct( 'startLocalRenaming', $title, $params );
	}

	public function run() {
		AutoLoader::autoload( 'SpecialRenameuser' );

		$rus = new RenameuserSQL(
			$this->params['from'],
			$this->params['to'],
			User::newFromName( $this->params['from'] )->getId()
		);

		if ( !$rus->rename() ) {
			return false;
		}

		return true;
	}
}


