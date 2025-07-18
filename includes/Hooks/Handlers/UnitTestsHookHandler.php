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

use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Hook\UnitTestsBeforeDatabaseTeardownHook;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IMaintainableDatabase;

class UnitTestsHookHandler implements
	UnitTestsAfterDatabaseSetupHook,
	UnitTestsBeforeDatabaseTeardownHook
{

	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * UnitTestsBeforeDatabaseTeardown hook handler
	 * Cleans up tables created by onUnitTestsAfterDatabaseSetup() above
	 */
	public function onUnitTestsBeforeDatabaseTeardown() {
		$schema = json_decode(
			file_get_contents( __DIR__ . '/../../../schema/tables.json' ), true
		);
		$tables = array_map( static fn ( $tableSchema ) => $tableSchema['name'], $schema );

		$dbw = $this->loadBalancer->getMaintenanceConnectionRef( DB_PRIMARY );
		foreach ( $tables as $table ) {
			$dbw->dropTable( $table );
		}
	}

	/**
	 * UnitTestsAfterDatabaseSetup hook handler
	 *
	 * Set up the centralauth tables in the current DB, so we don't have
	 * to worry about rights on another database. The first time it's called
	 * we have to set the DB prefix ourselves, and reset it back to the original
	 * so that CloneDatabase will work. On subsequent runs, the prefix is already
	 * set up for us.
	 *
	 * @param IMaintainableDatabase $db
	 * @param string $prefix
	 */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		$originalPrefix = $db->tablePrefix();
		$db->tablePrefix( $prefix );
		if ( !$db->tableExists( 'globaluser', __METHOD__ ) ) {
			$engine = $db->getType();
			$db->sourceFile( __DIR__ . "/../../../schema/$engine/tables-generated.sql" );
		}
		$db->tablePrefix( $originalPrefix );
	}
}
