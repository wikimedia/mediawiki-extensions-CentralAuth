<?php
/**
 * @section LICENSE
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

use Wikimedia\Rdbms\IDatabase;

/**
 * Data access object for global rename requests.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 */
class GlobalRenameRequest {

	const PENDING = 'pending';
	const APPROVED = 'approved';
	const REJECTED = 'rejected';

	protected $id;
	protected $name;
	protected $wiki;
	protected $newName;
	protected $reason;
	protected $requested;
	protected $status;
	protected $completed;
	protected $deleted;
	protected $performer;
	protected $comments;

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string Requesting user's name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string Requesting user's home wiki or null if CentralAuth user
	 */
	public function getWiki() {
		return $this->wiki;
	}

	/**
	 * @return string
	 */
	public function getNewName() {
		return $this->newName;
	}

	/**
	 * @return string User's reason for requesting rename
	 */
	public function getReason() {
		return $this->reason;
	}

	/**
	 * @return string MW timestamp that request was made
	 */
	public function getRequested() {
		return $this->requested;
	}

	/**
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return string MW timestamp that request was processed
	 */
	public function getCompleted() {
		return $this->completed;
	}

	/**
	 * @return int Protection flags
	 */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * @return int CentralAuth user id of stweard wo processed request
	 */
	public function getPerformer() {
		return $this->performer;
	}

	/**
	 * @return string
	 */
	public function getComments() {
		return $this->comments;
	}

