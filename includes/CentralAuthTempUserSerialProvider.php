<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\User\TempUser\DBSerialProvider;

class CentralAuthTempUserSerialProvider extends DBSerialProvider {

	/** @var CentralAuthDatabaseManager */
	private $dbManager;

	/**
	 * @param array $config
	 * @param CentralAuthDatabaseManager $dbManager
	 */
	public function __construct( $config, CentralAuthDatabaseManager $dbManager ) {
		parent::__construct( $config );
		$this->dbManager = $dbManager;
	}

	/** @inheritDoc */
	protected function getDB() {
		return $this->dbManager->getCentralPrimaryDB();
	}

	/** @inheritDoc */
	protected function getTableName() {
		return 'global_user_autocreate_serial';
	}
}
