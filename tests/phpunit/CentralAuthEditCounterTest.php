<?php

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ScopedCallback;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthEditCounter
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserEditCountUpdateHookHandler
 * @group Database
 */
class CentralAuthEditCounterTest extends MediaWikiIntegrationTestCase {

	public function testEdit() {
		$user = $this->getTestCentralAuthUser( 100, 'CentralAuthEditCounterTest' );
		$caUser = CentralAuthUser::getInstance( $user );
		$editCounter = CentralAuthServices::getEditCounter();
		$this->assertSame( 0, $editCounter->getCount( $caUser ) );
		$scope = DeferredUpdates::preventOpportunisticUpdates();
		$this->editPage( 'Test', 'test edit', '', NS_MAIN, $user );
		DeferredUpdates::doUpdates();
		ScopedCallback::consume( $scope );
		$this->assertSame( 1, $editCounter->getCount( $caUser ) );
	}

	/**
	 * Returns a {@link User} for a user with an existing central account and local account
	 */
	private function getTestCentralAuthUser( int $globalUserId, string $username ): User {
		$testUser = new CentralAuthTestUser(
			$username,
			bin2hex( random_bytes( 6 ) ),
			[ 'gu_id' => $globalUserId ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$testUser->save( $this->getDb() );
		return $this->getServiceContainer()->getUserFactory()->newFromName( $username );
	}

	public function testGetCountForCentralUserWithNoIdReturnsZero(): void {
		$editCounter = CentralAuthServices::getEditCounter();
		$caUser = CentralAuthUser::getInstance(
			UserIdentityValue::newRegistered( 123, 'NonExistingUser123' )
		);

		$this->assertSame(
			0,
			$editCounter->getCount( $caUser ),
			'Non-existing central users should have an global edit count of 0'
		);
	}

	public function testIncrementClearsGetCountInstanceCache(): void {
		$user = $this->getTestCentralAuthUser( 100, 'CentralAuthEditCounterTest' );
		$caUser = CentralAuthUser::getInstance( $user );

		$editCounter = CentralAuthServices::getEditCounter();

		$this->assertSame(
			0,
			$editCounter->getCount( $caUser ),
			'Global edit count should be initially 0'
		);

		// Make a test edit, but prevent the deferred update until we have
		// tested that initially the count stays at 0
		$scope = DeferredUpdates::preventOpportunisticUpdates();
		$this->editPage( 'Test', 'test edit', '', NS_MAIN, $user );

		$this->assertSame(
			0,
			$editCounter->getCount( $caUser ),
			'Global edit count should not change if the instance cache has not been cleared'
		);

		// Run deferred updates, which should call CentralAuthEditTracker::increment and clear
		// the instance cache for our test user
		ScopedCallback::consume( $scope );
		DeferredUpdates::doUpdates();

		$this->assertSame(
			1,
			$editCounter->getCount( $caUser ),
			'Global edit count should be refetched if the instance cache was cleared'
		);
	}

	public function testPreloadGetCountCacheActuallyCaches(): void {
		// Get two test users, one with an edit
		$firstUser = $this->getTestCentralAuthUser( 100, 'CentralAuthEditCounterTest' );
		$firstCaUser = CentralAuthUser::getInstance( $firstUser );

		$secondUser = $this->getTestCentralAuthUser( 101, 'CentralAuthEditCounterTest2' );
		$secondCaUser = CentralAuthUser::getInstance( $secondUser );

		// Create the global_edit_count rows for the users
		// (as CentralAuthTestUser doesn't do this for us)
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'global_edit_count' )
			->rows( [
				[ 'gec_user' => 100, 'gec_count' => 1 ],
				[ 'gec_user' => 101, 'gec_count' => 0 ],
			] )
			->caller( __METHOD__ )
			->execute();

		$editCounter = CentralAuthServices::getEditCounter();

		// Preload the internal instance cache with the current global edit count state
		$editCounter->preloadGetCountCache( [ $firstCaUser, $secondCaUser ] );

		// Make another test edit using both test users, but prevent CentralAuthEditCounter::increment
		// being called just yet (so we can test the internal instance cache was not cleared
		// until ::increment is called)
		$scope = DeferredUpdates::preventOpportunisticUpdates();
		$this->editPage( 'Test', 'test edit2', '', NS_MAIN, $firstUser );
		$this->editPage( 'Test', 'test edit3', '', NS_MAIN, $secondUser );

		$this->assertSame(
			1,
			$editCounter->getCount( $firstCaUser ),
			'::preloadGetCountCache should have cached the global edit count for the first user'
		);
		$this->assertSame(
			0,
			$editCounter->getCount( $secondCaUser ),
			'::preloadGetCountCache should have cached the global edit count for the second user'
		);

		// Now call CentralAuthEditCounter::increment to clear the cache
		ScopedCallback::consume( $scope );
		DeferredUpdates::doUpdates();

		$this->assertSame(
			2,
			$editCounter->getCount( $firstCaUser ),
			'::increment should have cleared the cache for the first test user'
		);
		$this->assertSame(
			1,
			$editCounter->getCount( $secondCaUser ),
			'::increment should have cleared the cache for the second test user'
		);
	}
}
