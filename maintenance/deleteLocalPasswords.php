<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use DeleteLocalPasswords;
use Generator;
use LogicException;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/includes/DeleteLocalPasswords.php";

// @phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class CentralAuthDeleteLocalPasswords extends DeleteLocalPasswords {

	/** @var string|null Wiki to run on, or null for all. */
	protected $wiki;

	/** @var string|null The wiki being currently processed */
	protected $currentWiki;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Deletes local password for users with a central account.' );

		$this->addOption( 'wiki', 'If specified, only runs against local names from this wiki',
			false, true );
		$this->addOption( 'allwikis', 'If specified, checks all wikis. This is for the benefit '
			. "of frameworks which always add a 'wiki' parameter.", false, false );
	}

	protected function initialize() {
		parent::initialize();
		$wiki = $this->getOption( 'wiki', false );
		if ( $wiki !== false && !$this->getOption( 'allwikis' ) ) {
			$this->wiki = $wiki;
		}
	}

	/** @inheritDoc */
	protected function getUserBatches() {
		// since the keys on localnames are not conducive to batch operations and
		// because of the database shards, grab a list of the wikis and we will
		// iterate from there
		foreach ( $this->getWikis() as $wiki ) {
			$this->output( "Processing users for $wiki ...\n" );

			if ( !WikiMap::getWiki( $wiki ) ) {
				$this->error( "$wiki does not exist, use checkLocalUser.php to delete invalid records\n" );
				continue;
			}

			$this->currentWiki = $wiki;
			foreach ( $this->getUsers( $wiki ) as $users ) {
				yield $users;
			}
		}
	}

	/** @inheritDoc */
	protected function getUserDB() {
		if ( $this->currentWiki === null ) {
			throw new LogicException( 'Tried to get wiki DB before wiki was selected' );
		}
		return $this->getServiceContainer()->getDBLoadBalancerFactory()
			->getMainLB( $this->currentWiki )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->currentWiki );
	}

	/**
	 * @return array|null[]|string[]
	 */
	protected function getWikis() {
		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		if ( $this->wiki !== null ) {
			return [ $this->wiki ];
		} else {
			$conds = [];
			if ( $this->user !== null ) {
				$conds['lu_name'] = $this->user;
			}
			return $centralReplica->newSelectQueryBuilder()
				->select( 'lu_wiki' )
				->distinct()
				->from( 'localuser' )
				->where( $conds )
				->orderBy( 'lu_wiki', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}
	}

	/**
	 * @param string $wiki
	 *
	 * @return Generator
	 */
	protected function getUsers( $wiki ) {
		if ( $this->user !== null ) {
			$this->output( "\t ... querying '$this->user'\n" );
			yield [ $this->user ];
			return;
		}

		$centralReplica = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$lastUsername = '';
		do {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$users = $centralReplica->newSelectQueryBuilder()
				->select( 'lu_name' )
				->from( 'localuser' )
				->where( [
					'lu_wiki' => $wiki,
					$centralReplica->expr( 'lu_name', '>', $lastUsername ),
				] )
				->orderBy( 'lu_name', SelectQueryBuilder::SORT_ASC )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();

			if ( $users ) {
				yield $users;
				$lastUsername = end( $users );
			}
		} while ( count( $users ) === $this->getBatchSize() );
	}
}

$maintClass = CentralAuthDeleteLocalPasswords::class;
require_once RUN_MAINTENANCE_IF_MAIN;
