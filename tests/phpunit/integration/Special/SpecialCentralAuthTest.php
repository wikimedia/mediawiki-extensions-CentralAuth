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

use CentralAuthTestUser;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralAuth;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageTestBase;
use TestUserRegistry;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Special\SpecialCentralAuth
 * @group Database
 */
class SpecialCentralAuthTest extends SpecialPageTestBase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	protected function newSpecialPage(): SpecialCentralAuth {
		return new SpecialCentralAuth(
			$this->getServiceContainer()->getCommentFormatter(),
			$this->getServiceContainer()->getNamespaceInfo(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getUserNameUtils(),
			$this->getServiceContainer()->getUserRegistrationLookup(),
			CentralAuthServices::getDatabaseManager( $this->getServiceContainer() ),
			CentralAuthServices::getUIService( $this->getServiceContainer() ),
			CentralAuthServices::getGlobalRenameFactory( $this->getServiceContainer() )
		);
	}

	/**
	 * Verifies that the given HTML contains the username form for Special:CentralAuth.
	 *
	 * @param string $html The HTML of the executed special page
	 * @param bool $userCanEdit Whether the user can unmerge, suppress, or lock users. This is also the expected value
	 *   of SpecialCentralAuth::mCanEdit.
	 */
	private function verifyUsernameFormPresent( string $html, bool $userCanEdit ) {
		$this->assertStringContainsString( '(centralauth-admin-username', $html );
		if ( $userCanEdit ) {
			$expectedSubmitMessageKey = '(centralauth-admin-lookup-rw';
			$expectedUsernameFormLegendMessageKey = '(centralauth-admin-manage';
		} else {
			$expectedSubmitMessageKey = '(centralauth-admin-lookup-ro';
			$expectedUsernameFormLegendMessageKey = '(centralauth-admin-view';
		}
		$this->assertStringContainsString( $expectedSubmitMessageKey, $html );
		$this->assertStringContainsString( $expectedUsernameFormLegendMessageKey, $html );
	}

	/** @dataProvider provideUserCanLock */
	public function testViewWithNoUsernameEntered( $userCanLock ) {
		if ( $userCanLock ) {
			$authority = $this->mockRegisteredAuthorityWithPermissions( [ 'centralauth-lock' ] );
		} else {
			$authority = $this->mockAnonNullAuthority();
		}
		// Execute the special page without a target provided
		[ $html ] = $this->executeSpecialPage( '', null, null, $authority );
		// Verify that the form to enter a username is shown to the user
		$this->verifyUsernameFormPresent( $html, $userCanLock );
	}

	public static function provideUserCanLock() {
		return [
			'User has rights to lock accounts' => [ true ],
			'User does not have rights to lock accounts' => [ false ],
		];
	}

	/** @dataProvider provideViewForNonExistingAccount */
	public function testViewForNonExistingAccount( $username ) {
		// Execute the special page with a non-existing username specified via the subpage
		[ $html ] = $this->executeSpecialPage( $username );
		// Verify that the error message is shown to the user
		$this->assertStringContainsString( '(centralauth-admin-nonexistent', $html );
		// Verify that the form to enter a username is shown to the user
		$this->assertStringContainsString( '(centralauth-admin-username', $html );
		$this->assertStringContainsString( '(centralauth-admin-lookup-ro', $html );
		$this->assertStringContainsString( '(centralauth-admin-view', $html );
	}

	public static function provideViewForNonExistingAccount() {
		return [
			'No such global account' => [ 'NonExistingTestAccount1234' ],
			'Invalid username' => [ 'Template:Test#test' ],
		];
	}

