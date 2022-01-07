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

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use CentralAuthServices;
use Exception;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserNameUtils;
use MWException;
use Status;
use stdClass;

/**
 * Data access object for global rename requests.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 */
class GlobalRenameRequest {

	/** @var UserNameUtils */
	private $userNameUtils;

	public const PENDING = 'pending';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';

	/** @var int|null */
	protected $id;
	/** @var string|null */
	protected $name;
	/** @var string|null */
	protected $wiki;
	/** @var string|null */
	protected $newName;
	/** @var string|null */
	protected $reason;
	/** @var string|null */
	protected $requested;
	/** @var string|null */
	protected $status;
	/** @var string|null */
	protected $completed;
	/** @var int */
	protected $deleted = 0;
	/** @var int|null */
	protected $performer;
	/** @var string|null */
	protected $comments;

	/**
	 * @internal Use GlobalRenameRequestStore::newBlankRequest instead
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct( UserNameUtils $userNameUtils ) {
		$this->userNameUtils = $userNameUtils;
	}

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
	 * @return int CentralAuth user id of the user who processed the request
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
	 * @param int $id
	 */
	public function setId( int $id ) {
		if ( $this->id !== null ) {
			throw new MWException( "Can't replace id when already set" );
		}

		$this->id = $id;
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
		$canonicalName = $this->userNameUtils->getCanonical( $newName, UserNameUtils::RIGOR_CREATABLE );
		if ( $canonicalName === false ) {
			throw new Exception( "Invalid username '{$newName}'" );
		}
		$this->newName = $canonicalName;
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

	/**
	 * @internal
	 * @param stdClass $row Database row
	 */
	public function importRow( stdClass $row ) {
		$this->id = $row->id;
		$this->name = $row->name;
		$this->wiki = $row->wiki;
		$this->newName = $row->newname;
		$this->reason = $row->reason;
		$this->requested = $row->requested;
		$this->status = $row->status;
		$this->completed = $row->completed;
		$this->deleted = $row->deleted;
		$this->performer = $row->performer;
		$this->comments = $row->comments;
	}

	/**
	 * Check to see if a given username is available for use via CentralAuth.
	 *
	 * Note that this is not a definitive check. It does not include checking
	 * for AntiSpoof, TitleBlacklist or other AbortNewAccount hook blocks.
	 * Unfortunately the only canonical way to validate that an account is
	 * available is to make the account and check that it wasn't blocked by
	 * something.
	 *
	 * @param string $name
	 * @return Status Canonicalized name
	 */
	public static function isNameAvailable( $name ) {
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$safe = $userNameUtils->getCanonical( $name, UserNameUtils::RIGOR_CREATABLE );
		$status = Status::newGood( $safe );

		if ( $safe === false || $safe === '' ) {
			$status->fatal( 'globalrenamerequest-newname-err-invalid' );
			return $status;
		}

		if ( CentralAuthServices::getGlobalRenameRequestStore()->nameHasPendingRequest( $safe ) ) {
			$status->fatal( 'globalrenamerequest-newname-err-taken' );
			return $status;
		}

		// New user creation checks against local wiki only using an API
		// request, but we need to check against the central user table instead
		$centralUser = CentralAuthUser::getPrimaryInstanceByName( $safe );
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
