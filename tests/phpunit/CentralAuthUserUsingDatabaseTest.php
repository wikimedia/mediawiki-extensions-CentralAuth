<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

/**
 * Setup database tests for centralauth
 *
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthUserUsingDatabaseTest extends MediaWikiIntegrationTestCase {

	public function testBasicAttrs() {
		$this->createCentralAccountForGlobalUser();
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$this->assertTrue( $caUser->exists() );
		$this->assertSame( 1001, $caUser->getId() );
		$this->assertSame( 'GlobalUser', $caUser->getName() );
		$this->assertSame( WikiMap::getCurrentWikiId(), $caUser->getHomeWiki() );
		$this->assertTrue( $caUser->isAttached() );
		$this->assertFalse( $caUser->isLocked() );
		$this->assertSame( '20130627183537', $caUser->getRegistration() );
		$this->assertSame(
			CentralAuthUser::HIDDEN_LEVEL_NONE,
			$caUser->getHiddenLevelInt()
		);
		$this->assertSame(
			'8de7319aacab2020d38db1fbfac313a4',
			$caUser->getStateHash()
		);
	}

	public function testLoadFromDB() {
		$this->createCentralAccountForGlobalUser();
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$caUser->loadStateNoCache();
		$this->assertTrue( $caUser->exists() );
		$this->assertSame( 1001, $caUser->getId() );
	}

	public function testLoadAttached() {
		$this->createCentralAccountForGlobalUser();
		$caUser = CentralAuthUser::getInstanceByName( 'GlobalUser' );
		$this->assertArrayEquals(
			[
				WikiMap::getCurrentWikiId(),
				'enwiki',
				'dewiki',
				'metawiki',
			],
			$caUser->listAttached()
		);
	}

	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertSame( 1, preg_match( '/^[0-9a-f]{32}$/', $token ) );
	}

	public function testNewFromId() {
		$this->createCentralAccountForGlobalUser();
		$ca = CentralAuthUser::newFromId( 1001 );
		$this->assertSame( 'GlobalUser', $ca->getName() );

		$caBad = CentralAuthUser::newFromId( -1001 );
		$this->assertFalse( $caBad );
	}

	public function testRegister() {
		$caUserNew = CentralAuthUser::getPrimaryInstanceByName( 'RegTest' );
		$ok = $caUserNew->register( "R3gT3stP@ssword", "user@localhost" );
		$this->assertTrue( $ok );

		$caUser = CentralAuthUser::getInstanceByName( 'RegTest' );
		$this->assertTrue( $caUser->exists() );

		// And duplicate registration doesn't throw an exception (T108541)
		$this->assertFalse( $caUserNew->register( "R3gT3stP@ssword", "user@localhost" ) );
	}

	public function testLocked() {
		$caUser = CentralAuthUser::getInstanceByName( $this->getTestCentralAuthUserWithExistingLocalWikis() );
		$caUser->adminLock();
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
	}

	public function testHidden() {
		$caUser = CentralAuthUser::getInstanceByName( $this->getTestCentralAuthUserWithExistingLocalWikis() );
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED );
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isHidden() );
		$this->assertTrue( $caUser->isSuppressed() );
		$this->assertSame( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, $caUser->getHiddenLevelInt() );
	}

	public function testStoreMigrationData() {
		$caUsers = [
			'2001' => 'StoreMigrationDataUser 1',
			'2002' => 'StoreMigrationDataUser 2',
			'2003' => 'StoreMigrationDataUser 3',
		];
		CentralAuthUser::storeMigrationData( 'smdwiki', $caUsers );
		$this->newSelectQueryBuilder()
			->select( 'ln_name' )
			->from( 'localnames' )
			->where( [ 'ln_wiki' => 'smdwiki' ] )
			->assertResultSet( [
				[ 'StoreMigrationDataUser 1' ],
				[ 'StoreMigrationDataUser 2' ],
				[ 'StoreMigrationDataUser 3' ],
			] );
	}

	public function testAdminLockAndHide() {
		$globalAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		$caUser = CentralAuthUser::getPrimaryInstanceByName( $globalAccountUsername );
		$this->assertTrue( $caUser->exists() );
		$this->assertFalse( $caUser->isHidden() );
		$this->assertFalse( $caUser->isLocked() );

		$caUser->adminLock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_LISTS );

		// Check the DB
		$this->newSelectQueryBuilder()
			->select( [ 'gu_name', 'gu_locked', 'gu_hidden_level' ] )
			->from( 'globaluser' )
			->where( [ 'gu_name' => $globalAccountUsername ] )
			->assertRowValue( [ $globalAccountUsername, '1', CentralAuthUser::HIDDEN_LEVEL_LISTS ] );

		// Check that the instance was reloaded from the DB
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
		$this->assertTrue( $caUser->isHidden() );
		// Ignore cache, read from DB for new instance
		$caUser = CentralAuthUser::getPrimaryInstanceByName( $globalAccountUsername );
		$this->assertTrue( $caUser->exists() );
		$this->assertTrue( $caUser->isLocked() );
		$this->assertTrue( $caUser->isHidden() );

		$caUser->adminUnlock();
		$caUser->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_NONE );

		// Check that the instance was reloaded from the DB
		$this->assertTrue( $caUser->exists() );
		$this->assertFalse( $caUser->isHidden() );
		$this->assertFalse( $caUser->isLocked() );
		// Ignore cache, read from DB for new instance
		$caUser = CentralAuthUser::getPrimaryInstanceByName( $globalAccountUsername );
		$this->assertTrue( $caUser->exists() );
		$this->assertFalse( $caUser->isHidden() );
		$this->assertFalse( $caUser->isLocked() );
	}

	public function testAttach() {
		$caUser = new class( $this->getTestCentralAuthUserWithExistingLocalWikis() ) extends CentralAuthUser {

			protected function addLocalEdits( $wikiID ) {
				// This test can't connect to anotherwiki to fetch edit counts
			}
		};
		$this->assertNotContains( 'anotherwiki', $caUser->listAttached() );

		$caUser->attach( 'anotherwiki', 'admin', false );
		$this->assertTrue( $caUser->exists() );
		$this->assertContains( 'anotherwiki', $caUser->listAttached() );
	}

	public function testGetEmail() {
		$user = new CentralAuthUser( __METHOD__ );
		$this->assertSame( '', $user->getEmail() );
		$user->setEmail( 'test@test.test' );
		$this->assertSame( 'test@test.test', $user->getEmail() );

		$user->register( 'blabla', 'test@test.test' );
		$this->assertSame( 'test@test.test', $user->getEmail() );

		// reload
		$user->getStateHash( true );
		$this->assertSame( 'test@test.test', $user->getEmail() );
	}

	/** T370779 */
	public function testShouldHandleDuplicatesDuringMigration(): void {
		$testUserOne = $this->getMutableTestUser()->getUserIdentity();
		$testUserTwo = $this->getMutableTestUser()->getUserIdentity();

		$firstBatch = [
			$testUserOne,
			$testUserTwo
		];

		$secondBatch = [
			$testUserOne,
			new UserIdentityValue( $testUserTwo->getId(), 'OtherUser' )
		];

		$firstBatchResults = $this->simulateMigration( $firstBatch );
		$secondBatchResults = $this->simulateMigration( $secondBatch );

		foreach ( $firstBatchResults as $result ) {
			$this->assertTrue(
				$result,
				'Pass 1 migration should have succeeded for all users in the first batch'
			);
		}

		$this->assertFalse(
			$secondBatchResults[$testUserOne->getId()],
			"Pass 1 migration should have failed for \"{$testUserOne->getName()}\" as a corresponding global user exists"
		);
		$this->assertTrue(
			$secondBatchResults[$testUserTwo->getId()],
			"Pass 1 migration should have succeeded for \"{$testUserTwo->getName()}\""
		);
	}

	/**
	 * Simulate Pass 0 and Pass 1 migrations for a batch of users.
	 *
	 * @param UserIdentity[] $users Users to simulate migrations for.
	 * @return bool[] Pass 1 migration statuses by user ID.
	 */
	private function simulateMigration( array $users ): array {
		// A more realistic test would run each migration for a separate wiki ID,
		// but this is not really feasible in CI as there's no mechanism to setup
		// multiple test databases.
		$wikiId = WikiMap::getCurrentWikiId();

		// Pass 0 migration
		CentralAuthUser::storeMigrationData( $wikiId, $users );

		// Pass 1 migration
		$results = [];
		foreach ( $users as $user ) {
			$userId = $user->getId();
			$userName = $user->getName();

			$cu = $this->getMockBuilder( CentralAuthUser::class )
				->onlyMethods( [ 'queryUnattached' ] )
				->setConstructorArgs( [ $userName ] )
				->getMock();

			$cu->method( 'queryUnattached' )
				->willReturn( [
					$wikiId => [
						'wiki' => $wikiId,
						'id' => $userId,
						'name' => $userName,
						'email' => 'test@example.com',
						'emailAuthenticated' => wfTimestampNow(),
						'registration' => wfTimestampNow(),
						'password' => '',
						'editCount' => 1,
						'groupMemberships' => [],
						'blocked' => false,
					]
				] );

			$results[$userId] = $cu->storeAndMigrate();
		}

		return $results;
	}

	private function createCentralAccountForGlobalUser(): void {
		$user = new CentralAuthTestUser(
			'GlobalUser',
			'GUP@ssword',
			[ 'gu_id' => '1001' ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
				[ 'enwiki', 'primary' ],
				[ 'dewiki', 'login' ],
				[ 'metawiki', 'password' ],
			]
		);
		$user->save( $this->getDb() );
	}

	private function getTestCentralAuthUserWithExistingLocalWikis(): string {
		$targetUsername = 'GlobalTestUser' . TestUserRegistry::getNextId();
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '123' ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		return $targetUsername;
	}
}
