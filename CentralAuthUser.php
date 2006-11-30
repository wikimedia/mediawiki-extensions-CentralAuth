<?php

/*

likely construction types...

- give me the global account for this local user id
- none? give me the global account for this name

- create me a global account for this name

*/


class CentralAuthUser {
	/**
	 * Look up the global user entry for the given local User object
	 */
	static function newFromUser( User $user ) {
		global $wgDBname;
		return CentralAuthUser::newFromLocal( $wgDBname, $user->getId() );
	}
	
	/**
	 * Look up the global user entry for a local DB's account,
	 * or return NULL if the account is not attached to a global
	 * account.
	 */
	function newFromLocal( $dbname, $userid ) {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		$username = $dbr->selectField(
			array( self::tableName( 'globaluser' ), self::tableName( 'localuser' ) ),
			'gu_name',
			array(
				'gu_id=lu_global_id',
				'lu_dbname' => $dbname,
				'lu_local_id' => $userid,
			),
			__METHOD__ );
		if( $username === false ) {
			return null;
		} else {
			return new CentralAuthUser( $username );
		}
	}
	
	/**
	 * Return the global user object for a given username.
	 */
	function newFromName( $username ) {
		return new CentralAuthUser( $username );
	}
	
	function __construct( $username ) {
		$this->mName = $username;
	}
	
	static function tableName( $name ) {
		global $wgCentralAuthDatabase;
		return
			'`' .
			$wgCentralAuthDatabase .
			'`.`' .
			$name .
			'`';
	}
	
	/**
	 * this code is crap
	 */
	function getId() {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		$id = $dbr->selectField(
			self::tableName( 'globaluser' ),
			'gu_id',
			array( 'gu_name' => $this->mName ),
			__METHOD__ );
		return $id;
	}
	
	/**
	 * Check whether a global user account for this name exists yet.
	 * If migration state is set for pass 1, this may trigger lazy
	 * evaluation of automatic migration for the account.
	 *
	 * @return bool
	 */
	function exists() {
		global $wgCentralAuthState;
		
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->begin();
		$id = $this->getId();
		if( $id == 0 && $wgCentralAuthState == 'pass1' ) {
			// Global accounts may not all be in place yet.
			// Try automerging first, then check again.
			$migrated = $user->attemptAutoMigration();
			$id = $this->getId();
		}
		$dbw->commit();
		wfDebugLog( 'CentralAuth', "exists() for '$this->mName': $id" );
		return $id != 0;
	}
	
	/**
	 * this code is crapper
	 */
	function register( $password ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		list( $salt, $hash ) = $this->saltedPassword( $password );
		$ok = $dbw->insert(
			self::tableName( 'globaluser' ),
			array(
				'gu_name'  => $this->mName,
				
				'gu_email' => null, // FIXME
				'gu_email_authenticated' => null, // FIXME
				
				'gu_salt'     => $salt,
				'gu_password' => $hash,
				
				'gu_locked' => 0,
				'gu_hidden' => 0,
				
				'gu_registration' => $dbw->timestamp(),
			),
			__METHOD__ );
		
		if( $ok ) {
			wfDebugLog( 'CentralAuth',
				"registered global account '$this->mName'" );
		} else {
			wfDebugLog( 'CentralAuth',
				"registration failed for global account '$this->mName'" );
		}
		return $ok;
	}
	
	/**
	 * For use in migration pass zero.
	 * Store local user data into the auth server's migration table.
	 */
	static function storeLocalData( $dbname, $row, $editCount ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$ok = $dbw->insert(
			self::tableName( 'localuser' ),
			array(
				'lu_global_id'          => null, // Not yet migrated!
				'lu_dbname'             => $dbname,
				'lu_local_id'           => $row->user_id,
				'lu_migrated_name'      => $row->user_name,
				'lu_migrated_password'  => $row->user_password,
				'lu_migrated_email'     => $row->user_email,
				'lu_migrated_email_authenticated' => $row->user_email_authenticated,
				'lu_migrated_editcount' => $editCount,
			),
			__METHOD__ );
		wfDebugLog( 'CentralAuth',
			"stored migration data for '$row->user_name' on $dbname" );
	}
	
	/**
	 * For use in migration pass one.
	 * Store global user data in the auth server's main table.
	 */
	function storeGlobalData( $salt, $hash, $email, $emailAuth ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->insert( self::tableName( 'globaluser' ),
			array(
				'gu_name' => $this->mName,
				'gu_salt' => $salt,
				'gu_password' => $hash,
				'gu_email' => $email,
				'gu_email_authenticated' => $emailAuth,
			),
			__METHOD__,
			array( 'IGNORE' ) );
		
		return $dbw->affectedRows() != 0;
	}
	
