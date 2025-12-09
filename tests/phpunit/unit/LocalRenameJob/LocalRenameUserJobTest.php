<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Tests\Unit\LocalRenameJob;

use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob
 * @copyright © 2018 Wikimedia Foundation and contributors
 */
class LocalRenameUserJobTest extends \PHPUnit\Framework\TestCase {

	protected ReflectionMethod $escapeReplacement;

	protected function setUp(): void {
		parent::setUp();
		$clazz = new ReflectionClass( LocalRenameUserJob::class );
		$this->escapeReplacement = $clazz->getMethod( 'escapeReplacement' );
	}

	/**
	 * @dataProvider provideEscapeReplacement
	 */
	public function testEscapeReplacement( $given, $expect ) {
		$escaped = $this->escapeReplacement->invokeArgs( null, [ $given ] );
		$this->assertSame(
			$expect,
			$escaped,
			'Replacement is escaped'
		);
	}

	public static function provideEscapeReplacement() {
		return [
			'$n' => [
				'Drytime%$1600',
				'Drytime%\\$1600'
			],
			'${n}' => [
				'That${1}',
				'That\\${1}'
			],
			'$' => [
				'End$',
				'End$'
			],
			'\\n' => [
				'This\\1',
				'This\\\\1'
			],
			'\\' => [
				'VeryU\\niqueAccount',
				'VeryU\\niqueAccount'
			],
		];
	}
}
