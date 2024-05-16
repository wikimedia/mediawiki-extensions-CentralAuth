<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthReadOnlyError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

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
						'CentralAuthReadOnly' => false,
					]
				),
				$this->createMock( LBFactory::class ),
				$this->createMock( ReadOnlyMode::class )
			)
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

		$lbFactory = $this->createMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );

		$roMode->method( 'isReadOnly' )->willReturn( false );
		$roMode->method( 'getReason' )->willReturn( $roReason );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
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
		$lbFactory = $this->createMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );

		$roMode->method( 'isReadOnly' )->willReturn( false );
		$roMode->method( 'getReason' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
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
			->method( 'waitForReplication' );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthReadOnly' => false,
				]
			),
			$lbFactory,
			$roMode
		);

		$manager->waitForReplication();
	}

	/**
	 * @covers ::getCentralPrimaryDB
	 */
	public function testGetCentralDBPrimaryReadOnly() {
		$database = $this->createMock( IDatabase::class );

		$lbFactory = $this->createNoOpMock( LBFactory::class );
		$lbFactory->method( 'getPrimaryDatabase' )->with( 'virtual-centralauth' )->willReturn( $database );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthReadOnly' => true,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->expectException( CentralAuthReadOnlyError::class );
		$manager->getCentralPrimaryDB();
	}

	/**
	 * @covers ::getCentralReplicaDB
	 */
	public function testGetCentralDBReplicaReadOnly() {
		$database = $this->createMock( IReadableDatabase::class );

		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getReplicaDatabase' )->with( 'virtual-centralauth' )->willReturn( $database );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$manager = new CentralAuthDatabaseManager(
			new ServiceOptions(
				CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS,
				[
					'CentralAuthReadOnly' => true,
				]
			),
			$lbFactory,
			$roMode
		);

		$this->assertEquals( $database, $manager->getCentralReplicaDB() );
	}
}
