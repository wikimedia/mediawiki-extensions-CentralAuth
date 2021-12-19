<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore
 * @group Database
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalRenameRequestStoreTest extends CentralAuthUsingDatabaseTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstructor() {
		$this->assertInstanceOf(
			GlobalRenameRequestStore::class,
			new GlobalRenameRequestStore(
				$this->createMock( CentralAuthDatabaseManager::class ),
				$this->createMock( UserNameUtils::class )
			)
		);
	}

	/**
	 * @covers ::save
	 */
	public function testSave(): void {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->with( DB_PRIMARY )
			->willReturn( $this->db );

		$store = new GlobalRenameRequestStore(
			$dbManager,
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, 'abcwiki' );

		$id = $this->db->selectField(
			'renameuser_queue',
			'rq_id',
			[
				'rq_name' => 'Example',
				'rq_wiki' => 'abcwiki',
				'rq_newname' => 'Test',
				'rq_reason' => 'I ate too many bananas.',
				'rq_status' => GlobalRenameRequest::PENDING,
			],
			__METHOD__
		);

		$this->assertNotNull( $id );
		$this->assertEquals( $id, $request->getId() );

		$request->setStatus( GlobalRenameRequest::REJECTED );
		$store->save( $request );

		$this->assertEquals(
			GlobalRenameRequest::REJECTED,
			$this->db->selectField(
				'renameuser_queue',
				'rq_status',
				[ 'rq_id' => $request->getId() ],
				__METHOD__
			)
		);
	}

	/**
	 * @covers ::newBlankRequest
	 */
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
	 * @covers ::newForUser
	 * @covers ::fetchRowFromDB
	 * @covers ::newFromRow
	 * @dataProvider provideWiki
	 */
	public function testNewForUser( $wiki ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->willReturn( $this->db );

		$store = new GlobalRenameRequestStore(
			$dbManager,
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, $wiki );

		$retrieved = $store->newForUser( 'Example', $wiki );

		$this->assertEquals( $request->getId(), $retrieved->getId() );
		$this->assertEquals( $request->getReason(), $retrieved->getReason() );
	}

	/**
	 * @covers ::newFromId
	 * @covers ::fetchRowFromDB
	 * @covers ::newFromRow
	 * @dataProvider provideWiki
	 */
	public function testNewFromId( $wiki ) {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->willReturn( $this->db );

		$store = new GlobalRenameRequestStore(
			$dbManager,
			$this->allValidUserNameUtils()
		);

		$request = $this->createSampleRequest( $store, $wiki );
		$retrieved = $store->newFromId( $request->getId() );
		$this->assertEquals( $request->getReason(), $retrieved->getReason() );
	}

	public function provideWiki(): array {
		return [
			'null' => [ null ],
			'set' => [ 'abcwiki' ],
		];
	}

	/**
	 * @covers ::nameHasPendingRequest
	 */
	public function testNameHasPendingRequest() {
		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->willReturn( $this->db );

		$store = new GlobalRenameRequestStore(
			$dbManager,
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
		$store->save( $request );
		return $request;
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
