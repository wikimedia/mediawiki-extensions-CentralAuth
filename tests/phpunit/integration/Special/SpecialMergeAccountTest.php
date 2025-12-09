<?php
/**
 * @license GPL-2.0-or-later
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
