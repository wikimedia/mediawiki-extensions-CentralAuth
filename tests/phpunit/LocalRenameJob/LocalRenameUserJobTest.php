<?php
/**
 * @section LICENSE
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

/**
 * @copyright © 2018 Wikimedia Foundation and contributors
 */
class LocalRenameUserJobTest extends PHPUnit\Framework\TestCase {

	/**
	 * @var ReflectionMethod
	 */
	protected $escapeReplacement;

	protected function setUp() {
		parent::setUp();
		$clazz = new ReflectionClass( LocalRenameUserJob::class );
		$this->escapeReplacement = $clazz->getMethod( 'escapeReplacement' );
		$this->escapeReplacement->setAccessible( true );
	}

	/**
	 * @dataProvider provideEscapeReplacement
	 * @covers LocalRenameUserJob::escapeReplacement
	 */
	public function testEscapeReplacement( $given, $expect ) {
		$escaped = $this->escapeReplacement->invokeArgs( null, [ $given ] );
		$this->assertSame(
			$expect, $escaped, 'Replacement is escaped' );
		$this->assertSame(
			$given, preg_replace( '/^.*$/', $escaped, $given ),
			'Roundtrip is clean' );
	}

	public function provideEscapeReplacement() {
		return [
			'$n' => [ 'Drytime%$1600', 'Drytime%\\$1600' ],
			'${n}' => [ 'That${1}', 'That\\${1}' ],
			'$' => [ 'End$', 'End$' ],
			'\\n' => [ 'This\\1', 'This\\\\1' ],
			'\\' => [ 'VeryU\\niqueAccount', 'VeryU\\niqueAccount' ],
		];
	}
}
