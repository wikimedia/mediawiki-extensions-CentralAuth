<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore
 * @group Database
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalRenameRequestStoreTest extends MediaWikiIntegrationTestCase {

	public function testSave(): void {
		$store = new GlobalRenameRequestStore(
			$this->getMockDbManager(),
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, 'abcwiki' );

		$id = $this->getDb()->newSelectQueryBuilder()
			->select( 'rq_id' )
			->from( 'renameuser_queue' )
			->where( [
				'rq_name' => 'Example',
				'rq_wiki' => 'abcwiki',
				'rq_newname' => 'Test',
				'rq_reason' => 'I ate too many bananas.',
				'rq_status' => GlobalRenameRequest::PENDING,
				'rq_type' => GlobalRenameRequest::RENAME,
			] )
			->caller( __METHOD__ )
			->fetchField();

		$this->assertNotNull( $id );
		$this->assertEquals( $id, $request->getId() );

		$request->setStatus( GlobalRenameRequest::REJECTED );
		$store->save( $request );

		$this->assertEquals(
			GlobalRenameRequest::REJECTED,
			$this->getDb()->newSelectQueryBuilder()
				->select( 'rq_status' )
				->from( 'renameuser_queue' )
				->where( [ 'rq_id' => $request->getId() ] )
				->caller( __METHOD__ )
				->fetchField()
		);
	}

	public function testGetBlankRequest() {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$store = new GlobalRenameRequestStore(
			$this->createMock( CentralAuthDatabaseManager::class ),
			$userNameUtils
		);

		$blankRequest = $store->newBlankRequest();

		$this->assertInstanceOf( GlobalRenameRequest::class, $blankRequest );
		$this->assertEquals( $userNameUtils, TestingAccessWrapper::newFromObject( $blankRequest )->userNameUtils );
		$this->assertFalse( $blankRequest->exists() );
	}

	/**
	 * @dataProvider provideWiki
	 */
	public function testNewForUser( $wiki ) {
		$store = new GlobalRenameRequestStore(
			$this->getMockDbManager(),
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, $wiki );

		$retrieved = $store->newForUser( 'Example', $wiki );

		$this->assertEquals( $request->getId(), $retrieved->getId() );
		$this->assertEquals( $request->getReason(), $retrieved->getReason() );
	}

	/**
	 * @dataProvider provideWiki
	 */
	public function testNewFromId( $wiki ) {
		$store = new GlobalRenameRequestStore(
			$this->getMockDbManager(),
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, $wiki );
		$retrieved = $store->newFromId( $request->getId() );
		$this->assertEquals( $request->getReason(), $retrieved->getReason() );
	}

	public static function provideWiki(): array {
		return [
			'null' => [ null ],
			'set' => [ 'abcwiki' ],
		];
	}

	public function testNameHasPendingRequest() {
		$store = new GlobalRenameRequestStore(
			$this->getMockDbManager(),
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, 'abcwiki' );

		$this->assertTrue( $store->nameHasPendingRequest( $request->getNewName() ) );
		$this->assertFalse( $store->nameHasPendingRequest( 'not-' . $request->getNewName() ) );
	}

	/**
	 * @param GlobalRenameRequestStore $store
	 * @param string|null $wiki
	 * @return GlobalRenameRequest
	 */
	private function createSampleRequest( GlobalRenameRequestStore $store, $wiki ): GlobalRenameRequest {
		$request = $store->newBlankRequest();
		$request->setName( 'Example' );
		$request->setWiki( $wiki );
		$request->setNewName( 'Test' );
		$request->setReason( 'I ate too many bananas.' );
		$request->setType( GlobalRenameRequest::RENAME );
		$store->save( $request );
		return $request;
	}

	private function getMockDbManager(): CentralAuthDatabaseManager {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralPrimaryDB' )
			->willReturn( $this->getDb() );
		$dbManager->method( 'getCentralReplicaDB' )
			->willReturn( $this->getDb() );
		$dbManager->method( 'getCentralDBFromRecency' )
			->willReturn( $this->getDb() );
		return $dbManager;
	}

	private function allValidUserNameUtils(): UserNameUtils {
		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'getCanonical' )
			->willReturnCallback( static function ( string $newName, $rigor ) {
				return $newName;
			} );

		return $userNameUtils;
	}
}
