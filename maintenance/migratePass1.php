<?php
// pass 1:
// * generate 'globaluser' entries for each username
// * go through all usernames in 'localnames' and for those
// that can be automatically migrated, go ahead and do it.

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Timestamp\TimestampFormat;

class MigratePass1 extends Maintenance {

	protected float $start;

	protected int $migrated = 0;

	protected int $total = 0;

	protected string $fromPrefix = '';

	protected int $totalRowCount;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Migrates local users to global users where possible' );
	}

	public function execute() {
		$this->output( "CentralAuth migration pass 1:\n" );
		$this->output( "Finding accounts which can be migrated without interaction...\n" );

		$dbBackground = CentralAuthServices::getConnectionProvider()->getReplicaDatabase();

		$this->start = microtime( true );
		$result = $dbBackground->newSelectQueryBuilder()
			->select( 'ln_name' )
			->from( 'localnames' )
			->where( [ 'ln_wiki' => WikiMap::getCurrentWikiId() ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->totalRowCount = $result->numRows();
		foreach ( $result as $row ) {
			$this->fromPrefix = $row->ln_name;
			$central = new CentralAuthUser( $row->ln_name, IDBAccessObject::READ_LATEST );
			if ( $central->storeAndMigrate() ) {
				$this->migrated++;
			}
			if ( ++$this->total % 1000 === 0 ) {
				$this->migratePassOneReport();
			}
		}
		$this->migratePassOneReport();
		$this->output( "done.\n" );
	}

	private function migratePassOneReport() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf(
			"%s: processed %d of %d usernames (%.1f%%; %.1f/sec) , %d (%.1f%%) processed fully migrated (@ %s)\n",
			wfTimestamp( TimestampFormat::DB ),
			$this->total,
			$this->totalRowCount,
			$this->total / $this->totalRowCount * 100.0,
			$this->total / $delta,
			$this->migrated,
			$this->migrated / $this->total * 100.0,
			$this->fromPrefix
		) );
	}
}

// @codeCoverageIgnoreStart
$maintClass = MigratePass1::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
