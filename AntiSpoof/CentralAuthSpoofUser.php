<?php

use Wikimedia\Rdbms\IDatabase;

class CentralAuthSpoofUser extends SpoofUser {
	/**
	 * @return IDatabase
	 */
	protected function getDBReplica() {
		return CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
	}

	/**
	 * @return IDatabase
	 */
	protected function getDBPrimary() {
		return CentralAuthServices::getDatabaseManager()->getCentralDB( DB_PRIMARY );
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
