<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\User\TempUser\DBSerialProvider;

class CentralAuthTempUserSerialProvider extends DBSerialProvider {

	private CentralAuthConnectionProvider $caConnectionProvider;

	/**
	 * @param array $config
	 * @param CentralAuthConnectionProvider $caConnectionProvider
	 */
	public function __construct( $config, CentralAuthConnectionProvider $caConnectionProvider ) {
		parent::__construct( $config );
		$this->caConnectionProvider = $caConnectionProvider;
	}

	/** @inheritDoc */
	protected function getDB() {
		return $this->caConnectionProvider->getPrimaryDatabase();
	}

	/** @inheritDoc */
	protected function getTableName() {
		return 'global_user_autocreate_serial';
	}
}
