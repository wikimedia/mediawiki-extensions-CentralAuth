<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Exception\ReadOnlyError;
use MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthReadOnlyError;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider
 * @todo Convert to a pure unit test. Currently testGetPrimaryDBReadOnly() needs global
 * state, as it creates the CentralAuthReadOnlyError, which as an ErrorPageError creates the
 * translations for it using global state (wfMessage()). This is tracked as T281935.
 */
class CentralAuthConnectionProviderTest extends MediaWikiIntegrationTestCase {

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

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => false,
				]
			),
			$this->createMock( IConnectionProvider::class ),
			$this->createMock( LBFactory::class ),
			$roMode
		);

		$this->expectException( ReadOnlyError::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $roReason, '/' ) . '/' );
		$caConnectionProvider->assertNotReadOnly();
	}

	public function testGetReadOnlyReasonDatabase() {
		$roReason = 'replace this with the real reason before the maintenance window';

		$lbFactory = $this->createMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );

		$roMode->method( 'isReadOnly' )->willReturn( false );
		$roMode->method( 'getReason' )->willReturn( $roReason );

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => false,
				]
			),
			$this->createMock( IConnectionProvider::class ),
			$lbFactory,
			$roMode
		);

		$this->expectException( CentralAuthReadOnlyError::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $roReason, '/' ) . '/' );
		$caConnectionProvider->assertNotReadOnly();
	}

	public function testGetReadOnlyReasonWriteable() {
		$lbFactory = $this->createMock( LBFactory::class );

		$roMode = $this->createMock( ReadOnlyMode::class );

		$roMode->method( 'isReadOnly' )->willReturn( false );
		$roMode->method( 'getReason' )->willReturn( false );

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => false,
				]
			),
			$this->createMock( IConnectionProvider::class ),
			$lbFactory,
			$roMode
		);

		$this->assertFalse( $caConnectionProvider->isReadOnly() );
		$caConnectionProvider->assertNotReadOnly();
	}

	public function testGetPrimaryDBReadOnly() {
		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => true,
				]
			),
			$this->createNoOpMock( IConnectionProvider::class ),
			$this->createNoOpMock( LBFactory::class ),
			$roMode
		);

		$this->expectException( CentralAuthReadOnlyError::class );
		$caConnectionProvider->getPrimaryDatabase();
	}

	public function testGetReplicaDatabaseReadOnly() {
		$database = $this->createMock( IReadableDatabase::class );

		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getReplicaDatabase' )->with( 'virtual-centralauth' )->willReturn( $database );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => true,
				]
			),
			$connectionProvider,
			$this->createNoOpMock( LBFactory::class ),
			$roMode
		);

		$this->assertEquals( $database, $caConnectionProvider->getReplicaDatabase() );
	}

	public function testReturnsGlobalDatabaseByDefault() {
		$primary = $this->createMock( IDatabase::class );
		$replica = $this->createMock( IReadableDatabase::class );

		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->expects( $this->atLeastOnce() )
			->method( 'getPrimaryDatabase' )
			->with( 'virtual-centralauth' )
			->willReturn( $primary );
		$connectionProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->with( 'virtual-centralauth' )
			->willReturn( $replica );

		$roMode = $this->createMock( ReadOnlyMode::class );
		$roMode->method( 'isReadOnly' )->willReturn( false );
		$roMode->method( 'getReason' )->willReturn( false );

		$caConnectionProvider = new CentralAuthConnectionProvider(
			new ServiceOptions(
				CentralAuthConnectionProvider::CONSTRUCTOR_OPTIONS,
				[
					CAMainConfigNames::CentralAuthReadOnly => false,
				]
			),
			$connectionProvider,
			$this->createNoOpMock( LBFactory::class ),
			$roMode,
		);

		$this->assertEquals( $primary, $caConnectionProvider->getPrimaryDatabase() );
		$this->assertEquals( $replica, $caConnectionProvider->getReplicaDatabase() );
	}
}
