<?php

/**
 * @author Taavi "Majavah" Väänänen
 * @covers SpecialCreateLocalAccount::onSubmit
 * @covers CentralAuthUtils::attemptAutoCreateLocalUserFromName
 * @group Database
 */
class CentralAuthCreateLocalTest extends CentralAuthUsingDatabaseTestCase {

	/** @var SpecialCreateLocalAccount */
	private $specialCreateLocalAccount;

	private const USERNAME_NONEXISTENT = 'AccountThatDoesNotExist';

	private const USER_ID_EXISTING = '1200';
	private const USERNAME_EXISTING = 'ExistingGlobalUser';

	private const USER_ID_NOT_ATTACHED = '1201';
	private const USERNAME_NOT_ATTACHED = 'NonAttachedUser';

	private const USER_ID_SUPPRESSED = '1202';
	private const USERNAME_SUPPRESSED = 'SuppressedTestUser';

	protected function setUp() : void {
		parent::setUp();

		$this->specialCreateLocalAccount = new SpecialCreateLocalAccount();
	}

	public function testWithAccountThatDoesNotExist() {
		$name = self::USERNAME_NONEXISTENT;

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertFalse( $result->isGood() );
		$this->assertSame( 0, User::newFromName( $name )->getId() );
	}

	public function testWithOversightedAccount() {
		$name = self::USERNAME_SUPPRESSED;

		$this->overrideUserPermissions(
			$this->specialCreateLocalAccount->getUser(),
			[ 'centralauth-oversight' => false ] );

		$u = new CentralAuthTestUser(
			$name,
			'GSUP@ssword',
			[
				'gu_id' => self::USER_ID_SUPPRESSED,
				'gu_locked' => 1,
				'gu_hidden' => CentralAuthUser::HIDDEN_OVERSIGHT,
				'gu_email' => 'testsuppressed@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'not' . wfWikiID(), 'primary' ],
			],
			false
		);
		$u->save( $this->db );

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertFalse( $result->isGood() );
		$this->assertSame( 'centralauth-createlocal-no-global-account', $result->getMessage()->getKey() );
		$this->assertSame( 0, User::newFromName( $name )->getId() );
	}

	public function testWithAlreadyExistingUser() {
		$name = self::USERNAME_EXISTING;

		$u = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => self::USER_ID_EXISTING ],
			[
				[ wfWikiID(), 'primary' ],
			]
		);
		$u->save( $this->db );

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertFalse( $result->isGood() );
		$this->assertNotEquals( 0, User::newFromName( $name )->getId() );
	}

	public function testWithNonAttachedUser() {
		$name = self::USERNAME_NOT_ATTACHED;

		$u = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => self::USER_ID_NOT_ATTACHED ],
			[
				[ 'not' . wfWikiID(), 'primary' ],
			],
			false
		);
		$u->save( $this->db );

		$result = $this->specialCreateLocalAccount->onSubmit( [
			'username' => $name,
			'reason' => 'Test reason',
		] );

		$this->assertTrue( $result->isGood() );
		$this->assertNotEquals( 0, User::newFromName( $name )->getId() );
	}
}
