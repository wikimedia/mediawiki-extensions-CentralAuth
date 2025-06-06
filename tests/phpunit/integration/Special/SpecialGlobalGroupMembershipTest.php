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

use Generator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\Html;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\WikiMap\WikiMap;
use ReflectionClass;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership
 * @group Database
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class SpecialGlobalGroupMembershipTest extends SpecialPageTestBase {

	use TempUserTestTrait;
	use MockAuthorityTrait;
	use MockWikiMapTrait;

	protected function setUp(): void {
		parent::setUp();

		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralPrimaryDB();

		$caDbw->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'group-one', 'ggp_permission' => 'right-one' ],
				[ 'ggp_group' => 'group-two', 'ggp_permission' => 'right-two' ],
				[ 'ggp_group' => 'group-three', 'ggp_permission' => 'right-three' ],
				[ 'ggp_group' => 'group-auto-one', 'ggp_permission' => 'right-auto-one' ],
				[ 'ggp_group' => 'group-auto-two', 'ggp_permission' => 'right-auto-two' ],
				[ 'ggp_group' => 'group-auto-three', 'ggp_permission' => 'right-auto-three' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	protected function newSpecialPage(): SpecialGlobalGroupMembership {
		return new SpecialGlobalGroupMembership(
			$this->getServiceContainer()->getHookContainer(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserNamePrefixSearch(),
			$this->getServiceContainer()->getUserNameUtils(),
			CentralAuthServices::getAutomaticGlobalGroupManager( $this->getServiceContainer() ),
			CentralAuthServices::getGlobalGroupLookup( $this->getServiceContainer() )
		);
	}

	private function getRegisteredTestUser(): array {
		$testUser = $this->getMutableTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $testUser->getUser() );
		$caUser->register( $testUser->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		$caUser->addToGlobalGroup( 'group-two' );
		$caUser->addToGlobalGroup(
			'group-three',
			// Some time in the future
			time() + 1800
		);
		return [ $caUser, $testUser->getUser() ];
	}

	/**
	 * @dataProvider provideFetchUserForGoodStatus
	 */
	public function testFetchUserForGoodStatus( $inputFunction ) {
		[ $user, ] = $this->getRegisteredTestUser();
		$status = $this->newSpecialPage()->fetchUser( $inputFunction( $user ) );

		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertEquals( $user->getName(), $value->getName() );
		$this->assertEquals( $user->getId(), $value->getId() );
	}

	public static function provideFetchUserForGoodStatus(): Generator {
		yield 'Username' => [ static fn ( CentralAuthUser $user ) => $user->getName() ];
		yield 'Non-canonical username' => [ static fn ( CentralAuthUser $user ) => lcfirst( $user->getName() ) ];
		yield 'ID' => [ static fn ( CentralAuthUser $user ) => '#' . $user->getId() ];
	}

	/**
	 * @dataProvider provideFetchUserForFatalStatus
	 */
	public function testFetchUserForFatalStatus( string $input, string $error ) {
		$status = $this->newSpecialPage()->fetchUser( $input );
		$this->assertStatusError( $error, $status );
	}

	public static function provideFetchUserForFatalStatus() {
		yield 'Blank' => [ '', 'nouserspecified' ];
		yield 'Username' => [ 'Not in use', 'nosuchusershort' ];
		yield 'Invalid username' => [ 'Invalid@username', 'nosuchusershort' ];
		yield 'ID' => [ '#12345678', 'noname' ];
	}

	public function testFetchUserForTemporaryAccount() {
		$this->enableAutoCreateTempUser();
		$this->testFetchUserForFatalStatus(
			$this->getServiceContainer()->getTempUserCreator()
				->create( null, new FauxRequest() )->getUser()->getName(),
			'userrights-no-group'
		);
	}

	public function testViewSpecialPageForTemporaryAccountTarget() {
		$this->enableAutoCreateTempUser();
		$user = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		[ $html ] = $this->executeSpecialPage( $user->getName() );
		$this->assertStringContainsString( '(userrights-no-group)', $html );
	}

	public function testRenderFormForPrivilegedUser() {
		[ $user, ] = $this->getRegisteredTestUser();
		[ $html, ] = $this->executeSpecialPage(
			// test against a non-canonical username form (T344495)
			lcfirst( $user->getName() ),
			null,
			null,
			new UltimateAuthority( $this->getTestSysop()->getUser() )
		);

		$specialPage = $this->newSpecialPage();

		// Group one: not a member
		// TODO: would be cool to not test the raw HTML structure here
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" id="wpGroup-group-one" name="wpGroup-group-one">',
			$html
		);

		// Group two: indefinite member
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" checked="" id="wpGroup-group-two" name="wpGroup-group-two">',
			$html
		);
		$this->assertStringContainsString(
			Html::openElement(
				'select',
				[ 'name' => 'wpExpiry-group-two', 'id' => 'mw-input-wpExpiry-group-two' ]
			) . Html::openElement(
				'option',
				[ 'value' => 'infinite', 'selected' => '' ],
			),
			$html
		);

		// Group three: temporary member
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" checked="" id="wpGroup-group-three" name="wpGroup-group-three">',
			$html
		);
		$this->assertStringContainsString(
			Html::openElement(
				'select',
				[ 'name' => 'wpExpiry-group-three', 'id' => 'mw-input-wpExpiry-group-three' ]
			) . Html::openElement(
				'option',
				[ 'value' => 'existing', 'selected' => '' ],
			),
			$html
		);

		// No automatic groups
		$this->assertStringNotContainsString( '(userrights-unchangeable-col)', $html );
		$this->assertStringNotContainsString( '(centralauth-globalgroupperms-automatic-group-info)', $html );

		// Can save
		$this->assertStringContainsString(
			$specialPage->msg( 'userrights-changeable-col', 6 )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);
		$this->assertStringContainsString(
			$specialPage->msg( 'saveusergroups', $user->getName() )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);
	}

	public function testRenderFormForPrivilegedUserWithAutomaticGroups() {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'group-one' => [ 'group-auto-one' ],
			'group-two' => [ 'group-auto-two' ],
			'group-three' => [ 'group-auto-three' ],
		] );

		$expiry = time() + 86400;
		$testUser = $this->getMutableTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $testUser->getUser() );
		$caUser->register( $testUser->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		$caUser->addToGlobalGroup( 'group-two' );
		$caUser->addToGlobalGroup( 'group-auto-two' );

		// Manually add the user to the automatic groups, since we won't hit a code path that does this for us.
		$caUser->addToGlobalGroup( 'group-three', $expiry );
		$caUser->addToGlobalGroup( 'group-auto-three', $expiry );

		[ $html, ] = $this->executeSpecialPage(
			$caUser->getName(),
			null,
			null,
			new UltimateAuthority( $this->getTestSysop()->getUser() )
		);

		$specialPage = $this->newSpecialPage();

		// Checkboxes are divided into columns
		$this->assertStringContainsString(
			$specialPage->msg( 'userrights-changeable-col', 3 )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);
		$this->assertStringContainsString(
			$specialPage->msg( 'userrights-unchangeable-col', 3 )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);

		// Messages are added
		$this->assertStringContainsString( '(centralauth-globalgroupperms-automatic-group-info: (group-group-auto-one-member', $html );
		$this->assertStringContainsString( '(centralauth-globalgroupperms-automatic-group-info: (group-group-auto-two-member', $html );
		$this->assertStringContainsString( '(centralauth-globalgroupperms-automatic-group-info: (group-group-auto-three-member', $html );
		$this->assertStringContainsString( '(centralauth-globalgroupperms-automatic-group-reason)', $html );

		// Automatic group one: not a member
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" id="wpGroup-group-auto-one" name="wpGroup-group-auto-one" disabled="">',
			$html
		);

		// Automatic group two: indefinite member
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" checked="" id="wpGroup-group-auto-two" name="wpGroup-group-auto-two" disabled="">',
			$html
		);
		$this->assertStringContainsString(
			'<span>(userrights-expiry-none)</span>',
			$html
		);

		// Automatic group three: temporary member
		$this->assertStringContainsString(
			'<input class="mw-userrights-groupcheckbox" type="checkbox" value="1" checked="" id="wpGroup-group-auto-three" name="wpGroup-group-auto-three" disabled="">',
			$html
		);
		$this->assertStringContainsString(
			'<span>(userrights-expiry-current',
			$html
		);
	}

	public function testRenderFormReadOnly() {
		[ $user, ] = $this->getRegisteredTestUser();
		[ $html, ] = $this->executeSpecialPage(
			$user->getName(),
		);

		$specialPage = $this->newSpecialPage();

		// Can't edit or save
		$this->assertStringNotContainsString(
			$specialPage->msg( 'userrights-changeable-col', 3 )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);
		$this->assertStringNotContainsString(
			$specialPage->msg( 'saveusergroups', $user->getName() )
				->inLanguage( 'qqx' )
				->text(),
			$html
		);
	}

	public function testSaveForTemporaryAccount() {
		$this->enableAutoCreateTempUser();
		$user = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user );
		$caUser->register( 'SpecialPageTest@12345', null );
		$caUser->attach( WikiMap::getCurrentWikiId() );

		[ $html ] = $this->executeSpecialPage(
			$user->getName(),
			new FauxRequest(
				[
					'user' => $user->getName(),
					'saveusergroups' => '1',
					'wpEditToken' => SessionManager::getGlobalSession()->getToken( $user->getName() ),
					'conflictcheck-originalgroups' => '',
					'wpGroup-group-one' => '1',
					'wpExpiry-group-one' => 'infinite',
					'wpGroup-group-three' => '1',
					'wpExpiry-group-three' => '1 month',
					'user-reason' => 'test',
				],
				true
			),
			null,
			$this->mockRegisteredUltimateAuthority()
		);

		$this->assertStringContainsString( '(userrights-no-group)', $html );

		$caUser->invalidateCache();
		$this->assertArrayEquals( [], $caUser->getGlobalGroups() );

		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => 'gblrights' ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testSave() {
		[ $user, ] = $this->getRegisteredTestUser();
		$originalExpiry = $user->getGlobalGroupsWithExpiration()['group-three'];

		$hookWasRun = false;
		$this->clearHook( 'CentralAuthGlobalUserGroupMembershipChanged' );
		$this->setTemporaryHook(
			'CentralAuthGlobalUserGroupMembershipChanged',
			static function () use ( &$hookWasRun ) {
				$hookWasRun = true;
			}
		);

		// test against a non-canonical username form (T344495)
		$username = lcfirst( $user->getName() );
		$this->executeSpecialPage(
			$username,
			new FauxRequest(
				[
					'user' => $username,
					'saveusergroups' => '1',
					'wpEditToken' => SessionManager::getGlobalSession()->getToken( $username ),
					'conflictcheck-originalgroups' => 'group-three,group-two',
					'wpGroup-group-one' => '1',
					'wpExpiry-group-one' => 'infinite',
					'wpGroup-group-three' => '1',
					'wpExpiry-group-three' => '1 month',
					'user-reason' => 'test',
				],
				true
			),
			null,
			new UltimateAuthority( $this->getTestSysop()->getUser() )
		);

		$user->invalidateCache();

		$this->assertEquals( [ 'group-one', 'group-three' ], $user->getGlobalGroups() );
		$this->assertNotEquals( $originalExpiry, $user->getGlobalGroupsWithExpiration()['group-three'] );

		$rawLogData = $this->newSelectQueryBuilder()
			->select( [ 'log_params' ] )
			->from( 'logging' )
			->where( [
				'log_type' => 'gblrights',
				'log_action' => 'usergroups',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $user->getName() ),
			] )
			->fetchField();
		$logData = unserialize( $rawLogData );
		$this->assertEquals(
			[
				'oldGroups' => [ 'group-three', 'group-two' ],
				'newGroups' => [ 'group-one', 'group-three' ],
				'oldMetadata' => [
					[ 'expiry' => $originalExpiry ],
					[ 'expiry' => null ],
				],
				'newMetadata' => [
					[ 'expiry' => null ],
					[ 'expiry' => $user->getGlobalGroupsWithExpiration()['group-three'] ],
				],
			],
			$logData
		);

		$this->assertTrue( $hookWasRun, 'The CentralAuthGlobalUserGroupMembershipChanged hook should have run' );
	}

	/** @dataProvider provideSaveWithAutomaticGroup */
	public function testSaveWithAutomaticGroup( $localGroup, $expected ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'localgroup' => [ 'group-one' ],
			'group-two' => [ 'group-one' ],
		] );

		$this->mockWikiMap();

		[ $caUser, $user ] = $this->getRegisteredTestUser();
		$username = $user->getName();
		$performer = new UltimateAuthority( $this->getTestSysop()->getUser() );

		if ( $localGroup ) {
			$this->getServiceContainer()->getUserGroupManager()->addUserToGroup(
				$user,
				$localGroup
			);
		}

		$this->executeSpecialPage(
			$username,
			new FauxRequest(
				[
					'user' => $username,
					'saveusergroups' => '1',
					'wpEditToken' => SessionManager::getGlobalSession()->getToken( $username ),
					'conflictcheck-originalgroups' => 'group-three,group-two',
					'wpGroup-group-two' => '1',
					'wpExpiry-group-two' => 'infinite',
					'user-reason' => 'test',
				],
				true
			),
			null,
			$performer
		);

		$caUser->invalidateCache();

		// The automatic global group is added
		$this->assertEquals( [ 'group-one', 'group-two' ], $caUser->getGlobalGroups() );

		$this->executeSpecialPage(
			$username,
			new FauxRequest(
				[
					'user' => $username,
					'saveusergroups' => '1',
					'wpEditToken' => SessionManager::getGlobalSession()->getToken( $username ),
					'conflictcheck-originalgroups' => 'group-one,group-two',
					'wpGroup-group-one' => '1',
					'wpExpiry-group-one' => 'infinite',
					'user-reason' => 'test',
				],
				true
			),
			null,
			$performer
		);

		$caUser->invalidateCache();

		// The automatic global group is removed or not, depending on the local group
		$this->assertEquals( $expected, $caUser->getGlobalGroups() );
	}

	public static function provideSaveWithAutomaticGroup() {
		return [
			'Automatic global group is not removed if user has a local group' => [
				'localgroup',
				[ 'group-one' ]
			],
			'Automatic global group is removed if user has no other groups' => [
				null,
				[]
			],
		];
	}

	public function testAdjustForAutomaticGlobalGroups() {
		$localGroup = 'localGroup';
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			$localGroup => [ 'group-one' ],
		] );

		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$localGroupMembership = $this->createMock( UserGroupMembership::class );
		$localGroupMembership->method( 'getGroup' )
			->willReturn( $localGroup );
		$localGroupMembership->method( 'getExpiry' )
			->willReturn( '20230405060707' );

		$externalGroupMembership = $this->createMock( UserGroupMembership::class );
		$externalGroupMembership->method( 'getGroup' )
			->willReturn( $localGroup );
		$externalGroupMembership->method( 'getExpiry' )
			->willReturn( '20240405060707' );

		$user = $this->createMock( CentralAuthUser::class );
		$user->method( 'queryAttached' )
			->willReturn( [
				'localWiki' => [
					'groupMemberships' => [ $localGroupMembership ],
				],
				'externalWiki' => [
					'groupMemberships' => [ $externalGroupMembership ],
				]
			] );

		$assignedGroups = [];
		$add = [];
		$remove = [];
		$groupExpiries = [];
		// T287318 - TestingAccessWrapper::__call does not support pass-by-reference
		$classReflection = new ReflectionClass( $specialPage->object );
		$methodReflection = $classReflection->getMethod( 'adjustForAutomaticGlobalGroups' );
		$methodReflection->setAccessible( true );
		$methodReflection->invokeArgs( $specialPage->object, [
			$user, $assignedGroups, &$add, &$remove, &$groupExpiries,
		] );

		$this->assertEquals( [ 'group-one' ], $add );
		$this->assertEquals( [], $remove );
		$this->assertEquals( [ 'group-one' => '20240405060707' ], $groupExpiries );
	}

	/** @dataProvider provideGetLogReason */
	public function testGetLogReason( $expected, $reason, $added, $removed ) {
		$this->overrideConfigValue( 'CentralAuthAutomaticGlobalGroups', [
			'global-group-1' => [ 'automatic-group-1' ],
			'global-group-2' => [ 'automatic-group-2' ],
		] );

		$this->setUserLang( 'qqx' );
		$this->setContentLang( 'qqx' );

		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$this->assertSame(
			$expected,
			$specialPage->getLogReason( $reason, $added, $removed )
		);
	}

	public static function provideGetLogReason() {
		return [
			'No automatic groups are changed, reason is unchanged' => [
				'Test reason',
				'Test reason',
				[ 'global-group-1' ],
				[ 'global-group-2' ],
			],
			'Automatic groups are added, reason is updated' => [
				'(centralauth-automatic-global-groups-reason-global: Test reason)',
				'Test reason',
				[ 'automatic-group-1', 'automatic-group-2' ],
				[],
			],
			'Automatic groups are removed, reason is updated' => [
				'(centralauth-automatic-global-groups-reason-global: Test reason)',
				'Test reason',
				[],
				[ 'automatic-group-1', 'automatic-group-2' ],
			],
			'Automatic groups are added after local change, reason unchanged' => [
				'(centralauth-automatic-global-groups-reason-local)',
				'(centralauth-automatic-global-groups-reason-local)',
				[ 'automatic-group-1', 'automatic-group-2' ],
				[],
			],
		];
	}
}
