<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class Fix72927_Jobs extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'oldname', 'Old name', true, true );
		$this->addOption( 'newname', 'New name', true, true );
		$this->addOption( 'renamer', 'Renamer', true, true );

	}

	public function execute() {
		$params = array(
			'from' => $this->getOption( 'oldname' ),
			'to' => $this->getOption( 'newname' ),
			'renamer' => $this->getOption( 'renamer' ),
			'movepages' => true,
			'suppressredirects' => true,
			'promotetoglobal' => false,
		);
		$wiki = wfWikiId();
		$this->output( "Processing {$params['from']} --> {$params['to']} @$wiki" );
		$title = Title::newFromText( 'Global rename job' ); // This isn't used anywhere!
		$job = new LocalRenameUserJob( $title, $params );
		$job->movePages();
		$this->output( "Queued jobs." );
	}
}

$maintClass = "Fix72927_Jobs";
require_once( RUN_MAINTENANCE_IF_MAIN );