	/**
	 * @param string $name
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setName( $name ) {
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string $wiki
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setWiki( $wiki ) {
		$this->wiki = $wiki;
		return $this;
	}

	/**
	 * @param string $newName
	 * @throws Exception if an invalid username is provided
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setNewName( $newName ) {
		$newName = User::getCanonicalName( $newName, 'creatable' );
		if ( $newName === false ) {
			throw new Exception( "Invalid username '{$newName}'" );
		}
		$this->newName = $newName;
		return $this;
	}

	/**
	 * @param string $reason
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setReason( $reason ) {
		$this->reason = $reason;
		return $this;
	}

	/**
	 * @param string|null $requested MW timestamp, null for now
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setRequested( $requested = null ) {
		if ( $requested === null ) {
			$requested = wfTimestampNow();
		}
		$this->requested = $requested;
		return $this;
	}

	/**
	 * @param string $status
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setStatus( $status ) {
		$this->status = $status;
		return $this;
	}

	/**
	 * @param string|null $completed MW timestamp, null for now
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setCompleted( $completed = null ) {
		if ( $completed === null ) {
			$completed = wfTimestampNow();
		}
		$this->completed = $completed;
		return $this;
	}

	/**
	 * @param int $deleted Bitmask
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setDeleted( $deleted ) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @param int $performer
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setPerformer( $performer ) {
		$this->performer = $performer;
		return $this;
	}

	/**
	 * @param string $comments
	 * @return GlobalRenameRequest self, for message chaining
	 */
	public function setComments( $comments ) {
		$this->comments = $comments;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function exists() {
		return $this->id !== null;
	}

	/**
	 * @return bool
	 */
	public function isPending() {
		return $this->status === self::PENDING;
	}

	/**
	 * @return bool
	 */
	public function userIsGlobal() {
		return $this->wiki === null;
	}

	public function save() {
		$dbw = self::getDB( DB_MASTER );
		if ( $this->id === null ) {
			$this->requested = wfTimestampNow();
			$this->status = self::PENDING;
			$dbw->insert(
				'renameuser_queue',
				[
					'rq_name'         => $this->name,
					'rq_wiki'         => $this->wiki,
					'rq_newname'      => $this->newName,
					'rq_reason'       => $this->reason,
					'rq_requested_ts' => $this->requested,
					'rq_status'       => $this->status,
				],
				__METHOD__
			);
			$this->id = $dbw->insertId();
		} else {
			$dbw->update(
				'renameuser_queue',
				[
					'rq_name'         => $this->name,
					'rq_wiki'         => $this->wiki,
					'rq_newname'      => $this->newName,
					'rq_reason'       => $this->reason,
					'rq_requested_ts' => $this->requested,
					'rq_status'       => $this->status,
					'rq_completed_ts' => $this->completed,
					'rq_deleted'      => $this->deleted,
					'rq_performer'    => $this->performer,
					'rq_comments'     => $this->comments,
				],
				[ 'rq_id' => $this->id ],
				__METHOD__
			);
		}

		return $dbw->affectedRows() === 1;
	}

	/**
	 * Get the pending rename request for the given user and wiki.
	 *
	 * @param string $username
	 * @param string $wiki
	 * @return GlobalRenameRequest
	 */
	public static function newForUser( $username, $wiki ) {
		return self::newFromRow(
			self::fetchRowFromDB( [
				'rq_name'   => $username,
				'rq_wiki'   => $wiki,
				'rq_status' => self::PENDING,
			] )
		);
	}

	/**
	 * Get a request record.
	 *
	 * @param int $id Request id
	 * @return GlobalRenameRequest
	 */
	public static function newFromId( $id ) {
		return self::newFromRow(
			self::fetchRowFromDB( [
				'rq_id' => $id,
			] )
		);
	}

	/**
	 * Fetch a single request from the database.
	 *
	 * @param array $where Where clause criteria
	 * @return stdClass|bool Row as object or false if not found
	 */
	protected static function fetchRowFromDB( array $where ) {
		return self::getDB( DB_REPLICA )->selectRow(
			'renameuser_queue',
			[
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
			],
			$where,
			__METHOD__
		);
	}

	/**
	 * Factory to build a GlobalRenameRequest from a database result.
	 *
	 * @param stdClass|bool $row Database result
	 * @return GlobalRenameRequest
	 */
	public static function newFromRow( $row ) {
		$req = new GlobalRenameRequest;
		if ( $row !== false ) {
			$req->id = $row->id;
			$req->name = $row->name;
			$req->wiki = $row->wiki;
			$req->newName = $row->newname;
			$req->reason = $row->reason;
			$req->requested = $row->requested;
			$req->status = $row->status;
			$req->completed = $row->completed;
			$req->deleted = $row->deleted;
			$req->performer = $row->performer;
			$req->comments = $row->comments;
		}
		return $req;
	}

	/**
	 * Get a Database object for the CentralAuth db
	 *
	 * @param int $type DB_REPLICA or DB_MASTER
	 * @return IDatabase
	 */
	protected static function getDB( $type ) {
		if ( $type === DB_MASTER ) {
			return CentralAuthUtils::getCentralDB();
		} else {
			return CentralAuthUtils::getCentralReplicaDB();
		}
	}

	/**
	 * Check to see if there is a pending rename request to the given name.
	 *
	 * @param string $newname
	 * @return bool
	 */
	public static function nameHasPendingRequest( $newname ) {
		$dbw = self::getDB( DB_REPLICA );
		$res = $dbw->selectField(
			'renameuser_queue',
			'rq_id',
			[
				'rq_newname' => $newname,
				'rq_status'  => self::PENDING,
			],
			__METHOD__
		);

		return $res !== false;
	}

	/**
	 * Check to see if a given username is available for use via CentralAuth.
	 *
	 * Note that this is not a definiative check. It does not include checking
	 * for AntiSpoof, TitleBlacklist or other AbortNewAccount hook blocks.
	 * Unfortunately the only cannonical way to validate that an account is
	 * available is to make the account and check that it wasn't blocked by
	 * something.
	 *
	 * @param string $name
	 * @return Status Canonicalized name
	 */
	public static function isNameAvailable( $name ) {
		$safe = User::getCanonicalName( $name, 'creatable' );
		$status = Status::newGood( $safe );

		if ( $safe === false || $safe === '' ) {
			$status->fatal( 'globalrenamerequest-newname-err-invalid' );
			return $status;
		}

		if ( self::nameHasPendingRequest( $safe ) ) {
			$status->fatal( 'globalrenamerequest-newname-err-taken' );
			return $status;
		}

		// New user creation checks against local wiki only using an API
		// request, but we need to check against te central user table instead
		$centralUser = CentralAuthUser::getMasterInstanceByName( $safe );
		if ( $centralUser->exists() || $centralUser->listUnattached() ) {
			$status->fatal( 'globalrenamerequest-newname-err-taken' );
			return $status;
		}

		// Check to see if there is an active rename to the desired name.
		$progress = $centralUser->renameInProgress();
		if ( $progress && $safe == $progress[1] ) {
			$status->fatal( 'globalrenamerequest-newname-err-taken' );
			return $status;
		}

		return $status;
	}

} // end GlobalRenameRequest
