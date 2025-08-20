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

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Special;

use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Special\SpecialMergeAccount
 * @group Database
 */
class SpecialMergeAccountTest extends SpecialPageTestBase {

	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		// To avoid complexity related to the use of shared domain
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, false );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'MergeAccount' );
	}

	public function testViewForTemporaryAccountCausesLoginError() {
		$this->enableAutoCreateTempUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		[ $html ] = $this->executeSpecialPage( '', null, null, $tempUser );
		$this->assertStringContainsString( '(centralauth-merge-notlogged', $html );
	}

	public function testViewForUserWithoutCentralAuthMergeRight() {
		$this->setGroupPermissions( '*', 'centralauth-merge', false );
		[ $html ] = $this->executeSpecialPage();
		$this->assertStringContainsString( '(centralauth-merge-denied', $html );
	}
}
