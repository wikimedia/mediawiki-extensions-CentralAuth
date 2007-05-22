<?php

/*

likely construction types...

- give me the global account for this local user id
- none? give me the global account for this name

- create me a global account for this name

*/


class CentralAuthUser {
	
	/**
	 * The username of the current user.
	 */
	private $mName;
	
	function __construct( $username ) {
		$this->mName = $username;
		$this->resetState();
		$this->loadState();
	}
	
	public static function tableName( $name ) {
		global $wgCentralAuthDatabase;
		return
			'`' .
			$wgCentralAuthDatabase .
			'`.`' .
			$name .
			'`';
	}
	
	/**
	 * Clear state information cache
	 */
	private function resetState() {
		$this->mGlobalId = null;
		$this->mLocalId = null;
	}
	
	/**
	 * Load up the most commonly required state information
	 */
	private function loadState() {
		if( !isset( $this->mGlobalId ) ) {
			global $wgDBname;
			$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
			$globaluser = self::tableName( 'globaluser' );
			$localuser = self::tableName( 'localuser' );
		
			$sql =
				"SELECT gu_id, lu_local_id
					FROM $globaluser
					LEFT OUTER JOIN $localuser
						ON gu_id=lu_global_id
						AND lu_dbname=?
					WHERE gu_name=?";
			$result = $dbr->safeQuery( $sql, $wgDBname, $this->mName );
			$row = $dbr->fetchObject( $result );
			$dbr->freeResult( $result );
		
			if( $row ) {
				$this->mGlobalId = $row->gu_id;
				$this->mLocalId = $row->lu_local_id;
			} else {
				$this->mGlobalId = null;
				$this->mLocalId = null;
			}
		}
	}
	
	/**
	 * Return the global account ID number for this account, if it exists.
	 */
	public function getId() {
		$this->loadState();
		return $this->mGlobalId;
	}
	
	/**
	 * @return bool True if the account is attached on the local wiki
	 */
	public function isAttached() {
		$this->loadState();
		return isset( $this->mLocalId );
	}
	
	/**
	 * Check whether a global user account for this name exists yet.
	 * If migration state is set for pass 1, this may trigger lazy
	 * evaluation of automatic migration for the account.
	 *
	 * @return bool
	 */
	public function exists() {
		$this->lazyMigrate();
		$id = $this->getId();
		wfDebugLog( 'CentralAuth', "exists() for '$this->mName': $id" );
		return $id != 0;
	}
	
	private function lazyMigrate() {
		global $wgCentralAuthAutoMigrate;
		if( $wgCentralAuthAutoMigrate ) {
			$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
			$dbw->begin();
			
			$id = $this->getId();
			if( !$id ) {
				// Global accounts may not all be in place yet.
				// Try automerging first, then check again.
				$migrated = $this->attemptAutoMigration(); // $migrated return val not used.
				$id = $this->getId();
				if( $id ) {
					wfDebugLog( 'CentralAuth',
						"Ran lazy migration for '$this->mName', new id $id" );
				} else {
					wfDebugLog( 'CentralAuth',
						"Ran lazy migration for '$this->mName', no entries" );
				}
			}
			
			$dbw->commit();
		}
	}
	
