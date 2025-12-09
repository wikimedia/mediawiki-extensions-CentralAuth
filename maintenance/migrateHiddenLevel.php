<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

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

/**
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class MigrateHiddenLevel extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getCentralPrimaryDB();

		$lastUser = $dbw->newSelectQueryBuilder()
			->select( 'MAX(gu_id)' )
			->from( 'globaluser' )
			->caller( __METHOD__ )
			->fetchField();

		for ( $min = 0; $min <= $lastUser; $min += $this->getBatchSize() ) {
			$max = $min + $this->getBatchSize();
			$this->output( "Now processing global users with id between $min and $max...\n" );

			$dbw->newUpdateQueryBuilder()
				->update( 'globaluser' )
				->set( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_LISTS ] )
				->where( [
					'gu_hidden' => 'lists',
					$dbw->expr( 'gu_id', '>=', $min ),
					$dbw->expr( 'gu_id', '<=', $max ),
				] )
				->caller( __METHOD__ )
				->execute();

			$dbw->newUpdateQueryBuilder()
				->update( 'globaluser' )
				->set( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED ] )
				->where( [
					'gu_hidden' => 'suppressed',
					$dbw->expr( 'gu_id', '>=', $min ),
					$dbw->expr( 'gu_id', '<=', $max ),
				] )
				->caller( __METHOD__ )
				->execute();

			$this->waitForReplication();
		}

		$this->output( "Done.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = MigrateHiddenLevel::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
