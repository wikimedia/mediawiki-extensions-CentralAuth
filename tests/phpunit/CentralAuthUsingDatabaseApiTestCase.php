<?php

/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthUsingDatabaseApiTestCase extends ApiTestCase {
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			CentralAuthHooks::$centralauthTables,
			[ 'user' ]
		);
		parent::__construct( $name, $data, $dataName );
	}
}
