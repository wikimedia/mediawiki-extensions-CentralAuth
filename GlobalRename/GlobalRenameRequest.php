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

	/**
	 * Get a DatabaseBase object for the CentralAuth db
	 *
	 * @param int $type DB_SLAVE or DB_MASTER
	 * @return DatabaseBase
	 */
	protected static function getDB( $type = DB_SLAVE ) {
		if ( $type === DB_MASTER ) {
			return CentralAuthUser::getCentralDB();
		} else {
			return CentralAuthUser::getCentralSlaveDB();
		}
	}

	/**
	 * Create a new rename request.
	 *
	 * @param string $username User making request
	 * @param string $wiki Home wiki of requesting user
	 * @param string $newname Reqested name
	 * @param string $reason User's explanation for rename request
	 * @return bool True if successful, false otherwise
	 */
	public static function createRequest (
		$username, $wiki, $newname, $reason
	) {
		$dbw = self::getDB( DB_MASTER );

		$dbw->insert(
			'renameuser_queue',
			array(
				'rq_name'         => $username,
				'rq_wiki'         => $wiki,
				'rq_newname'      => $newname,
				'rq_reason'       => $reason,
				'rq_requested_ts' => $dbw->timestamp(),
				'rq_status'       => self::PENDING,
			),
			__METHOD__
		);

		return $dbw->affectedRows() === 1;
	}

	/**
	 * Check to see if there is a pending rename request for the given user and
	 * wiki.
	 *
	 * @param string $username
	 * @param string $wiki
	 * @return int|bool Request id or false if none found
	 */
	public static function userHasPendingRequest( $username, $wiki ) {
		$dbw = self::getDB( DB_SLAVE );
		$res = $dbw->select(
			'renameuser_queue',
			array( 'rq_id' ),
			array(
				'rq_name'   => $username,
				'rq_wiki'   => $wiki,
				'rq_status' => self::PENDING,
			),
			__METHOD__
		);

		return ( $res->numRows() === 0 ) ? false : $res->fetchObject()->rq_id;
	}

	/**
	 * Get a request record.
	 *
	 * @param int $id Request id
	 * @return array Request
	 */
	public static function getRequest( $id ) {
		$dbw = self::getDB( DB_SLAVE );
		$res = $dbw->select(
			'renameuser_queue',
			array(
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
			),
			array( 'rq_id'   => $id, ),
			__METHOD__
		);
		return $res->fetchRow();
	}

	/**
	 * Check to see if there is a pending rename request to the given name.
	 *
	 * @param string $newname
	 * @return bool
	 */
	public static function nameHasPendingRequest( $newname ) {
		$dbw = self::getDB( DB_SLAVE );
		$res = $dbw->select(
			'renameuser_queue',
			array( 'rq_id' ),
			array(
				'rq_newname' => $newname,
				'rq_status'  => self::PENDING,
			),
			__METHOD__
		);

		return $res->numRows() !== 0;
	}

	/**
	 * Check to see if a given username is available for use via CentralAuth.
	 *
	 * Note that this is not a definiative check. It does not include checking
	 * for AntiSpoof, TitleBlacklist or other AbortNewAccount hook blocks.
	 *
	 * @param string $name
	 * @return Status Canonicalized name
	 */
	public static function isNameAvailable ( $name ) {
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

		// New user creation checks against local wiki only using an api, but we
		// need to check against te central user table instead
		$centralUser = new CentralAuthUser( $safe );
		if (
			$centralUser->exists() ||
			$centralUser->renameInProgressOn( wfWikiID() ) ||
			$centralUser->listUnattached()
		) {
			$status->fatal( 'globalrenamerequest-newname-err-taken' );
			return $status;
		}

		return $status;
	}

} //end GlobalRenameRequest
