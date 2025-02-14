<?php
namespace MediaWiki\Extension\CentralAuth\User\Tests\Unit;

use ArrayIterator;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilder;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider
 */
class CentralAuthGlobalRegistrationProviderTest extends MediaWikiUnitTestCase {

	private GlobalUserSelectQueryBuilderFactory $globalUserSelectQueryBuilderFactory;

	private CentralAuthDatabaseManager $centralAuthDatabaseManager;

	private UserNameUtils $userNameUtils;

	private CentralAuthGlobalRegistrationProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->globalUserSelectQueryBuilderFactory = $this->createMock( GlobalUserSelectQueryBuilderFactory::class );
		$this->centralAuthDatabaseManager = $this->createMock( CentralAuthDatabaseManager::class );
		$this->userNameUtils = $this->createMock( UserNameUtils::class );
		$this->provider = new CentralAuthGlobalRegistrationProvider(
			$this->globalUserSelectQueryBuilderFactory,
			$this->centralAuthDatabaseManager,
			$this->userNameUtils
		);
	}

	public function testFetchRegistrationBatchShouldHandleNoUsers(): void {
		$this->userNameUtils->expects( $this->never() )
			->method( 'getCanonical' );

		$this->globalUserSelectQueryBuilderFactory->expects( $this->never() )
			->method( $this->anything() );

		$timestampsByLocalId = $this->provider->fetchRegistrationBatch( [] );

		$this->assertSame( [], $timestampsByLocalId );
	}

	public function testFetchRegistrationBatchShouldHandleOnlyAnonymousUsers(): void {
		$users = [
			new UserIdentityValue( 0, '127.0.0.1' ),
			new UserIdentityValue( 0, '127.0.0.2' ),
		];

		$this->userNameUtils->method( 'getCanonical' )
			->willReturnMap( [
				[ '127.0.0.1', UserNameUtils::RIGOR_VALID, false ],
				[ '127.0.0.2', UserNameUtils::RIGOR_VALID, false ],
			] );

		$this->globalUserSelectQueryBuilderFactory->expects( $this->never() )
			->method( $this->anything() );

		$timestampsByLocalId = $this->provider->fetchRegistrationBatch( $users );

		$this->assertSame( [ 0 => null ], $timestampsByLocalId );
	}

	public function testFetchRegistrationBatchShouldExcludeAnonymousUsersFromBatches(): void {
		$users = [
			new UserIdentityValue( 0, '127.0.0.1' ),
			new UserIdentityValue( 4, 'TestUser' ),
		];

		$this->userNameUtils->method( 'getCanonical' )
			->willReturnMap( [
				[ '127.0.0.1', UserNameUtils::RIGOR_VALID, false ],
				[ 'TestUser', UserNameUtils::RIGOR_VALID, 'TestUser' ],
			] );

		$centralAuthUser = $this->makeMockCentralUser( 'TestUser' );

		$localUserAttachedExpr = new Expression( 'lu_wiki', '!=', null );
		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'expr' )
			->with( 'lu_wiki', '!=', null )
			->willReturn( $localUserAttachedExpr );

		$this->centralAuthDatabaseManager->method( 'getCentralReplicaDB' )
			->willReturn( $dbr );

		$selectQueryBuilder = $this->createMock( GlobalUserSelectQueryBuilder::class );
		$selectQueryBuilder->method(
			$this->anythingBut( 'whereUserNames', 'andWhere', 'fetchCentralAuthUsers' )
		)
			->willReturnSelf();
		$selectQueryBuilder->method( 'whereUserNames' )
			->with( [ 'TestUser' ] )
			->willReturnSelf();
		$selectQueryBuilder->method( 'andWhere' )
			->with( $localUserAttachedExpr )
			->willReturnSelf();
		$selectQueryBuilder->method( 'fetchCentralAuthUsers' )
			->willReturn( new ArrayIterator( [ $centralAuthUser ] ) );

		$this->globalUserSelectQueryBuilderFactory->method( 'newGlobalUserSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$timestampsByLocalId = $this->provider->fetchRegistrationBatch( $users );

		$this->assertSame( [ 0 => null, 4 => '20240101000000' ], $timestampsByLocalId );
	}

	public function testFetchRegistrationBatchShouldBatchQueries(): void {
		$users = [];

		for ( $i = 1; $i <= 2_000; $i++ ) {
			$users[] = new UserIdentityValue( $i, 'TestUser' . $i );
		}

		$firstNameBatch = array_map(
			static fn ( UserIdentity $user ): string => $user->getName(),
			array_slice( $users, 0, 1_000 )
		);
		$secondNameBatch = array_map(
			static fn ( UserIdentity $user ): string => $user->getName(),
			array_slice( $users, 1_000 )
		);

		$this->userNameUtils->method( 'getCanonical' )
			->with( $this->matches( 'TestUser%d' ), UserNameUtils::RIGOR_VALID )
			->willReturnArgument( 0 );

		$localUserAttachedExpr = new Expression( 'lu_wiki', '!=', null );
		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'expr' )
			->with( 'lu_wiki', '!=', null )
			->willReturn( $localUserAttachedExpr );

		$this->centralAuthDatabaseManager->method( 'getCentralReplicaDB' )
			->willReturn( $dbr );

		$firstBatchSelectQueryBuilder = $this->createMock( GlobalUserSelectQueryBuilder::class );
		$firstBatchSelectQueryBuilder->method( $this->anythingBut( 'andWhere', 'fetchCentralAuthUsers' ) )
			->willReturnSelf();
		$firstBatchSelectQueryBuilder->method( 'andWhere' )
			->with( $localUserAttachedExpr )
			->willReturnSelf();
		$firstBatchSelectQueryBuilder->method( 'fetchCentralAuthUsers' )
			->willReturn( new ArrayIterator( array_map( [ $this, 'makeMockCentralUser' ], $firstNameBatch ) ) );

		$secondBatchSelectQueryBuilder = $this->createMock( GlobalUserSelectQueryBuilder::class );
		$secondBatchSelectQueryBuilder->method( $this->anythingBut( 'andWhere', 'fetchCentralAuthUsers' ) )
			->willReturnSelf();
		$secondBatchSelectQueryBuilder->method( 'andWhere' )
			->with( $localUserAttachedExpr )
			->willReturnSelf();
		$secondBatchSelectQueryBuilder->method( 'fetchCentralAuthUsers' )
			->willReturn( new ArrayIterator( array_map( [ $this, 'makeMockCentralUser' ], $secondNameBatch ) ) );

		$selectQueryBuilder = $this->createMock( GlobalUserSelectQueryBuilder::class );
		$selectQueryBuilder->method( $this->anythingBut( 'whereUserNames' ) )
			->willReturnSelf();
		$selectQueryBuilder->method( 'whereUserNames' )
			->willReturnMap( [
				[ $firstNameBatch, $firstBatchSelectQueryBuilder ],
				[ $secondNameBatch, $secondBatchSelectQueryBuilder ]
			] );

		$this->globalUserSelectQueryBuilderFactory->method( 'newGlobalUserSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$timestampsByLocalId = $this->provider->fetchRegistrationBatch( $users );

		$this->assertCount( 2_000, $timestampsByLocalId );
		$this->assertSame( '20240101000000', $timestampsByLocalId[1] );
		$this->assertSame( '20240101000000', $timestampsByLocalId[2_000] );
	}

	/**
	 * Convenience function to make a mock CentralAuthUser with the given name
	 * and a registration timestamp of Jan 1st, 2024.
	 *
	 * @param string $userName
	 * @return CentralAuthUser
	 */
	private function makeMockCentralUser( string $userName ): CentralAuthUser {
		$centralUser = $this->createMock( CentralAuthUser::class );
		$centralUser->method( 'getName' )
			->willReturn( $userName );
		$centralUser->method( 'getRegistration' )
			->willReturn( '20240101000000' );

		return $centralUser;
	}
}
