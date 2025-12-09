<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Tests\Unit\GlobalRenameJob;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameJob\GlobalVanishJob;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUser;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Status\Status;
use MediaWiki\Tests\MockDatabase;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameJob\GlobalVanishJob
 * @group Database
 * @copyright © 2025 Wikimedia Foundation and contributors
 */
class GlobalVanishJobTest extends MediaWikiIntegrationTestCase {

	/** @var MockObject&GlobalRenameRequestStore */
	private $store;

	/** @var MockObject&GlobalRenameFactory */
	private $factory;

	/** @var MockObject&GlobalRenameUser */
	private $globalRenameUser;

	/**
	 * @inheritDoc
	 */
	public function setUp(): void {
		$this->store = $this->getGlobalRenameRequestStore();
		$this->factory = $this->getGlobalRenameFactory();
		$this->globalRenameUser = $this->createMock( GlobalRenameUser::class );

		$this->factory->method( 'newGlobalRenameUser' )->willReturn( $this->globalRenameUser );

		// The job class accesses services using the static instance methods,
		// so we need to redefine them.
		$this->setService( 'CentralAuth.GlobalRenameFactory', fn () => $this->factory );
		$this->setService( 'CentralAuth.GlobalRenameRequestStore', fn () => $this->store );
	}

	/**
	 * Ensures that a well-formed vanish request gets processed by the job.
	 */
	public function testGlobalVanishJob(): void {
		$testUser = $this->mockCentralAuthUser( '1001', 'TestUser' );
		$performer = $this->mockCentralAuthUser( '1002', 'Performer' );

		$request = $this->store->newBlankRequest()
			->setName( $testUser->getName() )
			->setNewName( 'Renamed user 1efab4f82efc59fa57da58612428420b' )
			->setReason( 'User provided reason.' )
			->setType( GlobalRenameRequest::VANISH );
		$request->setId( 1000 );

		$this->store->method( 'newFromId' )->with( 1000 )->willReturn( $request );

		// Expect a rename to be attempted as all of the data being fed into
		// the job is valid.
		$this->globalRenameUser->method( 'withLockPerformingUser' )->willReturn( $this->globalRenameUser );
		$this->globalRenameUser->method( 'rename' )->willReturn( Status::newGood() );
		$this->globalRenameUser->expects( $this->once() )->method( 'rename' );

		$this->getServiceContainer()->getJobQueueGroup()->push(
			GlobalVanishJob::newSpec( $request, $performer->getName() )
		);
		$this->runJobs();
	}

	/**
	 * Ensures that a vanish doesn't get processed if the request is missing.
	 */
	public function testGlobalVanishJobMissingRequest(): void {
		$performer = $this->mockCentralAuthUser( '1002', 'Performer' );

		// If a request doesn't exist then a blank request gets returned, and
		// that's what the job will be checking for.
		$this->store->method( 'newFromId' )->willReturn( $this->store->newBlankRequest() );

		// The rename should not be attempted since the request is missing.
		$this->globalRenameUser->expects( $this->never() )->method( 'rename' );

		$job = new GlobalVanishJob( [
			'globalRenameRequestId' => 1000,
			'renamer' => $performer->getName(),
		] );
		$this->assertFalse( $job->run() );
	}

	/**
	 * @return MockObject&GlobalRenameUserStatus
	 */
	private function getGlobalRenameUserStatus( string $name ) {
		return $this->getMockBuilder( GlobalRenameUserStatus::class )
			->onlyMethods( ( [ 'getNames' ] ) )
			->setConstructorArgs( [ $this->getMockDbManager(), $name ] )
			->getMock();
	}

	/**
	 * @return MockObject&GlobalRenameRequestStore
	 */
	private function getGlobalRenameRequestStore() {
		return $this->getMockBuilder( GlobalRenameRequestStore::class )
			->onlyMethods( [ 'newFromId' ] )
			->setConstructorArgs( [
				$this->getMockDbManager(),
				$this->allValidUserNameUtils(),
			] )
			->getMock();
	}

	/**
	 * @return MockObject&GlobalRenameFactory
	 */
	private function getGlobalRenameFactory() {
		return $this->getMockBuilder( GlobalRenameFactory::class )
			->onlyMethods( [ 'newGlobalRenameUser', 'newGlobalRenameUserStatus' ] )
			->setConstructorArgs( [
				$this->createMock( JobQueueGroupFactory::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( CentralAuthAntiSpoofManager::class ),
				$this->createMock( CentralAuthDatabaseManager::class ),
			] )
			->getMock();
	}

	/**
	 * @return MockObject&CentralAuthDatabaseManager
	 */
	private function getMockDbManager() {
		$mockDb = new MockDatabase();

		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralPrimaryDB' )->willReturn( $mockDb );
		$dbManager->method( 'getCentralReplicaDB' )->willReturn( $mockDb );
		$dbManager->method( 'getCentralDBFromRecency' )->willReturn( $mockDb );

		return $dbManager;
	}

	/**
	 * @return MockObject&UserNameUtils
	 */
	private function allValidUserNameUtils() {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'getCanonical' )
			->willReturnCallback( static function ( string $newName, $rigor ) {
				return $newName;
			} );

		return $userNameUtils;
	}

	private function mockCentralAuthUser( string $id, string $name ): CentralAuthUser {
		$user = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => $id ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
				[ 'enwiki', 'primary' ],
				[ 'dewiki', 'login' ],
				[ 'metawiki', 'password' ],
			]
		);
		$user->save( $this->getDb() );

		return CentralAuthUser::getInstanceByName( $name );
	}
}
