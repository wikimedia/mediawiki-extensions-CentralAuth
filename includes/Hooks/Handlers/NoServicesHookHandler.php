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

use DatabaseUpdater;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MigrateGuSalt;

/**
 * This handler is used in hooks which are outside the normal dependency injection scope.
 * It must not have any service dependencies.
 */
class NoServicesHookHandler implements
	LoadExtensionSchemaUpdatesHook,
	MediaWikiServicesHook
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
			"$baseDir/maintenance/migrateGuSalt.php"
		] );
	}

	/**
	 * Compatibility fallback for virtual domain name mapping.
	 * @param MediaWikiServices $services
	 */
	public function onMediaWikiServices( $services ) {
		global $wgCentralAuthDatabase, $wgVirtualDomainsMapping;

		if ( !isset( $wgVirtualDomainsMapping['virtual-centralauth'] )
			&& isset( $wgCentralAuthDatabase )
		) {
			$wgVirtualDomainsMapping['virtual-centralauth'] = [ 'db' => $wgCentralAuthDatabase ];
		}
	}

}
