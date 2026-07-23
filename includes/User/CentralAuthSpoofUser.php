<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\AntiSpoof\SpoofUser;
use MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class CentralAuthSpoofUser extends SpoofUser {

	public function __construct(
		string $name,
		private readonly CentralAuthConnectionProvider $caConnectionProvider
	) {
		parent::__construct( $name );
	}

	protected function getDBReplica(): IReadableDatabase {
		return $this->caConnectionProvider->getReplicaDatabase();
	}

	protected function getDBPrimary(): IDatabase {
		return $this->caConnectionProvider->getPrimaryDatabase();
	}

	protected function getTableName(): string {
		return 'globaluser';
	}

	protected function getUserColumn(): string {
		return 'gu_name';
	}
}
