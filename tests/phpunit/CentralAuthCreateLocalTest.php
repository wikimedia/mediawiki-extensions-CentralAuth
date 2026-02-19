<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Special\SpecialCreateLocalAccount;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * @author Taavi "Majavah" Väänänen
 * @covers \MediaWiki\Extension\CentralAuth\Special\SpecialCreateLocalAccount
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService::attemptAutoCreateLocalUserFromName
 * @group Database
 */
class CentralAuthCreateLocalTest extends MediaWikiIntegrationTestCase {

	private SpecialCreateLocalAccount $specialCreateLocalAccount;

	private const USERNAME_NONEXISTENT = 'AccountThatDoesNotExist';

	private const USER_ID_EXISTING = '1200';
	private const USERNAME_EXISTING = 'ExistingGlobalUser';

	private const USER_ID_NOT_ATTACHED = '1201';
	private const USERNAME_NOT_ATTACHED = 'NonAttachedUser';

	private const USER_ID_SUPPRESSED = '1202';
	private const USERNAME_SUPPRESSED = 'SuppressedTestUser';

	protected function setUp(): void {
		// To avoid complexity related to the use of shared domain
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthEnableSul3, false );

		parent::setUp();

		$this->specialCreateLocalAccount = new SpecialCreateLocalAccount(
			CentralAuthServices::getForcedLocalCreationService()
		);
	}

	public function testWithAccountThatDoesNotExist() {
		$name = self::USERNAME_NONEXISTENT;

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertStatusNotGood( $result );
		$this->assertSame( 0, User::newFromName( $name )->getId() );
	}

	public function testWithSuppressedAccount() {
		$name = self::USERNAME_SUPPRESSED;

		$this->overrideUserPermissions(
			$this->specialCreateLocalAccount->getUser(),
			[ 'centralauth-suppress' => false ] );

		$u = new CentralAuthTestUser(
			$name,
			'GSUP@ssword',
			[
				'gu_id' => self::USER_ID_SUPPRESSED,
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'not' . WikiMap::getCurrentWikiId(), 'primary' ],
			],
			false
		);
		$u->save( $this->getDb() );

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertStatusError( 'centralauth-createlocal-no-global-account', $result );
		$this->assertSame( 0, User::newFromName( $name )->getId() );
	}

	public function testWithAlreadyExistingUser() {
		$name = self::USERNAME_EXISTING;

		$u = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => self::USER_ID_EXISTING ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->getDb() );

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertStatusNotGood( $result );
		$this->assertNotEquals( 0, User::newFromName( $name )->getId() );
	}

	public static function provideWithNonAttachedUser() {
		return [
			'CreateLocalAccount from an unblocked source IP' => [
				'blockSourceIP' => false,
				'msg' => 'Autocreation failed unexpectedly',
			],
			'CreateLocalAccount from a blocked source IP' => [
				'blockSourceIP' => true,
				'msg' => 'Autocreation failed unexpectedly despite ipblock-exempt permission',
			],
		];
	}

	/**
	 * @dataProvider provideWithNonAttachedUser
	 */
	public function testWithNonAttachedUser( bool $blockSourceIP, string $msg ) {
		$name = self::USERNAME_NOT_ATTACHED;

		$u = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => self::USER_ID_NOT_ATTACHED ],
			[
				[ 'not' . WikiMap::getCurrentWikiId(), 'primary' ],
			],
			false
		);
		$u->save( $this->getDb() );

		// Make sure we have a named user set with the request.
		$performer = $this->getTestSysop()->getUser();
		$this->assertTrue( $performer->isAllowed( 'centralauth-createlocal' ) );
		if ( $blockSourceIP ) {
			$this->assertTrue( $performer->isAllowed( 'ipblock-exempt' ) );
		}
		$context = RequestContext::getMain();
		$context->setUser( $performer );

		// Block the context user's source IP: this block should be bypassed
		if ( $blockSourceIP ) {
			$status = $this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
				$context->getRequest()->getIP(), $this->getTestSysop()->getAuthority(), 'infinity',
				'', [ 'isCreateAccountBlocked' => true ]
			)->placeBlock();
			$this->assertStatusOK( $status, 'Block was not placed' );
		}

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertStatusGood( $result, $msg );
		$this->assertNotEquals( 0, User::newFromName( $name )->getId() );
	}
}
