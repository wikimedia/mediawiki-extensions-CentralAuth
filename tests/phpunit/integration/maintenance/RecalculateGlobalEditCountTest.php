<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\Maintenance\RecalculateGlobalEditCount;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\RecalculateGlobalEditCount
 * @group Database
 */
class RecalculateGlobalEditCountTest extends MaintenanceBaseTestCase {

	private CentralAuthEditCounter $mockEditCounter;

	protected function setUp(): void {
		parent::setUp();

		$this->mockEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $this->mockEditCounter );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass(): string {
		return RecalculateGlobalEditCount::class;
	}

	private function createCentralUserAttachedToWiki(): string {
		return CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser()
			->getName();
	}

	public function testMissingWikiOption(): void {
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/--wiki must be specified/' );
		$this->maintenance->execute();
	}

	public function testWhenNoUsersOnWiki(): void {
		$this->maintenance->setOption( 'wiki', WikiMap::getCurrentWikiId() );

		$this->mockEditCounter->expects( $this->never() )
			->method( 'getCount' );
		$this->mockEditCounter->expects( $this->never() )
			->method( 'recalculate' );

		$this->expectOutputString( "Processed all 0 users!\n" );
		$this->maintenance->execute();
	}

	public function testWhenNoUsersNeededUpdate(): void {
		$this->createCentralUserAttachedToWiki();

		// Mock that ::getCount and ::recalculate both return the same value
		// to similate no need for a change
		$this->mockEditCounter->expects( $this->once() )
			->method( 'getCount' )
			->willReturn( 42 );

		$this->mockEditCounter->expects( $this->once() )
			->method( 'recalculate' )
			->willReturn( 42 );

		$this->maintenance->setOption( 'wiki', WikiMap::getCurrentWikiId() );

		$this->expectOutputString( "Processed all 1 users!\n" );
		$this->maintenance->execute();
	}

	public function testForMultipleUsersSomeWhichNeedUpdating(): void {
		$firstUsername = $this->createCentralUserAttachedToWiki();
		$secondUsername = $this->createCentralUserAttachedToWiki();
		$thirdUsername = $this->createCentralUserAttachedToWiki();

		// Mock that the second test user has a different recalculated edit count
		// but all other users remain the same
		$this->mockEditCounter->expects( $this->exactly( 3 ) )
			->method( 'getCount' )
			->willReturnCallback( static fn ( $user ) => match ( $user->getName() ) {
				$firstUsername => 10,
				$secondUsername => 20,
				$thirdUsername => 30,
				default => throw new InvalidArgumentException( "Unexpected user: {$user->getName()}" ),
			} );

		$this->mockEditCounter->expects( $this->exactly( 3 ) )
			->method( 'recalculate' )
			->willReturnCallback( static fn ( $user ) => match ( $user->getName() ) {
				$firstUsername => 10,
				$secondUsername => 25,
				$thirdUsername => 30,
				default => throw new InvalidArgumentException( "Unexpected user: {$user->getName()}" ),
			} );

		$this->maintenance->loadWithArgv( [ '--batch-size', '2' ] );
		$this->maintenance->setOption( 'wiki', WikiMap::getCurrentWikiId() );

		$this->expectOutputRegex( "/Global edit count for '{$secondUsername}' updated from 20 to 25/" );
		$this->expectOutputRegex( "/Processed all 3 users!/" );
		$this->maintenance->execute();

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			"Global edit count for '$secondUsername' updated from 20 to 25",
			$actualOutput,
			'Second test user should be marked as having had their edit count updated'
		);
		$this->assertStringContainsString( "Processed 2 users so far ...\nProcessed all 3 users", $actualOutput );
	}
}