	function storeAndMigrate() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->begin();
		
		$ret = $this->attemptAutoMigration();
		
		$dbw->commit();
		return $ret;
	}
	
	/**
	 * Pick a winning master account and try to auto-merge as many as possible.
	 * @fixme add some locking or something
	 */
	function attemptAutoMigration() {
		$rows = $this->fetchUnattached();
		
		if( !$rows ) {
			wfDebugLog( 'CentralAuth',
				"Attempted migration with no unattached for '$this->mName'" );
			return false;
		}
		
		$winner = false;
		$max = -1;
		$attach = array();
		
		// We have to pick a master account
		// The winner is the one with the most edits, usually
		foreach( $rows as $row ) {
			if( $row->lu_migrated_editcount > $max ) {
				$winner = $row;
				$max = $row->lu_migrated_editcount;
			}
		}
		assert( isset( $winner ) );
		
		// If the primary account has an e-mail address set,
		// we can use it to match other accounts. If it doesn't,
		// we can't be sure that the other accounts with no mail
		// are the same person, so err on the side of caution.
		$winningMail = ($winner->lu_migrated_email == ''
			? false
			: $winner->lu_migrated_email);
		
		foreach( $rows as $row ) {
			if( $row->lu_dbname == $winner->lu_dbname ) {
				// Primary account holder... duh
				$attach[$row->lu_dbname] = 'primary';
			} elseif( $row->lu_migrated_email === $winningMail ) {
				// Same e-mail as primary means we know they could
				// reset their password, so we give them the account.
				$attach[$row->lu_dbname] = 'mail';
			} elseif( $row->lu_migrated_editcount == 0 ) {
				// Unused accounts are fair game for reclaiming
				$attach[$row->lu_dbname] = 'empty';
			} else {
				// Can't automatically resolve this account.
				//
				// If the password matches, it will be automigrated
				// at next login. If no match, user will have to input
				// the conflicting password or deal with the conflict.
			}
		}

		$ok = $this->storeGlobalData(
				$winner->lu_local_id,
				$winner->lu_migrated_password,
				$winner->lu_migrated_email,
				$winner->lu_migrated_email_authenticated );
		
		if( !$ok ) {
			wfDebugLog( 'CentralAuth',
				"attemptedAutoMigration for existing entry '$this->mName'" );
			return false;
		}
		
		if( count( $attach ) < count( $rows ) ) {
			wfDebugLog( 'CentralAuth',
				"Incomplete migration for '$this->mName'" );
		} else {
			if( count( $rows ) == 1 ) {
				wfDebugLog( 'CentralAuth',
					"Singleton migration for '$this->mName' on $winner->lu_dbname" );
			} else {
				wfDebugLog( 'CentralAuth',
					"Full automatic migration for '$this->mName'" );
			}
		}
		
		foreach( $attach as $dbname => $method ) {
			$this->attach( $dbname, $method );
		}
		
		return count( $attach ) == count( $rows );
	}
	
	/**
	 * Attempt to migrate any remaining unattached accounts by virtue of
	 * the password check.
	 * 
	 * @param string $password plaintext password to try matching
	 * @param $migrated out array of db names for records which were
	 *                  successfully migrated by this operation
	 * @param $remaining out array of db names for records which are still
	 *                   unattached after the operation
	 * @return bool true if all accounts are migrated at the end
	 */
	function attemptPasswordMigration( $password, &$migrated=null, &$remaining=null ) {
		$rows = $this->fetchUnattached();
		
		if( count( $rows ) == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Already fully migrated user '$this->mName'" );
			return true;
		}
		
		$migrated = array();
		$remaining = array();
		
		// Look for accounts we can match by password
		foreach( $rows as $key => $row ) {
			if( $this->matchHash( $password, $row->lu_local_id, $row->lu_migrated_password ) ) {
				wfDebugLog( 'CentralAuth',
					"Attaching '$this->mName' on $row->lu_dbname by password" );
				$this->attach( $row->lu_dbname, 'password' );
				$migrated[] = $row->lu_dbname;
			} else {
				wfDebugLog( 'CentralAuth',
					"No password match for '$this->mName' on $row->lu_dbname" );
				$remaining[] = $row->lu_dbname;
			}
		}
		
		if( count( $remaining ) == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Successfull auto migration for '$this->mName'" );
			return true;
		}
		
		wfDebugLog( 'CentralAuth',
			"Incomplete migration for '$this->mName'" );
		return false;
	}
	
	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param string $dbname
	 * @param int $localid
	 *
	 * Prerequisites:
	 * - completed migration state
	 */
	function addLocal( $dbname, $localid ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->insert( self::tableName( 'localuser' ),
			array(
				'lu_global_id' => $this->getId(),
				'lu_dbname'    => $dbname,
				'lu_local_id'  => $localid ),
			__METHOD__ );
	}
	
	/**
	 * Declare the local account for a given wiki to be attached
	 * to the global account for the current username.
	 *
	 * @return true on success
	 */
	public function attach( $dbname, $method ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->update( self::tableName( 'localuser' ),
			array(
				// Boo-yah!
				'lu_global_id'           => $this->getId(),
				'lu_migration_timestamp' => $dbw->timestamp(),
				'lu_migration_method'    => $method,
				),
			array(
				'lu_dbname'          => $dbname,
				'lu_migrated_name'   => $this->mName ),
			__METHOD__ );
		
		$rows = $dbw->affectedRows();
		if( $rows > 0 ) {
			return true;
		} else {
			wfDebugLog( 'CentralAuth',
				"failed to attach \"{$this->mName}@$dbname\", not in localuser\n" );
			return false;
		}
	}
	
	/**
	 * Attempt to authenticate the global user account with the given password
	 * @param string $password
	 * @return ("ok", "no user", "locked", "bad password")
	 */
	public function authenticate( $password ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$row = $dbw->selectRow( self::tableName( 'globaluser' ),
			array( 'gu_salt', 'gu_password', 'gu_locked' ),
			array( 'gu_id' => $this->getId() ),
			__METHOD__ );
		
		if( !$row ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' failed due to missing account" );
			return "no user";
		}
		
		$salt = $row->gu_salt;
		$crypt = $row->gu_password;
		$locked = $row->gu_locked;
		
		if( $locked ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' failed due to lock" );
			return "locked";
		}
		
		if( $this->matchHash( $password, $salt, $crypt ) ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' succeeded" );
			return "ok";
		} else {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' failed, bad pass" );
			return "bad password";
		}
	}
	
	/**
	 * @param $plaintext  User-provided password plaintext.
	 * @param $salt       The hash "salt", eg a local id for migrated passwords.
	 * @param $encrypted  Fully salted and hashed database crypto text from db.
	 * @return bool true on match.
	 */
	private function matchHash( $plaintext, $salt, $encrypted ) {
		return md5( $salt . "-" . md5( $plaintext ) ) === $encrypted;
	}
	
	/**
	 * Fetch a list of databases where this account name is registered,
	 * but not yet attached to the global account. It would be used for
	 * an alert or management system to show which accounts have still
	 * to be dealt with.
	 *
	 * @return array of database name strings
	 */
	function listUnattached() {
		$rows = $this->fetchUnattached();
		$dbs = array();
		foreach( $rows as $row ) {
			$dbs[] = $row->lu_dbname;
		}
		return $dbs;
	}
	
	function fetchUnattached() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$result = $dbw->select( self::tableName( 'localuser' ),
			array(
				'lu_dbname',
				'lu_local_id',
				'lu_migrated_name',
				'lu_migrated_password',
				'lu_migrated_email',
				'lu_migrated_email_authenticated',
				'lu_migrated_editcount',
			),
			array(
				'lu_migrated_name' => $this->mName,
				'lu_global_id IS NULL',
			),
			__METHOD__ );
		$rows = array();
		while( $row = $dbw->fetchObject( $result ) ) {
			$rows[] = $row;
		}
		$dbw->freeResult( $result );
		return $rows;
	}
	
	function getEmail() {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		return $dbr->selectField( self::tableName( 'globaluser' ),
			'gu_email',
			array( 'gu_id' => $this->getId() ),
			__METHOD__ );
	}

	/**
	 * Salt and hash a new plaintext password.
	 * @param string $password plaintext
	 * @return array of strings, salt and hash
	 */
	function saltedPassword( $password ) {
		$salt = mt_rand( 0, 1000000 );
		$hash = wfEncryptPassword( $salt, $password );
		return array( $salt, $hash );
	}
	
	/**
	 * Set the account's password
	 */
	function setPassword( $password ) {
		list( $salt, $hash ) = $this->saltedPassword( $password );
		
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$result = $dbr->update( self::tableName( 'globaluser' ),
			array(
				'gu_salt'     => $salt,
				'gu_password' => $hash,
			),
			array(
				'gu_id' => $this->getId(),
			),
			__METHOD__ );
		
		$rows = $dbw->numRows( $result );
		$dbw->freeResult( $result );
		
		return $rows > 0;
	}

}

?>