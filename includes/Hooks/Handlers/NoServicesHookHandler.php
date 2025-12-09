<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\Maintenance\MigrateGuSalt;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * This handler is used in hooks which are outside the normal dependency injection scope.
 * It must not have any service dependencies.
 */
class NoServicesHookHandler implements
	LoadExtensionSchemaUpdatesHook
{

	/**
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$baseDir = dirname( __DIR__, 3 );
		$dbType = $updater->getDB()->getType();

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralauth',
			'addTable',
			'globaluser',
			"$baseDir/schema/$dbType/tables-generated.sql",
			true
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralauth',
			'runMaintenance',
			MigrateGuSalt::class,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralauth',
			'addField',
			'renameuser_queue',
			'rq_type',
			"$baseDir/schema/$dbType/patch-rq_type.sql",
			true
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralauth',
			'dropField',
			'globaluser',
			'gu_salt',
			"$baseDir/schema/$dbType/patch-drop-gu_salt.sql",
			true
		] );
	}

}
