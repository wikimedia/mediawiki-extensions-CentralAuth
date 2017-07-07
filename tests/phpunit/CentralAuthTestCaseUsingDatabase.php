<?php

/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthTestCaseUsingDatabase extends MediaWikiTestCase {
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$this->tablesUsed = array_merge( $this->tablesUsed, CentralAuthHooks::$centralauthTables );
		parent::__construct( $name, $data, $dataName );
	}
}
