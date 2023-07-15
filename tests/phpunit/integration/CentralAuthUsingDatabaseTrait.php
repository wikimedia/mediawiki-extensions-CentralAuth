<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Extension\CentralAuth\Hooks\Handlers\UnitTestsHookHandler;

/**
 * Setup database tests for centralauth.
 */
trait CentralAuthUsingDatabaseTrait {
	/** @var string[] */
	protected $tablesUsed = [];

	protected function setupCentralAuthTables() {
		$this->tablesUsed = array_merge( $this->tablesUsed, UnitTestsHookHandler::CENTRALAUTH_TABLES );
	}
}
