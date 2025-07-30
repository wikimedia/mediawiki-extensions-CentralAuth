<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * @coversNothing
 */
class CentralAuthSchemaTest extends AbstractSchemaTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkipped( 'Temporary disabled for gerrit 1174110' );
	}

	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../schema';
	}

	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../schema/abstractSchemaChanges/';
	}

	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../schema/mysql',
			'sqlite' => __DIR__ . '/../../../schema/sqlite',
			'postgres' => __DIR__ . '/../../../schema/postgres',
		];
	}
}
