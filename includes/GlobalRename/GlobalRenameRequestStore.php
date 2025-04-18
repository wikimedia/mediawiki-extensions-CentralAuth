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

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\User\UserNameUtils;
use stdClass;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Stores and loads GlobalRenameRequest objects in a database.
 *
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalRenameRequestStore {

	private CentralAuthDatabaseManager $dbManager;
	private UserNameUtils $userNameUtils;

	public function __construct(
		CentralAuthDatabaseManager $dbManager,
		UserNameUtils $userNameUtils
	) {
		$this->dbManager = $dbManager;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * Persists the given global rename request to the central database.
	 * @param GlobalRenameRequest $request
	 * @return bool
	 */
	public function save( GlobalRenameRequest $request ): bool {
		$dbw = $this->dbManager->getCentralPrimaryDB();
		if ( $request->getId() === null ) {
			$request->setRequested( wfTimestampNow() );

			// Default to pending if unspecified, but otherwise use the
			// provided status. This is needed for automatic account vanishing
			// record keeping.
			if ( $request->getStatus() === null ) {
				$request->setStatus( GlobalRenameRequest::PENDING );
			}

			$row = [
				'rq_name'         => $request->getName(),
				'rq_wiki'         => $request->getWiki(),
				'rq_newname'      => $request->getNewName(),
				'rq_reason'       => $request->getReason(),
				'rq_requested_ts' => $dbw->timestamp( $request->getRequested() ),
				'rq_status'       => $request->getStatus(),
				'rq_performer'    => $request->getPerformer(),
				'rq_comments'     => $request->getComments(),
				'rq_type'         => $request->getType() || GlobalRenameRequest::RENAME,
			];

			// Ensure there's a completed timestamp if a pre-approved request
			// is being saved to the database.
			if ( $request->getStatus() === GlobalRenameRequest::APPROVED ) {
				if ( $request->getCompleted() === null ) {
					$request->setCompleted( wfTimestampNow() );
				}
				$row['rq_completed_ts'] = $dbw->timestamp( $request->getCompleted() );
			}

			$dbw->newInsertQueryBuilder()
				->insertInto( 'renameuser_queue' )
				->row( $row )
				->caller( __METHOD__ )
				->execute();

			$request->setId( $dbw->insertId() );
		} else {
			$dbw->newUpdateQueryBuilder()
				->update( 'renameuser_queue' )
				->set( [
					'rq_name'         => $request->getName(),
					'rq_wiki'         => $request->getWiki(),
					'rq_newname'      => $request->getNewName(),
					'rq_reason'       => $request->getReason(),
					'rq_requested_ts' => $dbw->timestamp( $request->getRequested() ),
					'rq_status'       => $request->getStatus(),
					'rq_completed_ts' => $dbw->timestamp( $request->getCompleted() ),
					'rq_deleted'      => $request->getDeleted(),
					'rq_performer'    => $request->getPerformer(),
					'rq_comments'     => $request->getComments(),
					'rq_type'         => $request->getType() || GlobalRenameRequest::RENAME,
				] )
				->where( [
					'rq_id' => $request->getId()
				] )
				->caller( __METHOD__ )
				->execute();
		}

		return $dbw->affectedRows() === 1;
	}

	/**
	 * Creates a new GlobalRenameRequest object without any filled data.
	 */
	public function newBlankRequest(): GlobalRenameRequest {
		return new GlobalRenameRequest( $this->userNameUtils );
	}

	/**
	 * Get the pending rename request for the given user and wiki.
	 *
	 * @param string $username
	 * @param string|null $wiki
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return GlobalRenameRequest
	 */
	public function newForUser( string $username, $wiki, int $flags = IDBAccessObject::READ_NORMAL ) {
		return $this->newFromRow(
			$this->fetchRowFromDB( [
				'rq_name'   => $username,
				'rq_wiki'   => $wiki,
				'rq_status' => GlobalRenameRequest::PENDING,
			], $flags )
		);
	}

	/**
	 * Get a request record.
	 *
	 * @param int $id Request id
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return GlobalRenameRequest
	 */
	public function newFromId( int $id, int $flags = IDBAccessObject::READ_NORMAL ) {
		return $this->newFromRow(
			$this->fetchRowFromDB( [
				'rq_id' => $id,
			], $flags )
		);
	}

	/**
	 * Check to see if there is a pending rename request to the given name.
	 *
	 * @param string $newname
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return bool
	 */
	public function nameHasPendingRequest( string $newname, int $flags = IDBAccessObject::READ_NORMAL ) {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );

		$res = $dbr->newSelectQueryBuilder()
			->select( 'rq_id' )
			->from( 'renameuser_queue' )
			->where( [
				'rq_newname' => $newname,
				'rq_status'  => GlobalRenameRequest::PENDING,
			] )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchField();

		return $res !== false;
	}

	/**
	 * Check to see if there is a pending rename request to the given (current) name.
	 *
	 * @param string $name
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return bool
	 */
	public function currentNameHasPendingRequest( string $name, int $flags = IDBAccessObject::READ_NORMAL ) {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );

		$res = $dbr->newSelectQueryBuilder()
			->select( 'rq_id' )
			->from( 'renameuser_queue' )
			->where( [
				'rq_name' => $name,
				'rq_status'  => GlobalRenameRequest::PENDING,
			] )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchField();

		return $res !== false;
	}

	/**
	 * Check to see if there is an approved vanish request for the given (previous) name.
	 *
	 * @param string $name
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return bool
	 */
	public function currentNameHasApprovedVanish( string $name, int $flags = IDBAccessObject::READ_NORMAL ) {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );

		$res = $dbr->newSelectQueryBuilder()
			->select( 'rq_id' )
			->from( 'renameuser_queue' )
			->where( [
				'rq_name' => $name,
				'rq_status' => GlobalRenameRequest::APPROVED,
				'rq_type' => GlobalRenameRequest::VANISH,
			] )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchField();

		return $res !== false;
	}

	/**
	 * Fetch a single request from the database.
	 *
	 * @param array $where Where clause criteria
	 * @param int $flags One of the IDBAccessObject::READ_* constants
	 * @return stdClass|false Row as object or false if not found
	 */
	protected function fetchRowFromDB( array $where, int $flags = IDBAccessObject::READ_NORMAL ) {
		$dbr = $this->dbManager->getCentralDBFromRecency( $flags );

		return $dbr->newSelectQueryBuilder()
			->select( [
				'id'        => 'rq_id',
				'name'      => 'rq_name',
				'wiki'      => 'rq_wiki',
				'newname'   => 'rq_newname',
				'reason'    => 'rq_reason',
				'requested' => 'rq_requested_ts',
				'status'    => 'rq_status',
				'completed' => 'rq_completed_ts',
				'deleted'   => 'rq_deleted',
				'performer' => 'rq_performer',
				'comments'  => 'rq_comments',
				'type'      => 'rq_type',
			] )
			->from( 'renameuser_queue' )
			->where( $where )
			->recency( $flags )
			->caller( __METHOD__ )
			->fetchRow();
	}

	/**
	 * Creates a new GlobalRenameRequest object from a database row.
	 *
	 * @param stdClass|false $row Database result
	 * @return GlobalRenameRequest
	 */
	protected function newFromRow( $row ): GlobalRenameRequest {
		$request = $this->newBlankRequest();

		if ( $row ) {
			$request->importRow( $row );
		}

		return $request;
	}
}
