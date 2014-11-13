<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class Fix72927 extends Maintenance {
	private $wps;
	private $phase0 = "20141023184600";
	private $phase1 = "20141028180500";
	private $phase2 = "20141029185700";


	public function execute() {

		$dbr = wfGetDB( DB_SLAVE );
		$fix    = "20141102204100";
		$rows = $dbr->select(
			'logging',
			array( 'log_params', 'log_timestamp', 'log_user_text' ),
			array(
				'log_type' => 'gblrename',
				'log_action' => 'gblrename',
				'log_timestamp >' . $dbr->addQuotes( $this->phase0 ),
				'log_timestamp <' . $dbr->addQuotes( $fix ),
			)
		);
		foreach ( $rows as $row ) {
			$params = unserialize( $row->log_params );
			$newname = $params['5::newname'];
			$ca = new CentralAuthUser( $newname );
			$attached = $ca->listAttached();
			foreach ( $attached as $wiki ) {
				if ( $row->timestamp > $this->getPhase( $wiki ) ) {
					$this->printJobCommand(
						$wiki,
						$params['4::oldname'],
						$newname,
						$row->log_user_text
					);
				}
			}
		}
	}

	private function printJobCommand( $wiki, $oldname, $newname, $stew ) {
		$this->output( "mwscript fix72927_jobs.php --wiki=\"$wiki\" --oldname=\"$oldname\" --newname=\"$newname\" --renamer=\"$stew\"\n" );
	}

	public function getPhase( $wiki ) {
		if ( in_array( $wiki, array( 'mediawikiwiki', 'testwiki', 'test2wiki' ) ) ) {
			return $this->phase0;
		} elseif ( in_array( $wiki, $this->wikipedias()) ) {
			return $this->phase2;
		} else {
			return $this->phase1;
		}
	}

	private function wikipedias() {
		global $IP;
		if ( $this->wps === null ) {
			$fname = "$IP/../wikipedia.dblist";
			$this->wps = array_map( 'trim', explode( "\n", file_get_contents( $fname ) ) );
		}
		return $this->wps;
	}

}

$maintClass = "Fix72927";
require_once( RUN_MAINTENANCE_IF_MAIN );
