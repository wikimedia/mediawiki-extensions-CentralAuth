<?php

use MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\CentralAuthUsingDatabaseTrait;

/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthUsingDatabaseTestCase extends MediaWikiIntegrationTestCase {
	use CentralAuthUsingDatabaseTrait;

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$this->setupCentralAuthTables();
		parent::__construct( $name, $data, $dataName );
	}
}