	/**
	 * Register a new, not previously existing, central user account
	 * Remaining fields are expected to be filled out shortly...
	 * eeeyuck
	 */
	function register( $password, $email, $realname ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		list( $salt, $hash ) = $this->saltedPassword( $password );
		$ok = $dbw->insert(
			self::tableName( 'globaluser' ),
			array(
				'gu_name'  => $this->mName,
				
				'gu_email' => $email, // FIXME
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
	 * @param string $dbname Source database
	 * @param array $users Associative array of ids => names
	 */
	static function storeMigrationData( $dbname, $users ) {
		if( $users ) {
			$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
			$tuples = array();
			foreach( $users as $id => $name ) {
				$tuples[] = array(
					'mu_dbname'   => $dbname,
					'mu_local_id' => $id,
					'mu_name'     => $name );
			}
			$dbw->insert(
				self::tableName( 'migrateuser' ),
				$tuples,
				__METHOD__,
				array( 'IGNORE' ) );
		}
	}
	
	/**
	 * For use in migration pass one.
	 * Store global user data in the auth server's main table.
	 * @return bool Whether we were successful or not.
	 */
	private function storeGlobalData( $salt, $hash, $email, $emailAuth ) {
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
		
		$this->resetState();
		return $dbw->affectedRows() != 0;
	}
	
	public function storeAndMigrate() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->begin();
		
		$ret = $this->attemptAutoMigration();
		
		$dbw->commit();
		return $ret;
	}
	
	/**
	 * Pick a winning master account and try to auto-merge as many as possible.
	 * @fixme add some locking or something
	 * @return bool Whether full automatic migration completed successfully.
	 */
	private function attemptAutoMigration() {
		$rows = $this->queryUnattached();
		
		if( !$rows ) {
			wfDebugLog( 'CentralAuth',
				"Attempted migration with no unattached for '$this->mName'" );
			return false;
		}
		
		$winner = null;
		$max = -1;
		$attach = array();
		
		// We have to pick a master account
		// The winner is the one with the most edits, usually
		foreach( $rows as $row ) {
			if( $row['editCount'] > $max ) {
				$winner = $row;
				$max = $row['editCount'];
			}
		}
		if( !isset( $winner ) ) {
			throw new MWException( "Logic error in migration: " .
				"Unable to determine primary account for $this->mName" );
		}
		
		// If the primary account has an e-mail address set,
		// we can use it to match other accounts. If it doesn't,
		// we can't be sure that the other accounts with no mail
		// are the same person, so err on the side of caution.
		$winningMail = ($winner['email'] == ''
			? false
			: $winner['email']);
		
		foreach( $rows as $row ) {
			$local = $this->mName . "@" . $row['dbName'];
			if( $row['dbName'] == $winner['dbName'] ) {
				// Primary account holder... duh
				$method = 'primary';
			} elseif( $row['email'] === $winningMail ) {
				// Same e-mail as primary means we know they could
				// reset their password, so we give them the account.
				$method = 'mail';
			} elseif( $row['editCount'] == 0 ) {
				// Unused accounts are fair game for reclaiming
				$method = 'empty';
			} else {
				// Can't automatically resolve this account.
				//
				// If the password matches, it will be automigrated
				// at next login. If no match, user will have to input
				// the conflicting password or deal with the conflict.
				wfDebugLog( 'CentralAuth', "unresolvable $local" );
				continue;
			}
			wfDebugLog( 'CentralAuth', "$method $local" );
			$attach[] = array( $row['dbName'], $row['localId'], $method );
		}

		$ok = $this->storeGlobalData(
				$winner['localId'],
				$winner['password'],
				$winner['email'],
				$winner['emailAuthenticated'] );
		
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
					"Singleton migration for '$this->mName' on " . $winner['dbName'] );
			} else {
				wfDebugLog( 'CentralAuth',
					"Full automatic migration for '$this->mName'" );
			}
		}
		
