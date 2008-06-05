<?php

class CentralAuthBlock {
	private $mId, $mUser, $mUserText, $mUserObj,
		$mByText, $mReason, $mTimestamp,
		$mExpiry, $mBlockEmail;	// @fixme we need to implement email blocks

	public function __construct() {
		$this->mId =
		$this->mUser = 
		$this->mBlockEmail =
			0;
		$this->mUserText = 
		$this->mByText =
		$this->mReason =
		$this->mExpire =
			'';
		$this->mUserObj =
			null;
	}

	/** Constructors */
	public static function newFromRow( $row ) {
		if( !$row )
			return null;

		$block = new CentralAuthBlock();
		$block->mId = $row->gb_id;
		$block->mUser = $row->gb_user;
		$block->mUserText = $row->gb_user_text;
		$block->mByText = $row->gb_by_text;
		$block->mReason = $row->gb_reason;
		$block->mTimestamp = $row->gb_timestamp;
		$block->mExpiry = Block::decodeExpiry( $row->gb_expiry );
		$block->mBlockEmail = (bool)$row->gb_block_email;
		return $block;
	}

	public static function newFromUser( CentralAuthUser $u ) {
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$r = $dbr->select( 'globalblock', '*', array( 'gb_user' => $u->getId() ), __METHOD__ );
		$row = $dbr->fetchObject( $r );
		$dbr->freeResult( $r );
		return self::newFromRow( $row );
	}

	/** Getters/setters block */
	public function getId() { return $this->mId; }
	public function getUserText() { return $this->mUserText; }
	public function getUserId() { return $this->mUser; }
	public function getBy() { return $this->mByText; }
	public function setBy($s) { $this->mByText = $s; }
	public function getReason() { return $this->mReason; }
	public function setReason($r) { $this->mReason = $r; }
	public function getTimestamp() { return $this->mTimestamp; }
	public function getExpiry() { return $this->mExpiry; }
	public function getBlockEmail() { return $this->mBlockEmail; }
	public function setBlockEmail($b) { $this->mBlockEmail = $b; }
	public function setUser( CentralAuthUser $u ) {
		$this->mUserObj = $u;
		$this->mUser = $u->getId();
		$this->mUserText = $u->getName();
	}
	public function setExpiry( $expiry ) {
		$this->mExpiry = $expiry;
	}
	public function getUser() {
		return $this->mUserObj ? $this->mUserObj : new CentralAuthUser( $this->mUserText );
	}
	public function getByStripped() {
		$bits = explode( '@', $this->mByText, 2 );
		return $bits[0];
	}

	/**
	 * Deletes block if it exists
	 * @return false on failure, true on success
	 */
	public function delete() {
		if( wfReadOnly() )
			return false;

		$dbw = CentralAuthUser::getCentralDB();
		$dbw->delete( 'globalblock', array( 'gb_id' => $this->mId ), __METHOD__ );
		$dbw->commit();
		return $dbw->affectedRows() > 0;
	}

	/**
	 * Inserts a new block into the globalblock table.
	 * @return false on failure, true on success
	 */
	public function insert() {
		if( wfReadOnly() )
			return false;

		self::purgeExpired();
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->insert( 'globalblock',
			array(
				'gb_id' => 0,
				'gb_user' => $this->mUser,
				'gb_user_text' => $this->mUserText,
				'gb_by_text' => $this->mByText,
				'gb_reason' => $this->mReason,
				'gb_timestamp' => $dbw->timestamp(),
				'gb_expiry' => Block::encodeExpiry( $this->mExpiry, $dbw ),
				'gb_block_email' => 0,	//is not implemented yet
			), __METHOD__, array( 'IGNORE' )
		);
		return $dbw->affectedRows() > 0;
	}

	/**
	 * Checks if block is expired.
	 * @return boolean
	 */
	public function isExpired() {
		if( !$this->mExpiry ) {
			return false;
		}
		return wfTimestampNow() > $this->mExpiry;
	}

	/**
	 * Deletes block if it's expired
	 * @return boolean
	 */
	public function deleteIfExpired() {
		if( $this->isExpired() ) {
			$this->delete();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Deletes all expired blocks.
	 */
	public static function purgeExpired() {
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->delete( 'globalblock', array( 'gb_expiry < ' . $dbw->addQuotes( $dbw->timestamp() ) ), __METHOD__ );
	}
}