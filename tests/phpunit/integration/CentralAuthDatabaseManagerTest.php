<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * @coversDefaultClass \MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager
 * @todo Convert to a pure unit test. Currently testGetCentralDBPrimaryReadOnly() needs global
 * state, as it creates the CentralAuthReadOnlyError, which as an ErrorPageError creates the
 * translations for it using global state (wfMessage()). This is tracked as T281935.
 */
class CentralAuthDatabaseManagerTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers ::__construct
	 */
	public function testConstructor() {
		$this->assertInstanceOf(
			CentralAuthDatabaseManager::class,
			new CentralAuthDatabaseManager(
				new ServiceOptions(
					CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
					[
						'CentralAuthDatabase' => 'centralauth',
						'CentralAuthReadOnly' => false,
					]
				),
				$this->createMock( LBFactory::class ),
				$this->createMock( ReadOnlyMode::class )
			)
		);
	}

	/**
	 * @covers ::getLoadBalancer
	 */
	public function testGetLoadBalancer() {
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getMainLB' )->with( 'centralauth' )->willReturn( $loadBalancer );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->assertEquals(
			$loadBalancer,
			$manager->getLoadBalancer()
		);
	}

	/**
	 * @covers ::getCentralReadOnlyReason
	 * @covers ::isReadOnly
	 */
	public function testGetReadOnlyReasonReadOnlyMode() {
		$roReason = 'Database switchover script broke and left everything read only';
		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( true );
		$roMode->method( 'getReason' )->willReturn( $roReason );

		// Override global mode for ReadOnlyError::__construct()
		$this->overrideMwServices( null,
			[
				'ReadOnlyMode' => static function () use ( $roMode ) {
					return $roMode;
				}
			]
		);

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$this->createMock( LBFactory::class ),
			$roMode
		);

		$this->expectException( ReadOnlyError::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $roReason, '/' ) . '/' );
		$manager->assertNotReadOnly();
	}

	/**
	 * @covers ::getCentralReadOnlyReason
	 * @covers ::isReadOnly
	 */
	public function testGetReadOnlyReasonDatabase() {
		$roReason = 'replace this with the real reason before the maintenance window';

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getReadOnlyReason' )->willReturn( $roReason );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getMainLB' )->with( 'centralauth' )->willReturn( $loadBalancer );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->expectException( CentralAuthReadOnlyError::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $roReason, '/' ) . '/' );
		$manager->assertNotReadOnly();
	}

	/**
	 * @covers ::getCentralReadOnlyReason
	 * @covers ::isReadOnly
	 */
	public function testGetReadOnlyReasonWriteable() {
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getReadOnlyReason' )->willReturn( false );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getMainLB' )->with( 'centralauth' )->willReturn( $loadBalancer );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->assertFalse( $manager->isReadOnly() );
		$manager->assertNotReadOnly();
	}

	/**
	 * @covers ::waitForReplication
	 */
	public function testWaitForReplication() {
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->expects( $this->once() )
			->method( 'waitForReplication' )
			->with( [ 'domain' => 'centralauth' ] );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$manager->waitForReplication();
	}

	/**
	 * @covers ::getCentralDB
	 * @dataProvider provideValidIndexes
	 */
	public function testGetCentralDBValidIndexes( int $index ) {
		$loadBalancer = $this->createMock( ILoadBalancer::class );

		$database = $this->createMock( IDatabase::class );
		$loadBalancer->method( 'getConnectionRef' )
			->with( $index, [], 'centralauth' )
			->willReturn( $database );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getMainLB' )->with( 'centralauth' )->willReturn( $loadBalancer );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->assertEquals( $database, $manager->getCentralDB( $index ) );
	}

	public function provideValidIndexes(): Generator {
		yield 'DB_PRIMARY' => [ DB_PRIMARY ];
		yield 'DB_REPLICA' => [ DB_REPLICA ];
	}

	/**
	 * @covers ::getCentralDB
	 */
	public function testGetCentralDBInvalidIndex() {
		$lbFactory = $this->createNoOpMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->expectException( InvalidArgumentException::class );
		$manager->getCentralDB( 1337 );
	}

	/**
	 * @covers ::getCentralDB
	 */
	public function testGetCentralDBPrimaryReadOnly() {
		$lbFactory = $this->createNoOpMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => true,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->expectException( CentralAuthReadOnlyError::class );
		$manager->getCentralDB( DB_PRIMARY );
	}

	/**
	 * @covers ::getCentralDB
	 */
	public function testGetCentralDBReplicaReadOnly() {
		$loadBalancer = $this->createMock( ILoadBalancer::class );

		$database = $this->createMock( IDatabase::class );
		$loadBalancer->method( 'getConnectionRef' )
			->with( DB_REPLICA, [], 'centralauth' )
			->willReturn( $database );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getMainLB' )->with( 'centralauth' )->willReturn( $loadBalancer );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthDatabase' => 'centralauth',
					'CentralAuthReadOnly' => true,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->assertEquals( $database, $manager->getCentralDB( DB_REPLICA ) );
	}
}
