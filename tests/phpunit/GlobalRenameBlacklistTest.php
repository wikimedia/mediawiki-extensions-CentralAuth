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
 * @author Martin Urbanec <martin.urbanec@wikimedia.cz>
 * @copyright © 2019 Wikimedia Foundation and contributors
 * @group Database
 */
class GlobalRenameBlacklistTest extends MediaWikiIntegrationTestCase {
	private $blacklist = null;

	protected function setUp() {
		parent::setUp();
		$this->editPage( 'Blacklist', 'BadUser' );
		$this->blacklist = new GlobalRenameBlacklist( Title::newFromText( 'Blacklist' ) );
	}

	/**
	 * @covers GlobalRenameBlacklist::checkUser
	 */
	public function testGoodUser() {
		$this->assertTrue(
			$this->blacklist->checkUser( User::newFromName( 'GoodUser' ) ),
			'GoodUser didn\'t pass GlobalRenameBlacklist'
		);
	}

	/**
	 * @covers GlobalRenameBlacklist::checkUser
	 */
	public function testBadUser() {
		$this->assertFalse(
			$this->blacklist->checkUser( User::newFromName( 'BadUser' ) ),
			'BadUser passed GlobalRenameBlacklist'
		);
	}
}
