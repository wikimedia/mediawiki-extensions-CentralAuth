<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\AntiSpoof\SpoofUser;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class CentralAuthSpoofUser extends SpoofUser {
	private CentralAuthDatabaseManager $centralAuthDatabaseManager;

	/**
	 * @param string $name
	 * @param CentralAuthDatabaseManager $centralAuthDatabaseManager
	 */
	public function __construct( string $name, CentralAuthDatabaseManager $centralAuthDatabaseManager ) {
		parent::__construct( $name );
		$this->centralAuthDatabaseManager = $centralAuthDatabaseManager;
	}

	/**
	 * @return IReadableDatabase
	 */
	protected function getDBReplica() {
		return $this->centralAuthDatabaseManager->getCentralReplicaDB();
	}

	/**
	 * @return IDatabase
	 */
	protected function getDBPrimary() {
		return $this->centralAuthDatabaseManager->getCentralPrimaryDB();
	}

	/**
	 * @return string
	 */
	protected function getTableName() {
		return 'globaluser';
	}

	/**
	 * @return string
	 */
	protected function getUserColumn() {
		return 'gu_name';
	}
}
