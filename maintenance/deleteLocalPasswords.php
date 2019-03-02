<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/includes/DeleteLocalPasswords.php";

class CentralAuthDeleteLocalPasswords extends DeleteLocalPasswords {
	/** @var string|null Wiki to run on, or null for all. */
	protected $wiki;

	/** @var string|null The wiki being currently processed */
	protected $currentWiki;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->mDescription = "Deletes local password for users with a central account.";

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
		return wfGetDB( DB_MASTER, [], $this->currentWiki );
	}

	protected function getWikis() {
		$centralSlave = CentralAuthUtils::getCentralSlaveDB();
		$wikis = [];

		if ( !is_null( $this->wiki ) ) {
			$wikis[] = $this->wiki;
		} else {
			$conds = [];
			if ( !is_null( $this->user ) ) {
				$conds['lu_name'] = $this->user;
			}
			$result = $centralSlave->select(
				'localuser',
				[ 'lu_wiki' ],
				$conds,
				__METHOD__,
				[
					"DISTINCT",
					"ORDER BY" => "lu_wiki ASC"
				]
			);

			foreach ( $result as $row ) {
				$wikis[] = $row->lu_wiki;
			}
		}

		return $wikis;
	}

	protected function getUsers( $wiki ) {
		if ( !is_null( $this->user ) ) {
			$this->output( "\t ... querying '$this->user'\n" );
			yield $this->user;
			return;
		}

		$centralSlave = CentralAuthUtils::getCentralSlaveDB();
		$lastUsername = '';
		do {
			$this->output( "\t ... querying from '$lastUsername'\n" );
			$users = $centralSlave->selectFieldValues(
				'localuser',
				'lu_name',
				[
					'lu_wiki' => $wiki,
					'lu_name > ' . $centralSlave->addQuotes( $lastUsername ),
				],
				__METHOD__,
				[
					"LIMIT" => $this->getBatchSize(),
					"ORDER BY" => "lu_name ASC"
				]
			);
			if ( $users ) {
				yield $users;
				$lastUsername = end( $users );
			}
		} while ( count( $users ) === $this->getBatchSize() );
	}
}

$maintClass = CentralAuthDeleteLocalPasswords::class;
require_once RUN_MAINTENANCE_IF_MAIN;
