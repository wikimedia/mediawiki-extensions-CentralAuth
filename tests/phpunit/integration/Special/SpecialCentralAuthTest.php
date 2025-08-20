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
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralAuth;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageTestBase;
use TestUserRegistry;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Special\SpecialCentralAuth
 * @group Database
 */
class SpecialCentralAuthTest extends SpecialPageTestBase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			// We need to set wgLocalDatabases for the CentralAuthWikiListService
			MainConfigNames::LocalDatabases => [ WikiMap::getCurrentWikiId() ],
			// Testing the GlobalBlocking integration requires using CentralAuth's central ID provider.
			MainConfigNames::CentralIdLookupProvider => 'CentralAuth',
			// To avoid complexity related to the use of shared domain
			CAMainConfigNames::CentralAuthEnableSul3 => false,
		] );

		// Add the current site to the SiteStore to allow it to appear in the attached local accounts list.
		$sitesTable = $this->getServiceContainer()->getSiteStore();
		$site = $sitesTable->getSite( WikiMap::getCurrentWikiId() ) ?? new MediaWikiSite();
		$site->setGlobalId( WikiMap::getCurrentWikiId() );
		// We need to set a page path, otherwise this is not considered a valid site. Use enwiki's path as a mock value.
		$site->setPath( MediaWikiSite::PATH_PAGE, "https://en.wikipedia.org/wiki/$1" );
		$sitesTable->saveSite( $site );
	}

	protected function newSpecialPage(): SpecialCentralAuth {
		return new SpecialCentralAuth(
			$this->getServiceContainer()->getCommentFormatter(),
			$this->getServiceContainer()->getConnectionProvider(),
			$this->getServiceContainer()->getNamespaceInfo(),
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
			$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, false, false );
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
	 * Calls DOMCompat::getElementById, expects that it returns a valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $id The ID to search for, excluding the "#" character
	 * @return string
	 */
	private function assertAndGetByElementId( string $html, string $id ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::getElementById( $specialPageDocument, $id );
		$this->assertNotNull( $element, "Could not find element with ID $id in $html" );
		return DOMCompat::getInnerHTML( $element );
	}

	/**
	 * Verifies that the list of local accounts (wiki lists) is present on the Special:CentralAuth page.
	 *
	 * @param string $html The HTML of the executed special page
	 * @param bool $userCanLock Whether the user has the centralauth-lock right.
	 */
	private function verifyStatusFormShownIfUserHasRightsToView(
		string $html, bool $userCanLock, bool $userCanSuppress
	) {
		if ( !$userCanLock ) {
			// Verify that the status form is not shown
			$this->assertStringNotContainsString( '(centralauth-admin-status', $html );
			return;
		}

		// Verify that the status form heading and intro is present
		$this->assertStringContainsString( '(centralauth-admin-status', $html );
		$this->assertStringContainsString( '(centralauth-admin-status-intro)', $html );
		// Check that the "locked" field and radio options are present
		$lockedField = $this->assertAndGetByElementId( $html, 'mw-centralauth-admin-status-locked' );
		$this->assertStringContainsString( '(centralauth-admin-status-locked-no', $lockedField );
		$this->assertStringContainsString( '(centralauth-admin-status-locked-yes', $lockedField );
		// Check that the "hidden" field and radio options are present
		$hiddenField = $this->assertAndGetByElementId( $html, 'mw-centralauth-admin-status-hidden' );
		$this->assertStringContainsString( '(centralauth-admin-status-hidden-no', $hiddenField );
		// The hidden-list and hidden-oversight options should be present only if the user has the suppress right
		if ( $userCanSuppress ) {
			$this->assertStringContainsString( '(centralauth-admin-status-hidden-list', $hiddenField );
			$this->assertStringContainsString( '(centralauth-admin-status-hidden-oversight', $hiddenField );
		} else {
			$this->assertStringNotContainsString( '(centralauth-admin-status-hidden-list', $hiddenField );
			$this->assertStringNotContainsString( '(centralauth-admin-status-hidden-oversight', $hiddenField );
		}
		// Check that the reason field is there
		$reasonDropdownField = $this->assertAndGetByElementId( $html, 'mw-centralauth-admin-reason' );
		$this->assertStringContainsString( '(centralauth-admin-status-reasons', $reasonDropdownField );
		$this->assertStringContainsString( '(centralauth-admin-reason-other-select', $reasonDropdownField );
	}

	/**
	 * Verifies that the info fieldset is present on the Special:CentralAuth page.
	 *
	 * @param string $html The HTML of the executed special page
	 */
	private function verifyDeleteAccountFormShownIfUserHasRightsToView( string $html, bool $userCanUnmerge ) {
		if ( !$userCanUnmerge ) {
			// Verify that the delete form is not shown if the user is missing the centralauth-unmerge right
			$this->assertStringNotContainsString( '(centralauth-admin-delete-title', $html );
			return;
		}
		// Verify the fieldset header is present if the user does have the centralauth-unmerge right
		$this->assertStringContainsString( '(centralauth-admin-delete-title', $html );
		// Verify that the expected form fields are present
		$deleteForm = $this->assertAndGetByElementId( $html, 'mw-centralauth-delete' );
		$this->assertStringContainsString( '(centralauth-admin-delete-description', $deleteForm );
		$this->assertStringContainsString( '(centralauth-admin-reason', $deleteForm );
		$this->assertStringContainsString( '(centralauth-admin-delete-button', $deleteForm );
	}

	/**
	 * @param string $targetUsername The target parameter value specified when loading Special:CentralAuth
	 * @param bool $userCanSuppress Whether the viewing authority has the centralauth-suppress right
	 * @param bool $userCanUnmerge Whether the viewing authority has the centralauth-unmerge right
	 * @param bool $userCanLock Whether the viewing authority has the centralauth-lock right
	 * @param WebRequest|null $request The request to use when loading the special page. Optional.
	 * @return string
	 */
	private function verifyForExistingGlobalAccount(
		string $targetUsername, bool $userCanSuppress, bool $userCanUnmerge, bool $userCanLock,
		?WebRequest $request = null
	): string {
		// Explicitly set the user's language as qqx as some messages look at the user's language and not the request
		// language.
		$this->setUserLang( 'qqx' );
		// Prevent the content langauge being used for the reason fields (so that we can assert against the
		// message key using the qqx language).
		$this->overrideConfigValue( MainConfigNames::ForceUIMsgAsContentMsg, [
			'centralauth-admin-status-reasons',
			'centralauth-admin-reason-other-select',
		] );
		// Generate the rights the authority loading the page should have
		$authorityRights = [];
		if ( $userCanSuppress ) {
			$authorityRights[] = 'centralauth-suppress';
		}
		if ( $userCanUnmerge ) {
			$authorityRights[] = 'centralauth-unmerge';
		}
		if ( $userCanLock ) {
			$authorityRights[] = 'centralauth-lock';
		}
		$authority = $this->mockRegisteredAuthorityWithPermissions( $authorityRights );
		// Execute the special page with the username specified via the target parameter
		$request = $request ?? new FauxRequest();
		$request->setVal( 'target', $targetUsername );
		[ $html ] = $this->executeSpecialPage( '', $request, null, $authority );
		// Verify that the fieldsets for the page are there
		$this->verifyUsernameFormPresent( $html, $userCanSuppress || $userCanUnmerge || $userCanLock );
		$this->verifyStatusFormShownIfUserHasRightsToView( $html, $userCanLock, $userCanSuppress );
		$this->verifyDeleteAccountFormShownIfUserHasRightsToView( $html, $userCanUnmerge );
		$this->verifyWikiListPresent( $html, $userCanUnmerge );
		$this->verifyInfoFieldsetPresent( $html );
		return $html;
	}

	private function getTestCentralAuthUser() {
		$targetUsername = 'GlobalTestUser' . TestUserRegistry::getNextId();
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '123' ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		return $targetUsername;
	}

	/** @dataProvider provideViewForExistingGlobalAccount */
	public function testViewForExistingGlobalAccount( $userCanSuppress, $userCanUnmerge, $userCanLock ) {
		$targetUsername = $this->getTestCentralAuthUser();
		// Return the HTML for the executed special page for tests which extend this to make further assertions.
		return $this->verifyForExistingGlobalAccount(
			$targetUsername, $userCanSuppress, $userCanUnmerge, $userCanLock
		);
	}

	public static function provideViewForExistingGlobalAccount() {
		return [
			'User cannot suppress, merge, or lock' => [ false, false, false ],
			'User can lock, but not merge or suppress' => [ false, false, true ],
			'User can merge, suppress, and lock' => [ true, true, true ],
		];
	}

	private function getRowInWikiListTable( string $html ): string {
		$wikiListHtml = $this->assertAndGetByElementId( $html, 'mw-centralauth-merged' );
		$tbody = DOMCompat::getElementsByTagName( DOMUtils::parseHTML( $wikiListHtml ), 'tbody' )[0];
		$rowTags = DOMCompat::getElementsByTagName( $tbody, 'tr' );
		$this->assertCount( 1, $rowTags, 'One row in the wiki list table was expected.' );
		return DOMCompat::getInnerHTML( $rowTags[0] );
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
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, false, false, false );
		// Verify that the temporary account is marked as expired, not blocked, and that the attached timestamp is
		// correct
		$this->assertStringContainsString( '(centralauth-admin-info-expired', $html );
		$this->assertStringContainsString( '(centralauth-admin-notblocked', $this->getRowInWikiListTable( $html ) );
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
		$html = $this->testViewForExistingGlobalAccount( false, false, false );
		// Verify that the custom field data was added to the info fieldset.
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$centralAuthInfoElement = DOMCompat::getElementById( $specialPageDocument, 'mw-centralauth-info' );
		$this->assertStringContainsString(
			'(centralauth-custom-info-field-for-test', $centralAuthInfoElement->textContent
		);
		$this->assertStringContainsString(
			'Custom Info Field Data', $centralAuthInfoElement->textContent
		);
		// Check that the unmerge checkbox is not present
		$rowInWikiList = $this->getRowInWikiListTable( $html );
		$checkboxElement = DOMCompat::querySelector( DOMUtils::parseHTML( $rowInWikiList ), "input[type=checkbox]" );
		$this->assertNull( $checkboxElement );
	}

	public function testViewForLocallyBlockedGlobalAccount() {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetLocalUser = $this->getServiceContainer()->getUserFactory()->newFromName( $targetUsername );
		// Give the local user a group, a local block, and an edit to test all the properties in the table.
		$this->getServiceContainer()->getUserGroupManager()->addUserToGroup( $targetLocalUser, 'sysop' );
		$status = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$targetUsername, $this->mockRegisteredUltimateAuthority(), 'indefinite', 'Test reason1234'
			)
			->placeBlock();
		$this->assertStatusGood( $status );
		$this->editPage(
			$this->getExistingTestPage(), 'testing1234', '', NS_MAIN, $targetLocalUser
		);
		// Other extensions may run code which causes a CentralAuthUser to create an instance cache with stale data.
		// Clear the cache to avoid test failures (T377714).
		CentralAuthServices::getUserCache()->clear();
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		// Verify that the user is marked as locally blocked, has the correct edit count, and is in the sysop group
		// Check that one row is present in the table
		$rowInWikiList = $this->getRowInWikiListTable( $html );
		$this->assertStringContainsString( '(centralauth-admin-blocked2-indef', $rowInWikiList );
		$this->assertStringContainsString( '(centralauth-foreign-contributions: 1, en.wikipedia.org', $rowInWikiList );
		$this->assertStringContainsString( 'Test reason1234', $rowInWikiList );
		$this->assertStringContainsString( 'sysop', $rowInWikiList );
		// Check that the merge method is correct
		$this->assertStringContainsString( "(centralauth-merge-method-primary)", $rowInWikiList );
		// Check that the unmerge checkbox is present
		$checkboxElement = DOMCompat::querySelector( DOMUtils::parseHTML( $rowInWikiList ), "input[type=checkbox]" );
		$this->assertNotNull( $checkboxElement );
		$this->assertSame( 'wpWikis[]', $checkboxElement->getAttribute( 'name' ) );
		$this->assertSame( WikiMap::getCurrentWikiId(), $checkboxElement->getAttribute( 'value' ) );
	}

	public function testViewForGlobalAccountWithUnattachedAccount() {
		$targetUsername = $this->getTestCentralAuthUser();
		// Unattach the local account from the global account for the test
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$targetUser->addLocalName( WikiMap::getCurrentWikiId() );
		$targetUser->adminUnattach( [ WikiMap::getCurrentWikiId() ] );
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		// Check that one row is present in the table
		$rowInWikiList = $this->getRowInWikiListTable( $html );
		$this->assertStringContainsString( '(centralauth-admin-unattached)', $rowInWikiList );
		// Check that the unmerge checkbox is not present, as the user is not attached
		$checkboxElement = DOMCompat::querySelector( DOMUtils::parseHTML( $rowInWikiList ), "input[type=checkbox]" );
		$this->assertNull( $checkboxElement );
		// Check that the "unattached" info item is present in the "Global account information" box (which indicates
		// at least one unattached account).
		$this->assertStringContainsString( '(centralauth-admin-info-unattached)', $html );
	}

	public function testViewForGloballyBlockedUserWithGlobalBlockLocallyDisabled() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$targetUsername = $this->getTestCentralAuthUser();
		// Globally block the target user and then disable it on the local wiki
		$globalBlockingServices = GlobalBlockingServices::wrap( $this->getServiceContainer() );
		$globalBlockingServices->getGlobalBlockManager()
			->block(
				$targetUsername, 'testing', 'indefinite',
				$this->getTestUser( [ 'steward' ] )->getUserIdentity()
			);
		$globalBlockingServices->getGlobalBlockLocalStatusManager()
			->locallyDisableBlock(
				$targetUsername, 'Test reason for local disable',
				$this->getTestSysop()->getUserIdentity()
			);
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		// Verify that the global block exempt table is present
		$globalBlockExemptFieldset = $this->assertAndGetByElementId(
			$html, 'mw-centralauth-globalblock-exempt-list'
		);
		$this->assertStringContainsString( '(centralauth-admin-globalblock-exempt-list', $globalBlockExemptFieldset );
		$this->assertStringContainsString(
			'(centralauth-admin-globalblock-exempt-list-reason-heading', $globalBlockExemptFieldset
		);
		$this->assertStringContainsString(
			'(centralauth-admin-globalblock-exempt-list-wiki-heading', $globalBlockExemptFieldset
		);
		// Check that there is one row in the global block exempt list and that it contains the correct data
		$tbody = DOMCompat::getElementsByTagName( DOMUtils::parseHTML( $globalBlockExemptFieldset ), 'tbody' )[0];
		$rowTags = DOMCompat::getElementsByTagName( $tbody, 'tr' );
		$this->assertCount( 1, $rowTags, 'One row in the global block exempt list table was expected.' );
		$rowHtml = DOMCompat::getInnerHTML( $rowTags[0] );
		$this->assertStringContainsString( '(centralauth-foreign-link', $rowHtml );
		$this->assertStringContainsString( 'Test reason for local disable', $rowHtml );
	}

	private function verifyForExistingGlobalAccountOnFormSubmission(
		string $targetUsername, array $requestData, bool $userCanSuppress, bool $userCanUnmerge, bool $userCanLock
	): string {
		// Add fields to the request and set that the request was posted
		$fauxRequest = new FauxRequest( $requestData, true );
		return $this->verifyForExistingGlobalAccount(
			$targetUsername, $userCanSuppress, $userCanUnmerge, $userCanLock, $fauxRequest
		);
	}

	/** @dataProvider provideAdminStatusFormSubmissionForUserWithoutNecessaryRights */
	public function testAdminStatusFormSubmissionForUserWithoutNecessaryRights(
		$userCanLock, $wpStatusHiddenValue, $expectedErrorMessageKey
	) {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$userStateBeforeExecution = $targetUser->getStateHash( true );
		$html = $this->verifyForExistingGlobalAccountOnFormSubmission(
			$targetUsername,
			[
				'wpReason-other' => 'test',
				'wpReason' => 'other',
				'wpMethod' => 'set-status',
				'wpStatusLocked' => 1,
				'wpStatusHidden' => $wpStatusHiddenValue,
				'wpUserState' => $userStateBeforeExecution,
			],
			false,
			true,
			$userCanLock
		);
		$this->assertStringContainsString( $expectedErrorMessageKey, $html );
		$this->assertSame(
			$targetUser->getStateHash( true ), $userStateBeforeExecution,
			"Form should have not submitted successfully"
		);
	}

	public static function provideAdminStatusFormSubmissionForUserWithoutNecessaryRights() {
		return [
			'User does not have rights to lock' => [
				false, CentralAuthUser::HIDDEN_LEVEL_NONE, '(centralauth-admin-bad-input',
			],
			'User does not have rights to suppress' => [
				true, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, '(centralauth-admin-not-authorized)',
			],
		];
	}

	public function testSetStatusFormForStateCheckMismatch() {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$userStateBeforeExecution = $targetUser->getStateHash( true );
		$html = $this->verifyForExistingGlobalAccountOnFormSubmission(
			$targetUsername,
			[
				'wpReason-other' => 'test',
				'wpReason' => 'other',
				'wpMethod' => 'set-status',
				'wpStatusLocked' => 1,
				'wpStatusHidden' => 0,
				'wpUserState' => 'abcdef',
			],
			false,
			true,
			true
		);
		$this->assertStringContainsString( '(centralauth-state-mismatch)', $html );
		$this->assertSame(
			$targetUser->getStateHash( true ), $userStateBeforeExecution,
			"Form should have not submitted successfully"
		);
	}

	private function commonSubmitStatusFormForSuccess(
		CentralAuthUser $targetCentralAuthUser, array $requestData
	): string {
		$userState = $targetCentralAuthUser->getStateHash( true );
		$html = $this->verifyForExistingGlobalAccountOnFormSubmission(
			$targetCentralAuthUser->getName(),
			$requestData,
			true,
			true,
			true
		);
		// The state hash should change if the form was submitted successfully.
		$this->assertNotSame(
			$targetCentralAuthUser->getStateHash( true ), $userState,
			"Form should have been submitted successfully"
		);
		return $html;
	}

	/**
	 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthUser
	 */
	public function testSetStatusFormForLockingUserThenUnlockingUser() {
		// Get our testing global account
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		// First use the special page to lock a user using the old names for the reason fields to test the B/C code.
		$htmlForLockSubmission = $this->commonSubmitStatusFormForSuccess( $targetUser, [
			'wpReason' => 'Locking user for test',
			'wpReasonList' => 'other',
			'wpMethod' => 'set-status',
			'wpStatusLocked' => 1,
			'wpUserState' => $targetUser->getStateHash( true ),
		] );
		// Assert that the "global account changes" fieldset is shown to the user. It is hidden if the request did not
		// actually perform any status changes.
		$this->assertStringContainsString( '(centralauth-admin-logsnippet', $htmlForLockSubmission );
		// Verify that user is actually locked, both by checking the special page information and the user itself
		$lockedInfoField = $this->assertAndGetByElementId( $htmlForLockSubmission, 'mw-centralauth-admin-info-locked' );
		$this->assertStringContainsString( '(centralauth-admin-yes)', $lockedInfoField );
		$targetUser->invalidateCache();
		$this->assertTrue( $targetUser->isLocked() );
		// Check that the reason used for the lock is as expected
		$this->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'logging' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( [ 'log_action' => 'setstatus' ] )
			->assertFieldValue( 'Locking user for test' );
		// Use the special page to unlock the user
		$htmlForUnlockSubmission = $this->commonSubmitStatusFormForSuccess( $targetUser, [
			'wpReason-other' => 'Unlocking user for test',
			'wpReason' => 'Testingabc',
			'wpMethod' => 'set-status',
			'wpStatusLocked' => 0,
			'wpUserState' => $targetUser->getStateHash( true ),
		] );
		// Verify that user is actually unlocked, both by checking the special page information and the user itself
		$this->assertNull( DOMCompat::getElementById(
			DOMUtils::parseHTML( $htmlForUnlockSubmission ), 'mw-centralauth-admin-info-locked'
		) );
		$targetUser->invalidateCache();
		$this->assertFalse( $targetUser->isLocked() );
		// Check that the reason used for the unlock is as expected
		$reasonForSecondLogEntry = $this->getDb()->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'logging' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( [ 'log_action' => 'setstatus' ] )
			->orderBy( 'log_id', SelectQueryBuilder::SORT_DESC )
			->limit( 1 )
			->fetchField();
		$this->assertSame( 'Testingabc: Unlocking user for test', $reasonForSecondLogEntry );
		// Check the structure of the log snippet is as expected (contains a log entry for locking and then
		// unlocking).
		$logSnippet = $this->assertAndGetByElementId( $htmlForUnlockSubmission, 'mw-centralauth-admin-logsnippet' );
		$this->assertStringContainsString( '(centralauth-admin-logsnippet', $logSnippet );
		$this->assertStringContainsString( 'Locking user for test', $logSnippet );
		$this->assertStringContainsString( 'Unlocking user for test', $logSnippet );
		$this->assertStringContainsString( $targetUsername, $logSnippet );
	}

	/**
	 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthUser
	 */
	public function testSetStatusFormForSuppressingUser() {
		// Get our testing global account
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		// Use the special page to suppress the account
		$htmlForLockSubmission = $this->commonSubmitStatusFormForSuccess( $targetUser, [
			'wpReason-other' => 'test',
			'wpReason' => 'other',
			'wpMethod' => 'set-status',
			'wpStatusLocked' => 0,
			'wpStatusHidden' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			'wpUserState' => $targetUser->getStateHash( true ),
		] );
		// Verify that user is actually suppressed, both by checking the special page information and the user itself
		$hiddenFieldInfo = $this->assertAndGetByElementId( $htmlForLockSubmission, 'mw-centralauth-admin-info-hidden' );
		$this->assertStringContainsString( '(centralauth-admin-hidden-oversight)', $hiddenFieldInfo );
		$targetUser->invalidateCache();
		$this->assertTrue( $targetUser->isSuppressed() );
		// Check that the reason used for the suppress is as expected
		$this->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'logging' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( [ 'log_action' => 'setstatus' ] )
			->assertFieldValue( 'test' );
	}

	public function testAccountDeleteForm() {
		// Get our testing global account
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$this->assertTrue( $targetUser->exists() );
		// Use the special page to delete the global account
		$authority = $this->mockRegisteredAuthorityWithPermissions( [ 'centralauth-unmerge' ] );
		// Execute the special page with the username specified via the target parameter
		$request = new FauxRequest( [
			'reason' => 'test',
			'wpMethod' => 'delete',
		], true );
		$request->setVal( 'target', $targetUsername );
		[ $html ] = $this->executeSpecialPage( '', $request, null, $authority );
		// Verify that global user has been deleted
		$this->assertStringContainsString( '(centralauth-admin-delete-success', $html );
		$this->assertStringContainsString( '(centralauth-admin-nonexistent', $html );
		$this->verifyUsernameFormPresent( $html, true );
		$targetUser->invalidateCache();
		$this->assertFalse( $targetUser->exists() );
		// Check that the reason used for the suppress is as expected
		$this->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'logging' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->where( [ 'log_action' => 'delete' ] )
			->assertFieldValue( 'test' );
	}

	/** @dataProvider provideWpMethodValues */
	public function testFormSubmissionWithInvalidEditToken( $method ) {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$userStateBeforeExecution = $targetUser->getStateHash( true );
		$html = $this->verifyForExistingGlobalAccountOnFormSubmission(
			$targetUsername,
			[
				// Add all the possible fields used by the forms, but get the wpEditToken incorrect
				'wpEditToken' => 'invalidtoken',
				'wpReason-other' => 'test',
				'reason' => 'test',
				'wpReason' => 'other',
				'wpMethod' => $method,
				'wpStatusLocked' => 1,
				'wpStatusHidden' => 0,
				'wpWikis' => [ 'enwiki', 'dewiki' ],
				'wpUserState' => 'abcdef',
			],
			false,
			true,
			true
		);
		$this->assertStringContainsString( '(centralauth-token-mismatch)', $html );
		$this->assertSame(
			$targetUser->getStateHash( true ), $userStateBeforeExecution,
			"Form should have not submitted successfully"
		);
	}

	public static function provideWpMethodValues() {
		return [
			'wpMethod is "set-status"' => [ 'set-status' ],
			'wpMethod is "delete"' => [ 'delete' ],
			'wpMethod is "unmerge"' => [ 'unmerge' ],
		];
	}

	public function testUnmergeForm() {
		// Get our testing global account, and check that it's attached to the local account.
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$targetUser->addLocalName( WikiMap::getCurrentWikiId() );
		$this->assertTrue( $targetUser->attachedOn( WikiMap::getCurrentWikiId() ) );
		// Use the special page to unmerge the local account from the global account
		$html = $this->verifyForExistingGlobalAccountOnFormSubmission(
			$targetUsername,
			[ 'wpMethod' => 'unmerge', 'wpWikis' => [ WikiMap::getCurrentWikiId() ] ],
			true,
			true,
			true
		);
		// Verify that local user has been unmerged from the global user
		$this->assertStringContainsString( '(centralauth-admin-unmerge-success', $html );
		$targetUser->invalidateCache();
		$this->assertFalse( $targetUser->attachedOn( WikiMap::getCurrentWikiId() ) );
		// Check the wiki list row says the local account is unattached
		$rowInWikiList = $this->getRowInWikiListTable( $html );
		$this->assertStringContainsString( '(centralauth-admin-unattached)', $rowInWikiList );
	}

	public function testLogExtractWhenGlobalBlockingLogsPresent() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		// Globally lock the target
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$this->assertStatusGood(
			$targetUser->adminLockHide( true, null, 'Test global lock', $context )
		);
		// Also globally block the target
		$globalBlockingServices = GlobalBlockingServices::wrap( $this->getServiceContainer() );
		$this->assertStatusGood( $globalBlockingServices->getGlobalBlockManager()->block(
			$targetUsername, 'Test global block', 'indefinite',
			$this->getTestUser( [ 'steward' ] )->getUserIdentity()
		) );
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		// Verify that that the log snippet is present and that is contains the log entries for the lock
		// and global block.
		$logSnippet = $this->assertAndGetByElementId( $html, 'mw-centralauth-admin-logsnippet' );
		$this->assertStringContainsString( '(centralauth-admin-logsnippet', $logSnippet );
		$this->assertStringContainsString( 'Test global block', $logSnippet );
		$this->assertStringContainsString( 'Test global lock', $logSnippet );
		$this->assertStringContainsString( $targetUsername, $logSnippet );
	}

	public function testLogExtractWithSuppressionLog() {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		// Globally lock the target with suppression enabled
		$context = RequestContext::getMain();
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$this->assertStatusGood( $targetUser->adminLockHide(
			true, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'Test global suppression', $context
		) );
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		// Verify that that the log snippet is present and contains the global suppression entry.
		$logSnippet = $this->assertAndGetByElementId( $html, 'mw-centralauth-admin-logsnippet' );
		$this->assertStringContainsString( 'Test global suppression', $logSnippet );
		$this->assertStringContainsString( $targetUsername, $logSnippet );
	}

	public function testLogExtractWithSuppressionLogWhenAuthorityLacksSuppressionLogRight() {
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		// Set the performing user to have all rights except the ability to see suppression logs
		$context = RequestContext::getMain();
		$context->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'suppressionlog' ] ) );

		// Globally lock the target with suppression enabled
		$this->assertStatusGood( $targetUser->adminLockHide(
			true, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'Test global suppression', $context
		) );

		// Verify that that the log snippet is not present and the global suppression log is
		// not present in the page.
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$logSnippet = DOMCompat::getElementById( $specialPageDocument, 'mw-centralauth-admin-logsnippet' );
		$this->assertNull(
			$logSnippet, 'Should not have found log snippet element as logs cannot be seen by the authority'
		);
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getOuterHTML( $element[0] );
	}

	public function testLogExtractMissingWhenUserIsLocked() {
		// Define the MediaWiki:Centralauth-admin-log-otherwiki page to override the associated message.
		$this->getServiceContainer()->getMessageCache()->enable();
		$this->editPage(
			Title::newFromText( 'centralauth-admin-log-otherwiki', NS_MEDIAWIKI ),
			'Test warning',
			'test',
			NS_MEDIAWIKI,
			$this->getTestSysop()->getAuthority()
		);

		// Globally lock the target using a method which does not create a log entry
		$targetUsername = $this->getTestCentralAuthUser();
		$targetUser = CentralAuthUser::getInstanceByName( $targetUsername );
		$this->assertStatusGood( $targetUser->adminLock() );

		// Load the special page for the target, and verify that the "centralauth-admin-log-otherwiki" message
		// is shown along with the associated target username in the message.
		RequestContext::getMain()->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$html = $this->verifyForExistingGlobalAccount( $targetUsername, true, true, true );
		$otherWikiLogsWarning = $this->assertAndGetByElementClass( $html, 'centralauth-admin-log-otherwiki' );
		$this->assertStringContainsString( "(centralauth-admin-log-otherwiki", $otherWikiLogsWarning );
		$this->assertStringContainsString( $targetUsername, $otherWikiLogsWarning );
	}
}
