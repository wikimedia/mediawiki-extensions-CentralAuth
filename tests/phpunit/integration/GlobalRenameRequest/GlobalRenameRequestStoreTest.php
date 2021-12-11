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
				$this->createMock( CentralAuthDatabaseManager::class )
			)
		);
	}

	/**
	 * @covers ::save
	 */
	public function testSave(): void {
		$request = new GlobalRenameRequest;
		$request->setName( 'Example' );
		$request->setWiki( 'abcwiki' );
		$request->setNewName( 'Test' );
		$request->setReason( 'I ate too many bananas.' );

		$dbManager = $this->createMock( CentralAuthDatabaseManager::class );
		$dbManager->method( 'getCentralDB' )
			->with( DB_PRIMARY )
			->willReturn( $this->db );

		$store = new GlobalRenameRequestStore( $dbManager );
		$store->save( $request );

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

}
