<?php
declare( strict_types=1 );

use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilder;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilder
 */
class GlobalUserSelectQueryBuilderTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	private GlobalUserSelectQueryBuilder $queryBuilder;

	protected function setUp(): void {
		parent::setUp();

		$this->queryBuilder = $this->getServiceContainer()
			->getService( 'CentralAuth.GlobalUserSelectQueryBuilderFactory' )
			->newGlobalUserSelectQueryBuilder();
	}

	public function testFetchLocalUserIdentitiesShouldOnlyReturnAttachedLocalAccounts(): void {
		// Disable temporary account autocreation to allow creating an actor for an IP user
		// (which will then have actor_user = NULL).
		$this->disableAutoCreateTempUser();

		$ipUser = new UserIdentityValue( 0, '127.0.0.1' );

		$this->getServiceContainer()
			->getActorStore()
			->acquireActorId( $ipUser, $this->getDb() );

		$localUsers = [
			new UserIdentityValue( 1, 'TestUser' ),
			new UserIdentityValue( 2, 'OtherUser', 'foo' ),
			new UserIdentityValue( 3, 'UnattachedLocalUser' ),
		];

		$nextCentralId = 1_001;
		/** @var UserIdentity $localUser */
		foreach ( $localUsers as $localUser ) {
			// Create a local user and actor record for users on the current wiki.
			if ( $localUser->getWikiId() === UserIdentity::LOCAL ) {
				$localUser = $this->getServiceContainer()
					->getUserFactory()
					->newFromName( $localUser->getName() );
				$localUser->addToDatabase();
			}

			$wikis = [];

			if ( $localUser->getName() !== 'UnattachedLocalUser' ) {
				$wikis[] = [ $localUser->getWikiId() ?: WikiMap::getCurrentWikiId(), 'primary' ];
			}

			$centralUser = new CentralAuthTestUser(
				$localUser->getName(),
				'GUP@ssword',
				[
					'gu_id' => $nextCentralId++,
				],
				$wikis
			);
			$centralUser->save( $this->getDb() );

			// Set an appropriate local user ID mapping for this user.
			$this->getDb()->newUpdateQueryBuilder()
				->update( 'localuser' )
				->set( [ 'lu_local_id' => $localUser->getId( $localUser->getWikiId() ) ] )
				->where( [
					'lu_name' => $localUser->getName(),
					'lu_wiki' => $localUser->getWikiId() ?: WikiMap::getCurrentWikiId(),
				] )
				->caller( __METHOD__ )
				->execute();
		}

		$result = $this->queryBuilder
			->whereUserNames( array_map( static fn ( $user ) => $user->getName(), $localUsers ) )
			->caller( __METHOD__ )
			->fetchLocalUserIdentitites();

		$result = iterator_to_array( $result );

		$this->assertCount( 1, $result );
		$this->assertTrue(
			$localUsers[0]->equals( $result[0] ),
			'Expected to only return the sole attached local user from the current wiki'
		);
	}
}
