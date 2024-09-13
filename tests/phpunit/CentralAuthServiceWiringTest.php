<?php

use MediaWiki\MediaWikiServices;

/**
 * @coversNothing
 * @group Database
 */
class CentralAuthServiceWiringTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ) {
		MediaWikiServices::getInstance()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
