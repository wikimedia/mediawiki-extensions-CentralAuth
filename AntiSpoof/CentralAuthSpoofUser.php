<?php

use Wikimedia\Rdbms\IDatabase;

class CentralAuthSpoofUser extends SpoofUser {
	/**
	 * @return IDatabase
	 */
	protected function getDBSlave() {
		return CentralAuthUtils::getCentralReplicaDB();
	}

	/**
	 * @return IDatabase
	 */
	protected function getDBMaster() {
		return CentralAuthUtils::getCentralDB();
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
