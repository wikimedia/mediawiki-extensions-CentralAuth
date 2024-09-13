<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;

/**
 * @covers MediaWiki\Extension\CentralAuth\CentralAuthWikiListService
 */
class CentralAuthWikiListServiceTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideConfigValues
	 */
	public function testGetWikiListFromConfig( array $configValue ) {
		$wikiListService = new CentralAuthWikiListService(
			new ServiceOptions(
				CentralAuthWikiListService::CONSTRUCTOR_OPTIONS,
				[ 'LocalDatabases' => $configValue ]
			),
			$this->createHookContainer()
		);

		$this->assertArrayEquals( $configValue, $wikiListService->getWikiList() );
	}

	public static function provideConfigValues(): array {
		return [
			'Empty' => [ [] ],
			'With values' => [ [ 'foowiki', 'barwiki' ] ],
		];
	}

	/**
	 * @dataProvider provideHooks
	 */
	public function testGetWikiListFromHook( array $hooks, array $expected ) {
		$hookContainer = $this->createHookContainer();

		foreach ( $hooks as $hook ) {
			$hookContainer->register( 'CentralAuthWikiList', $hook );
		}

		$wikiListService = new CentralAuthWikiListService(
			new ServiceOptions(
				CentralAuthWikiListService::CONSTRUCTOR_OPTIONS,
				[ 'LocalDatabases' => [ 'configuredwiki' ] ]
			),
			$hookContainer
		);

		$this->assertArrayEquals( $expected, $wikiListService->getWikiList() );
	}

	public static function provideHooks() {
		return [
			'One hook' => [
				[
					static function ( ?array &$wikiList ) {
						$wikiList = [ 'barwiki' ];
					}
				],
				[ 'barwiki' ],
			],
			'Multiple hooks, with aborting' => [
				[
					static function ( ?array &$wikiList ) {
						$wikiList = [ 'foowiki' ];
						return true;
					},
					static function ( ?array &$wikiList ) {
						$wikiList = [ 'barwiki' ];
						return false;
					},
					static function ( ?array &$wikiList ) {
						$wikiList = [ 'bazwiki' ];
					},
				],
				[ 'barwiki' ],
			],
			'Multiple hooks, with aborting before setting a value' => [
				[
					static function ( ?array &$wikiList ) {
						return false;
					},
					static function ( ?array &$wikiList ) {
						$wikiList = [ 'bazwiki' ];
					},
				],
				[ 'configuredwiki' ],
			],
			'No hooks' => [
				[],
				[ 'configuredwiki' ],
			]
		];
	}
}
