<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\Maintenance\MigrateGuSalt;
use MediaWiki\Extension\CentralAuth\Maintenance\MigrateInitialAccounts;
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

		if ( defined( 'MEDIAWIKI_INSTALL' ) ) {
			// Globalize any existing accounts during installation, but not updates (T358985)
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralauth',
				'runMaintenance',
				MigrateInitialAccounts::class,
			] );
		}
	}

}
