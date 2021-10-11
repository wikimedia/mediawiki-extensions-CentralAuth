<?php

use MediaWiki\Extension\CentralAuth\Hooks\Handlers\UnitTestsHookHandler;

/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthUsingDatabaseTestCase extends MediaWikiIntegrationTestCase {
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			UnitTestsHookHandler::CENTRALAUTH_TABLES
		);
		parent::__construct( $name, $data, $dataName );
	}
}
