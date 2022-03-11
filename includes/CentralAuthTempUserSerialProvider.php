<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\User\TempUser\DBSerialProvider;

class CentralAuthTempUserSerialProvider extends DBSerialProvider {
	/** @var CentralAuthDatabaseManager */
	private $dbManager;

	public function __construct( $config, CentralAuthDatabaseManager $dbManager ) {
		parent::__construct( $config );
		$this->dbManager = $dbManager;
	}

	protected function getDB() {
		return $this->dbManager->getCentralDB( DB_PRIMARY );
	}

	protected function getTableName() {
		return 'global_user_autocreate_serial';
	}
}
