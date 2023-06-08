<?php

use MediaWiki\Extension\AntiSpoof\SpoofUser;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class CentralAuthSpoofUser extends SpoofUser {
	/**
	 * @return IReadableDatabase
	 */
	protected function getDBReplica() {
		return CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
	}

	/**
	 * @return IDatabase
	 */
	protected function getDBPrimary() {
		return CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
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