		foreach( $attach as $bits ) {
			list( $dbname, $localid, $method ) = $bits;
			$this->attach( $dbname, $localid, $method );
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
	public function attemptPasswordMigration( $password, &$migrated=null, &$remaining=null ) {
		$rows = $this->queryUnattached();
		
		if( count( $rows ) == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Already fully migrated user '$this->mName'" );
			return true;
		}
		
		$migrated = array();
		$remaining = array();
		
		// Look for accounts we can match by password
		foreach( $rows as $row ) {
			$db = $row['dbName'];
			if( $this->matchHash( $password, $row['localId'], $row['password'] ) ) {
				wfDebugLog( 'CentralAuth',
					"Attaching '$this->mName' on $db by password" );
				$this->attach( $db, $row['localId'], 'password' );
				$migrated[] = $db;
			} else {
				wfDebugLog( 'CentralAuth',
					"No password match for '$this->mName' on $db" );
				$remaining[] = $db;
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
	
	private static function validateList( $list ) {
		global $wgLocalDatabases;
		
		$unique = array_unique( $list );
		$valid = array_intersect( $unique, $wgLocalDatabases );
		
		if( count( $valid ) != count( $list ) ) {
			// fixme: handle this gracefully
			throw new MWException( "Invalid input" );
		}
		
		return $valid;
	}
	
	public function adminAttach( $list, &$migrated=null, &$remaining=null ) {
		$valid = $this->validateList( $list );
		$unattached = $this->queryUnattached();
		
		$migrated = array();
		$remaining = array();
		
		foreach( $unattached as $row ) {
			if( in_array( $row['dbName'], $valid ) ) {
				$this->attach( $row['dbName'], $row['localId'], 'admin' );
				$migrated[] = $row['dbName'];
			} else {
				$remaining[] = $row['dbName'];
			}
		}
		
		return count( $migrated ) == count( $valid );
	}
	
	public function adminUnattach( $list, &$migrated=null, &$remaining=null ) {
		$valid = $this->validateList( $list );
		
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( self::tableName( 'localuser' ),
			array(
				'lu_global_id' => $this->getId(),
				'lu_dbname'    => $valid ),
			__METHOD__ );
		
		// FIXME: touch remote-database user accounts
		
		// FIXME: proper... stuff
		$migrated = array();
		$remaining = $list;
		
		global $wgDBname;
		if( in_array( $wgDBname, $valid ) ) {
			$this->resetState();
		}
		
		return count( $list ) == count( $valid );
	}
	
	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param string $dbname
	 * @param int $localid
	 *
	 * Prerequisites:
	 * - completed migration state
	 */
	public function attach( $dbname, $localid, $method='new' ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->insert( self::tableName( 'localuser' ),
			array(
				'lu_global_id'          => $this->getId(),
				'lu_dbname'             => $dbname,
				'lu_local_id'           => $localid,
				'lu_attached_timestamp' => $dbw->timestamp(),
				'lu_attached_method'    => $method ),
			__METHOD__ );
		wfDebugLog( 'CentralAuth',
			"Attaching local user $dbname:$localid to '$this->mName' for '$method'" );
		
		global $wgDBname;
		if( $dbname == $wgDBname ) {
			$this->resetState();
		}
	}
	
	/**
	 * Attempt to authenticate the global user account with the given password
	 * @param string $password
	 * @return string status, one of: "ok", "no user", "locked", or "bad password".
	 * @todo Currently only the "ok" result is used (i.e. either use, or return a bool).
	 */
	public function authenticate( $password ) {
		$this->lazyMigrate();
		
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
		$hash = wfEncryptPassword( $salt, $plaintext );
		if( $encrypted === $hash ) {
			return true;
		} elseif( function_exists( 'iconv' ) ) {
			// Some wikis were converted from ISO 8859-1 to UTF-8;
			// retained hashes may contain non-latin chars.
			$latin = iconv( 'UTF-8', 'WINDOWS-1252//TRANSLIT', $plaintext );
			$latinHash = wfEncryptPassword( $salt, $latin );
			if( $encrypted === $latinHash ) {
				return true;
			}
		} else {
			$latinHash = null;
		}
		return false;
	}
	
	/**
	 * Fetch a list of databases where this account name is registered,
	 * but not yet attached to the global account. It would be used for
	 * an alert or management system to show which accounts have still
	 * to be dealt with.
	 *
	 * @return array of database name strings
	 */
	public function listUnattached() {
		$rows = $this->fetchUnattached();
		$dbs = array();
		foreach( $rows as $row ) {
			$dbs[] = $row->mu_dbname;
		}
		return $dbs;
	}
	
	/**
	 * Fetch a list of database where this account has been successfully
	 * attached.
	 *
	 * @return array database name strings
	 */
	public function listAttached() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		
		$result = $dbw->select(
			array( self::tableName( 'localuser' ) ),
			array( 'lu_dbname' ),
			array( 'lu_global_id' => $this->getId() ),
			__METHOD__ );
		
		$dbs = array();
		while( $row = $dbw->fetchObject( $result ) ) {
			$dbs[] = $row->lu_dbname;
		}
		$dbw->freeResult( $result );
		
		return $dbs;
	}
	
	/**
	 * Fetch a list of database where this account has been successfully
	 * attached.
	 *
	 * @return array database name strings
	 */
	public function queryAttached() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		
		$result = $dbw->select(
			array( self::tableName( 'localuser' ) ),
			array(
				'lu_dbname',
				'lu_local_id',
				'lu_attached_timestamp',
				'lu_attached_method' ),
			array( 'lu_global_id' => $this->getId() ),
			__METHOD__ );
		
		$dbs = array();
		while( $row = $dbw->fetchObject( $result ) ) {
			$dbs[$row->lu_dbname] = array(
				'dbName' => $row->lu_dbname,
				'localId' => intval( $row->lu_local_id ),
				'attachedTimestamp' => wfTimestampOrNull( TS_MW,
					 $row->lu_attached_timestamp ),
				'attachedMethod' => $row->lu_attached_method,
			);
		}
		$dbw->freeResult( $result );
		
		return $dbs;
	}
	
	/**
	 * Find any remaining migration records for this username
	 * which haven't gotten attached to some global account.
	 *
	 * Formatted as associative array with some data.
	 */
	public function queryUnattached() {
		$rows = $this->fetchUnattached();
		
		$items = array();
		foreach( $rows as $row ) {
			$db = $row->mu_dbname;
			$id = intval( $row->mu_local_id );
			$userData = self::localUserData( $db, $id );
			if( !is_object( $userData ) ) {
				throw new MWException("Bad user row looking up local user #$id@$db");
			}
			
			$items[$db] = array(
				'dbName' => $db,
				'localId' => $id,
				'email' => $userData->user_email,
				'emailAuthenticated' => $userData->user_email_authenticated,
				'password' => $userData->user_password,
				'editCount' => $userData->user_editcount,
			);
		}
		
		return $items;
	}
	
	/**
	 * Fetch a row of user data needed for migration.
	 * @todo: work on multi-master clusters!
	 */
	protected static function localUserData( $dbname, $id ) {
		//$db = wfGetForeignDB( $dbname );
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( "`$dbname`.user",
			array(
				'user_email',
				'user_email_authenticated',
				'user_password',
				'user_editcount' ),
			array( 'user_id' => $id ),
			__METHOD__ );
		
		// Edit count field may not be initialized...
		if( $row !== false && is_null( $row->user_editcount ) ) {
			$row->user_editcount = $db->selectField(
				"`$dbname`.revision",
				'COUNT(*)',
				array( 'rev_user' => $id ),
				__METHOD__ );
		}
		
		return $row;
	}
	
	/**
	 * Find any remaining migration records for this username
	 * which haven't gotten attached to some global account.
	 */
	private function fetchUnattached() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		
		$migrateuser = self::tableName( 'migrateuser' );
		$localuser = self::tableName( 'localuser' );
		$sql = "SELECT * FROM $migrateuser" .
			" LEFT JOIN $localuser" .
			" ON mu_dbname=lu_dbname AND mu_local_id=lu_local_id" .
			" WHERE mu_name=" . $dbw->addQuotes( $this->mName ) .
			" AND lu_dbname IS NULL";
		$result = $dbw->query( $sql, __METHOD__ );
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
	private function saltedPassword( $password ) {
		$salt = mt_rand( 0, 1000000 );
		$hash = wfEncryptPassword( $salt, $password );
		return array( $salt, $hash );
	}
	
	/**
	 * Set the account's password
	 * @param string $password plaintext
	 */
	function setPassword( $password ) {
		list( $salt, $hash ) = $this->saltedPassword( $password );
		
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$result = $dbw->update( self::tableName( 'globaluser' ),
			array(
				'gu_salt'     => $salt,
				'gu_password' => $hash,
			),
			array(
				'gu_id' => $this->getId(),
			),
			__METHOD__ );
		
		// if ( $result ) { ...
		wfDebugLog( 'CentralAuth',
			"Set global password for '$this->mName'" );
		return true;
	}

}

?>