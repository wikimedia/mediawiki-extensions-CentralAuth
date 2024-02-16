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
use MediaWiki\WikiMap\WikiMap;
use SpecialPageTestBase;
use Xml;

/**
 * @coversDefaultClass \MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership
 * @group Database
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class SpecialGlobalGroupMembershipTest extends SpecialPageTestBase {
	protected function setUp(): void {
		parent::setUp();

		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralPrimaryDB();

		$caDbw->insert(
			'global_group_permissions',
			[
				[ 'ggp_group' => 'group-one', 'ggp_permission' => 'right-one' ],
				[ 'ggp_group' => 'group-two', 'ggp_permission' => 'right-two' ],
				[ 'ggp_group' => 'group-three', 'ggp_permission' => 'right-three' ],
			],
			__METHOD__
		);
	}

	protected function newSpecialPage(): SpecialGlobalGroupMembership {
		return new SpecialGlobalGroupMembership(
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getUserNamePrefixSearch(),
			$this->getServiceContainer()->getUserNameUtils(),
			CentralAuthServices::getGlobalGroupLookup( $this->getServiceContainer() )
		);
	}

	private function getRegisteredTestUser(): CentralAuthUser {
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
		return $caUser;
	}

	/**
	 * @covers ::__construct
	 */
	public function testConstructor() {
		$this->assertInstanceOf(
			SpecialGlobalGroupMembership::class,
			$this->newSpecialPage()
		);
	}

	/**
	 * @dataProvider provideFetchUserGood
	 * @covers ::fetchUser
	 */
	public function testFetchUserGood( $inputFunction ) {
		$user = $this->getRegisteredTestUser();
		$status = $this->newSpecialPage()->fetchUser( $inputFunction( $user ) );

		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertEquals( $user->getName(), $value->getName() );
		$this->assertEquals( $user->getId(), $value->getId() );
	}

	public static function provideFetchUserGood(): Generator {
		yield 'Username' => [ fn ( CentralAuthUser $user ) => $user->getName() ];
		yield 'Non-canonical username' => [ fn ( CentralAuthUser $user ) => lcfirst( $user->getName() ) ];
		yield 'ID' => [ fn ( CentralAuthUser $user ) => '#' . $user->getId() ];
	}

	/**
	 * @dataProvider provideFetchUserNonexistent
	 * @covers ::fetchUser
	 */
	public function testFetchUserNonexistent( string $input, string $error ) {
		$status = $this->newSpecialPage()->fetchUser( $input );
		$this->assertStatusError( $error, $status );
	}

	public static function provideFetchUserNonexistent() {
		yield 'Blank' => [ '', 'nouserspecified' ];
		yield 'Username' => [ 'Not in use', 'nosuchusershort' ];
		yield 'Invalid username' => [ 'Invalid@username', 'nosuchusershort' ];
		yield 'ID' => [ '#12345678', 'noname' ];
	}

	/**
	 * @covers ::execute
	 * @covers ::editUserGroupsForm
	 * @covers ::showEditUserGroupsForm
	 * @covers ::groupCheckboxes
	 */
	public function testRenderFormForPrivilegedUser() {
		$user = $this->getRegisteredTestUser();
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
			Xml::checkLabel(
				$specialPage->msg( 'group-group-one-member', $user->getName() )
					->inLanguage( 'qqx' )
					->text(),
				'wpGroup-group-one',
				'wpGroup-group-one',
				false,
				[ 'class' => 'mw-userrights-groupcheckbox' ],
			),
			$html
		);

		// Group two: indefinite member
		$this->assertStringContainsString(
			Xml::checkLabel(
				$specialPage->msg( 'group-group-two-member', $user->getName() )
					->inLanguage( 'qqx' )
					->text(),
				'wpGroup-group-two',
				'wpGroup-group-two',
				true,
				[ 'class' => 'mw-userrights-groupcheckbox' ],
			),
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
			Xml::checkLabel(
				$specialPage->msg( 'group-group-three-member', $user->getName() )
					->inLanguage( 'qqx' )
					->text(),
				'wpGroup-group-three',
				'wpGroup-group-three',
				true,
				[ 'class' => 'mw-userrights-groupcheckbox' ],
			),
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

		// Can save
		$this->assertStringContainsString(
			$specialPage->msg( 'userrights-changeable-col', 3 )
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

	/**
	 * @covers ::execute
	 * @covers ::editUserGroupsForm
	 * @covers ::showEditUserGroupsForm
	 */
	public function testRenderFormReadOnly() {
		$user = $this->getRegisteredTestUser();
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

	/**
	 * @covers ::execute
	 * @covers ::saveUserGroups
	 * @covers ::doSaveUserGroups
	 * @covers ::addLogEntry
	 */
	public function testSave() {
		$user = $this->getRegisteredTestUser();
		$originalExpiry = $user->getGlobalGroupsWithExpiration()['group-three'];

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
	}
}