	/** @dataProvider provideUserCanSuppress */
	public function testViewForSuppressedAccount( $hasSuppressRight ) {
		// Create a test CentralAuth user which is suppressed.
		$targetUsername = 'GloballyHiddenUser' . TestUserRegistry::getNextId();
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '3003', 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		if ( $hasSuppressRight ) {
			$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, false );
			// Verify that Special:CentralAuth says the account is suppressed
			$this->assertStringContainsString( '(centralauth-admin-hidden-oversight', $html );
		} else {
			// Unless the user viewing the page has the rights to see suppressed users, it should appear as if the
			// central user does not exist.
			$this->testViewForNonExistingAccount( $targetUsername );
		}
	}

	public static function provideUserCanSuppress() {
		return [
			'User has rights to suppress users' => [ true ],
			'User does not have rights to suppress users' => [ false ],
		];
	}

	/**
	 * Verifies that the list of local accounts (wiki lists) is present on the Special:CentralAuth page.
	 *
	 * @param string $html The HTML of the executed special page
	 * @param bool $userCanUnmerge Whether the user has the centralauth-unmerge right.
	 */
	private function verifyWikiListPresent( string $html, bool $userCanUnmerge ) {
		if ( $userCanUnmerge ) {
			$expectedWikiListsLegendMessageKey = '(centralauth-admin-list-legend-rw';
		} else {
			$expectedWikiListsLegendMessageKey = '(centralauth-admin-list-legend-ro';
		}
		$this->assertStringContainsString( $expectedWikiListsLegendMessageKey, $html );
		// Verify that the form headings are present
		$this->assertStringContainsString( '(centralauth-admin-list-localwiki', $html );
		$this->assertStringContainsString( '(centralauth-admin-list-attached-on', $html );
		$this->assertStringContainsString( '(centralauth-admin-list-method', $html );
		$this->assertStringContainsString( '(centralauth-admin-list-blocked', $html );
		$this->assertStringContainsString( '(centralauth-admin-list-editcount', $html );
		$this->assertStringContainsString( '(centralauth-admin-list-groups', $html );
		if ( $userCanUnmerge ) {
			$this->assertStringContainsString( '(centralauth-admin-unmerge', $html );
		} else {
			$this->assertStringNotContainsString( '(centralauth-admin-unmerge', $html );
		}
	}

	/**
	 * Verifies that the info fieldset is present on the Special:CentralAuth page.
	 *
	 * @param string $html The HTML of the executed special page
	 */
	private function verifyInfoFieldsetPresent( string $html ) {
		// Verify the fieldset header is present
		$this->assertStringContainsString( '(centralauth-admin-info-header', $html );
		// Verify that the expected list items are present
		$this->assertStringContainsString( '(centralauth-admin-info-username', $html );
		$this->assertStringContainsString( '(centralauth-admin-info-registered', $html );
		$this->assertStringContainsString( '(centralauth-admin-info-editcount', $html );
		$this->assertStringContainsString( '(centralauth-admin-info-attached', $html );
	}

	/**
	 * @param string $targetUsername The target parameter value specified when loading Special:CentralAuth
	 * @param bool $userCanSuppress Whether the viewing authority has the centralauth-suppress right
	 * @param bool $userCanMerge Whether the viewing authority has the centralauth-unmerge right
	 * @return string
	 */
	private function verifyForExistingGlobalAccount(
		string $targetUsername, bool $userCanSuppress, bool $userCanMerge
	): string {
		$authorityRights = [];
		if ( $userCanSuppress ) {
			$authorityRights[] = 'centralauth-suppress';
		}
		if ( $userCanMerge ) {
			$authorityRights[] = 'centralauth-unmerge';
		}
		$authority = $this->mockRegisteredAuthorityWithPermissions( $authorityRights );
		// Execute the special page with the username specified via the target parameter
		$fauxRequest = new FauxRequest();
		$fauxRequest->setVal( 'target', $targetUsername );
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $authority );
		// Verify that the username form is shown
		$this->verifyUsernameFormPresent( $html, $userCanSuppress || $userCanMerge );
		// Verify that the wiki list is present
		$this->verifyWikiListPresent( $html, $userCanMerge );
		// Verify that the info fieldset is present
		$this->verifyInfoFieldsetPresent( $html );
		return $html;
	}

	public function testViewForExistingGlobalAccount() {
		// Create a test CentralAuth user for the test if no username was provided to the method.
		$targetUsername = 'GlobalTestUser' . TestUserRegistry::getNextId();
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '123' ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		// Return the HTML for the executed special page for tests which extend this to make further assertions.
		return $this->verifyForExistingGlobalAccount( $targetUsername, false, false );
	}

	public function testViewForExistingGlobalTemporaryAccount() {
		ConvertibleTimestamp::setFakeTime( '20240505050505' );
		$this->enableAutoCreateTempUser( [ 'expireAfterDays' => 2 ] );
		// Create a test CentralAuth user which is a temporary account username
		$targetUsername = $this->getServiceContainer()->getTempUserCreator()
			->acquireAndStashName( RequestContext::getMain()->getRequest()->getSession() );
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '321', 'gu_registration' => '20240405060708' ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, false, false );
		// Verify that the temporary account is marked as expired.
		$this->assertStringContainsString( '(centralauth-admin-info-expired', $html );
	}

	public function testViewForExistingGlobalAccountWithCustomInfoFields() {
		// Set a custom hook handler for the CentralAuthInfoFields hook to test that items are correctly added by it.
		$this->setTemporaryHook(
			'CentralAuthInfoFields',
			function ( CentralAuthUser $centralAuthUser, IContextSource $context, &$attribs ) {
				$this->assertIsArray( $attribs );
				$attribs[] = [
					'label' => 'centralauth-custom-info-field-for-test',
					'data' => 'Custom Info Field Data',
				];
			}
		);
		$html = $this->testViewForExistingGlobalAccount();
		// Verify that the custom field data was added to the info fieldset.
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$centralAuthInfoElement = DOMCompat::getElementById( $specialPageDocument, 'mw-centralauth-info' );
		$this->assertStringContainsString(
			'(centralauth-custom-info-field-for-test', $centralAuthInfoElement->textContent
		);
		$this->assertStringContainsString(
			'Custom Info Field Data', $centralAuthInfoElement->textContent
		);
	}
}
