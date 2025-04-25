<?php

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Setup database tests for centralauth
 *
 * @covers MediaWiki\Extension\CentralAuth\User\CentralAuthUser
 * @group CentralAuthDB
 * @group Database
 */
class CentralAuthUserUsingDatabaseTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

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

	public function testAdminLockHideForLockWhenAuthorityMissingRight() {
		$caUser = CentralAuthUser::getInstanceByName( $this->getTestCentralAuthUserWithExistingLocalWikis() );
		$adminLockHideStatus = $caUser->adminLockHide( true, null, 'test', RequestContext::getMain() );
		$this->assertStatusError( 'centralauth-admin-not-authorized', $adminLockHideStatus );
	}

	public function testAdminLockHideForSuppressWhenAuthorityMissingRight() {
		$caUser = CentralAuthUser::getInstanceByName( $this->getTestCentralAuthUserWithExistingLocalWikis() );
		$adminLockHideStatus = $caUser->adminLockHide(
			null, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'test', RequestContext::getMain()
		);
		$this->assertStatusError( 'centralauth-admin-not-authorized', $adminLockHideStatus );
	}

	public function testAdminLockHideForSuppressWhenGlobalUserHasTooManyEdits() {
		// Mock that the central user has too many edits to be suppressed.
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturn( 1001 );
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );
		// Attempt to suppress the central user with too many edits
		$caUser = CentralAuthUser::getInstanceByName( $this->getTestCentralAuthUserWithExistingLocalWikis() );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$adminLockHideStatus = $caUser->adminLockHide(
			null, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'test', $context
		);
		$this->assertStatusError( 'centralauth-admin-too-many-edits', $adminLockHideStatus );
	}

	public function testAdminLockHideForLockWhenLockFails() {
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		// Get the central account instance and load the global account status data for it
		$caUser1 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser1->loadStateNoCache();
		// Lock the global account using a different CentralAuthUser instance, so that we can simulate a race condition
		// using the outdated first instance.
		CentralAuthServices::getUserCache()->clear();
		$caUser2 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser2->adminLock();
		// Now try to lock the global account using the first instance.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$adminLockHideStatus = $caUser1->adminLockHide( true, null, 'test', $context );
		$this->assertStatusError( 'centralauth-state-mismatch', $adminLockHideStatus );
		// Check that the call to ::adminLockHide did not create a log entry, as no change was made by it.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => [ 'suppress', 'globalauth' ] ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testAdminLockHideForUnLockWhenUnLockFails() {
		$globalAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		// Get the central account instance for a global account that is locked.
		$caUser1 = CentralAuthUser::getInstanceByName( $globalAccountUsername );
		$caUser1->adminLock();
		$caUser1->loadStateNoCache();
		// Lock the global account using a different CentralAuthUser instance, so that we can simulate a race condition
		// using the outdated first instance by unlocking the account.
		CentralAuthServices::getUserCache()->clear();
		$caUser2 = CentralAuthUser::getInstanceByName( $globalAccountUsername );
		$caUser2->adminUnlock();
		// Now try to lock the global account using the first instance.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$adminLockHideStatus = $caUser1->adminLockHide( false, null, 'test', $context );
		$this->assertStatusError( 'centralauth-state-mismatch', $adminLockHideStatus );
		// Check that the call to ::adminLockHide did not create a log entry, as no change was made by it.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => [ 'suppress', 'globalauth' ] ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testAdminLockHideForLockAndSuppressWhenSuppressFails() {
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		// Get the central account instance and load the global account status data for it
		$caUser1 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser1->loadStateNoCache();
		// Suppress the global account using a different CentralAuthUser instance, so that we can simulate a
		// race condition using the outdated first instance.
		CentralAuthServices::getUserCache()->clear();
		$caUser2 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser2->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED );
		// Now try to lock the global account using the first instance.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$adminLockHideStatus = $caUser1->adminLockHide(
			true, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'test', $context
		);
		$this->assertStatusError( 'centralauth-state-mismatch', $adminLockHideStatus );
		$caUser1->invalidateCache();
		$this->assertFalse( $caUser1->isLocked() );
		$this->assertSame( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, $caUser1->getHiddenLevelInt() );
		// Check that the call to ::adminLockHide did not create a log entry, as no change was made by it.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => [ 'suppress', 'globalauth' ] ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testAdminLockHideForUnLockAndHideWhenHideFails() {
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		// Get the central account instance and load the global account status data for it
		$caUser1 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser1->adminLock();
		// Suppress the global account using a different CentralAuthUser instance, so that we can simulate a
		// race condition using the outdated first instance.
		CentralAuthServices::getUserCache()->clear();
		$caUser2 = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$caUser2->adminSetHidden( CentralAuthUser::HIDDEN_LEVEL_LISTS );
		// Now try to lock the global account using the first instance.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$adminLockHideStatus = $caUser1->adminLockHide(
			false, CentralAuthUser::HIDDEN_LEVEL_LISTS, 'test', $context
		);
		$this->assertStatusError( 'centralauth-state-mismatch', $adminLockHideStatus );
		$caUser1->invalidateCache();
		$this->assertTrue( $caUser1->isLocked() );
		$this->assertSame( CentralAuthUser::HIDDEN_LEVEL_LISTS, $caUser1->getHiddenLevelInt() );
		// Check that the call to ::adminLockHide did not create a log entry, as no change was made by it.
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => [ 'suppress', 'globalauth' ] ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testAdminLockHideForSuccessfulChange() {
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		$caUser = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$performer = $this->getTestUser( [ 'steward' ] )->getUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $performer );
		$adminLockHideStatus = $caUser->adminLockHide(
			true, CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'test', $context
		);
		// Check that the call to ::adminLockHide worked and created a log entry
		$this->assertStatusGood( $adminLockHideStatus );
		$caUser->invalidateCache();
		$this->assertTrue( $caUser->isLocked() );
		$this->assertSame( CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, $caUser->getHiddenLevelInt() );
		$this->newSelectQueryBuilder()
			->select( [
				'log_title', 'log_namespace', 'log_action', 'log_type', 'actor_name', 'comment_text',
			] )
			->from( 'logging' )
			->join( 'actor', null, 'actor_id=log_actor' )
			->join( 'comment', null, 'comment_id=log_comment_id' )
			->caller( __METHOD__ )
			->assertRowValue( [
				"$centralAccountUsername@global", NS_USER, 'setstatus', 'suppress', $performer->getName(), 'test',
			] );
		// Check that the log parameters are as expected
		$logParams = $this->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertSame(
			[ 'added' => [ 'locked', 'oversighted' ], 'removed' => [] ],
			LogEntryBase::extractParams( $logParams )
		);
		// Check that the local block exists
		$blocks = $this->getServiceContainer()->getDatabaseBlockStore()
			->newListFromTarget( $centralAccountUsername );
		$this->assertCount( 1, $blocks );
		$this->assertTrue( $blocks[0]->getHideName() );
	}

	/** @dataProvider provideAddToGlobalGroup */
	public function testAddToGlobalGroup( $group, $expiry ) {
		ConvertibleTimestamp::setFakeTime( '20240405060708' );
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		$caUser = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$this->assertStatusGood( $caUser->addToGlobalGroup( $group, $expiry ) );
		$this->assertArrayEquals( [ $group => $expiry ], $caUser->getGlobalGroupsWithExpiration() );
		$this->assertArrayEquals( [ $group ], $caUser->getGlobalGroups() );
	}

	public static function provideAddToGlobalGroup() {
		return [
			'Group is granted indefinitely' => [ 'global-test', null ],
			'Group is granted for a week' => [ 'global-test-2', '20240412060708' ],
		];
	}

	/** @dataProvider provideAddToGlobalGroup */
	public function testAddToGlobalGroupWhenUserIsTemporaryAccount( $group, $expiry ) {
		$this->enableAutoCreateTempUser();
		$tempAccountName = $this->getServiceContainer()->getTempUserCreator()
			->acquireAndStashName( RequestContext::getMain()->getRequest()->getSession() );
		$this->getTestCentralAuthUserWithExistingLocalWikis( $tempAccountName );
		$caUser = CentralAuthUser::getInstanceByName( $tempAccountName );
		$this->assertStatusError(
			'centralauth-admin-cannot-lock-temporary-account',
			$caUser->addToGlobalGroup( $group, $expiry )
		);
	}

	/** @dataProvider provideRemoveFromGlobalGroups */
	public function testRemoveFromGlobalGroups( $groupsArgument, $groupsAfterCall ) {
		// Get a test user with the global group 'global-test'
		$centralAccountUsername = $this->getTestCentralAuthUserWithExistingLocalWikis();
		$caUser = CentralAuthUser::getInstanceByName( $centralAccountUsername );
		$this->assertStatusGood( $caUser->addToGlobalGroup( 'global-test' ) );
		$this->assertArrayEquals( [ 'global-test' ], $caUser->getGlobalGroups() );

		// Call ::removeFromGlobalGroups with $groups and check that the global groups of the user is as expected.
		$caUser->removeFromGlobalGroups( $groupsArgument );
		$this->assertArrayEquals( $groupsAfterCall, $caUser->getGlobalGroups() );
	}

	public static function provideRemoveFromGlobalGroups() {
		return [
			'Removing a group the CentralAuth user has' => [ 'global-test', [] ],
			'Removing a group the CentralAuth user does not have' => [ 'global-test-unassigned', [ 'global-test' ] ],
			'Removing a group the CentralAuth user has and does not have' => [
				[ 'global-test', 'global-test-unassigned' ], [],
			],
		];
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

	private function getTestCentralAuthUserWithExistingLocalWikis( $targetUsername = null ): string {
		if ( !$targetUsername ) {
			$targetUsername = 'GlobalTestUser' . TestUserRegistry::getNextId();
		}
		$targetUser = new CentralAuthTestUser(
			$targetUsername, 'GUP@ssword',
			[ 'gu_id' => '123' ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$targetUser->save( $this->getDb() );
		return $targetUsername;
	}
}
