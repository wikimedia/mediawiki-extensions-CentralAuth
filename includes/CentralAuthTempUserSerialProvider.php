<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\User\TempUser\DBSerialProvider;

class CentralAuthTempUserSerialProvider extends DBSerialProvider {

	public function __construct(
		array $config,
		private readonly CentralAuthConnectionProvider $caConnectionProvider,
	) {
		parent::__construct( $config );
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
