<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\AntiSpoof\SpoofUser;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class CentralAuthSpoofUser extends SpoofUser {

	public function __construct(
		string $name,
		private readonly CentralAuthDatabaseManager $centralAuthDatabaseManager
	) {
		parent::__construct( $name );
	}

	protected function getDBReplica(): IReadableDatabase {
		return $this->centralAuthDatabaseManager->getCentralReplicaDB();
	}

	protected function getDBPrimary(): IDatabase {
		return $this->centralAuthDatabaseManager->getCentralPrimaryDB();
	}

	protected function getTableName(): string {
		return 'globaluser';
	}

	protected function getUserColumn(): string {
		return 'gu_name';
	}
}
