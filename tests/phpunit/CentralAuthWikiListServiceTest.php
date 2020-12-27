<?php

use MediaWiki\Config\ServiceOptions;

class CentralAuthWikiListServiceTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideConfigValues
	 * @covers CentralAuthWikiListService::getWikiList
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

	public static function provideConfigValues() : array {
		return [
			'Empty' => [ [] ],
			'With values' => [ [ 'foowiki', 'barwiki' ] ],
		];
	}

	/**
	 * @dataProvider provideHooks
	 * @covers CentralAuthWikiListService::getWikiList
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
					function ( ?array &$wikiList ) {
						$wikiList = [ 'barwiki' ];
					}
				],
				[ 'barwiki' ],
			],
			'Multiple hooks, with aborting' => [
				[
					function ( ?array &$wikiList ) {
						$wikiList = [ 'foowiki' ];
						return true;
					},
					function ( ?array &$wikiList ) {
						$wikiList = [ 'barwiki' ];
						return false;
					},
					function ( ?array &$wikiList ) {
						$wikiList = [ 'bazwiki' ];
					},
				],
				[ 'barwiki' ],
			],
			'Multiple hooks, with aborting before setting a value' => [
				[
					function ( ?array &$wikiList ) {
						return false;
					},
					function ( ?array &$wikiList ) {
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
