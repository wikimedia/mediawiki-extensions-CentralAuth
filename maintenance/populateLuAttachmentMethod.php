<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Maintenance\LoggedUpdateOutcome;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Populate localuser.lu_attachment_method from localuser.lu_attached_method (T304015).
 */
class PopulateLuAttachmentMethod extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Populate the localuser.lu_attachment_method field from localuser.lu_attached_method'
		);
		$this->addOption( 'dry-run', 'Only count the rows which would be populated, without changing them' );
		$this->addOption( 'sleep', 'Sleep time (in seconds) between every batch. Default: 0', false, true );
		$this->setBatchSize( 200 );
		$this->requireExtension( 'CentralAuth' );
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return 'PopulateLuAttachmentMethod';
	}

	/** @inheritDoc */
	public function doDBUpdates(): LoggedUpdateOutcome {
		// Not using CentralAuthServices, as its services are unavailable when run by the installer
		$dbr = $this->getReplicaDB( 'virtual-centralauth' );
		$dbw = $this->getPrimaryDB( 'virtual-centralauth' );

		$dryRun = $this->hasOption( 'dry-run' );
		$sleep = (int)$this->getOption( 'sleep', 0 );
		$batchSize = $this->getBatchSize();
		$lastWiki = '';
		$lastName = '';
		$updated = 0;

		do {
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'lu_wiki', 'lu_name', 'lu_attached_method' ] )
				->from( 'localuser' )
				->where( [
					'lu_attachment_method' => null,
					$dbr->expr( 'lu_attached_method', '!=', null ),
				] )
				->orderBy( [ 'lu_wiki', 'lu_name' ] )
				->limit( $batchSize )
				->caller( __METHOD__ );

			if ( $lastWiki !== '' ) {
				// Start from where we left off in the last batch
				$queryBuilder->andWhere( $dbr->buildComparison( '>', [
					'lu_wiki' => $lastWiki,
					'lu_name' => $lastName,
				] ) );
			}

			$res = $queryBuilder->fetchResultSet();
			$numRows = $res->numRows();

			// Group by method and wiki so that each batch needs only a few update queries
			$namesByMethodAndWiki = [];
			foreach ( $res as $row ) {
				$lastWiki = $row->lu_wiki;
				$lastName = $row->lu_name;

				if ( !isset( CentralAuthUser::ATTACHMENT_METHOD_MAPPING[$row->lu_attached_method] ) ) {
					$this->output(
						"Unknown lu_attached_method \"$row->lu_attached_method\" for " .
						"\"$row->lu_name\" on $row->lu_wiki, skipping.\n"
					);
					continue;
				}

				$namesByMethodAndWiki[$row->lu_attached_method][$row->lu_wiki][] = $row->lu_name;
			}

			if ( $dryRun ) {
				foreach ( $namesByMethodAndWiki as $namesByWiki ) {
					foreach ( $namesByWiki as $names ) {
						$updated += count( $names );
					}
				}
			} else {
				$this->beginTransactionRound( __METHOD__ );

				foreach ( $namesByMethodAndWiki as $method => $namesByWiki ) {
					foreach ( $namesByWiki as $wiki => $names ) {
						$dbw->newUpdateQueryBuilder()
							->update( 'localuser' )
							->set( [ 'lu_attachment_method' => CentralAuthUser::ATTACHMENT_METHOD_MAPPING[$method] ] )
							->where( [
								'lu_wiki' => $wiki,
								'lu_name' => $names,
								'lu_attached_method' => $method,
								'lu_attachment_method' => null,
							] )
							->caller( __METHOD__ )
							->execute();
						$updated += $dbw->affectedRows();
					}
				}

				$this->commitTransactionRound( __METHOD__ );
			}

			if ( $numRows ) {
				$this->output( ( $dryRun ? 'Would update' : 'Updated' ) .
					" $updated rows. Last row: \"$lastName\" on $lastWiki.\n" );
			}

			if ( $sleep > 0 ) {
				sleep( $sleep );
			}
		} while ( $numRows >= $batchSize );

		if ( $dryRun ) {
			$this->output( "Dry run done, would populate lu_attachment_method for $updated rows.\n" );
			return LoggedUpdateOutcome::SIMULATED;
		}

		$this->output( "Done, populated lu_attachment_method for $updated rows.\n" );
		return LoggedUpdateOutcome::COMPLETE;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateLuAttachmentMethod::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
