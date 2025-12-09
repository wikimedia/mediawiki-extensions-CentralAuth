<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use BatchRowIterator;
use Generator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;

/**
 * @author Taavi Väänänen
 */
class RecalculateGlobalEditCount extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Re-calculate the global edit count for matching users' );
		$this->setBatchSize( 500 );
		$this->addOption( 'wiki', 'Match all users with an account on this wiki', true, true );
	}

	public function execute() {
		$editCounter = CentralAuthServices::getEditCounter( $this->getServiceContainer() );
		$handled = 0;

		foreach ( $this->getUsers() as $name ) {
			$user = CentralAuthUser::getInstanceByName( $name );
			$oldCount = $editCounter->getCount( $user );
			$count = $editCounter->recalculate( $user );

			if ( $oldCount !== $count ) {
				$this->output( "Global edit count for '{$name}' updated from {$oldCount} to {$count}\n" );
			}

			$handled++;
			if ( $handled % $this->getBatchSize() === 0 ) {
				$this->output( "Processed {$handled} users so far ...\n" );
			}
		}

		$this->output( "Processed all {$handled} users!\n" );
	}

	/** @return Generator<string> */
	private function getUsers(): Generator {
		$wiki = $this->getOption( 'wiki' );
		if ( !$wiki ) {
			$this->fatalError( '--wiki must be specified' );
		}

		$caDbm = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() );
		$dbr = $caDbm->getCentralReplicaDB();

		$iterator = new BatchRowIterator(
			$dbr,
			$dbr->newSelectQueryBuilder()
				->select( 'lu_name' )
				->from( 'localuser' )
				->where( [ 'lu_wiki' => $wiki ] )
				->orderBy( 'lu_name' )
				->caller( __METHOD__ ),
			'lu_name',
			$this->getBatchSize(),
		);

		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				yield $row->lu_name;
			}
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = RecalculateGlobalEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
