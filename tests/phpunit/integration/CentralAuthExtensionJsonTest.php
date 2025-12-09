<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @coversNothing
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class CentralAuthExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected static string $extensionJsonPath = __DIR__ . '/../../../extension.json';

	/** @inheritDoc */
	protected ?string $serviceNamePrefix = 'CentralAuth.';

	public static function provideHookHandlerNames(): iterable {
		$registry = ExtensionRegistry::getInstance();
		foreach ( self::getExtensionJson()['HookHandlers'] ?? [] as $hookHandlerName => $specification ) {
			if ( $hookHandlerName === 'abusefilter' && !$registry->isLoaded( 'Abuse Filter' ) ) {
				continue;
			}
			if ( $hookHandlerName === 'securepoll' && !$registry->isLoaded( 'SecurePoll' ) ) {
				continue;
			}
			yield [ $hookHandlerName ];
		}
	}
}
