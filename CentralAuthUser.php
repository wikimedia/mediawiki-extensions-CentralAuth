<?php

/*

likely construction types...

- give me the global account for this local user id
- none? give me the global account for this name

- create me a global account for this name

*/

class CentralAuthHelper {
	private static $connections = array();
	
	public static function get( $dbname ) {
		global $wgDBname;
		if( $dbname == $wgDBname ) {
			return wfGetDB( DB_MASTER );
		}
		
		global $wgDBuser, $wgDBpassword;
		$server = self::getServer( $dbname );
		if( !isset( self::$connections[$server] ) ) {
			self::$connections[$server] = new Database( $server, $wgDBuser, $wgDBpassword, $dbname );
		}
		self::$connections[$server]->selectDB( $dbname );
		return self::$connections[$server];
	}
	
	private static function getServer( $dbname ) {
		global $wgAlternateMaster, $wgDBserver;
		if( isset( $wgAlternateMaster[$dbname] ) ) {
			return $wgAlternateMaster[$dbname];
		} elseif( isset( $wgAlternateMaster['DEFAULT'] ) ) {
			return $wgAlternateMaster['DEFAULT'];
		}
		return $wgDBserver;
	}
}

class CentralAuthUser {
	
	/**
	 * The username of the current user.
	 */
	private $mName;
	
	function __construct( $username ) {
		$this->mName = $username;
		$this->resetState();
	}
	
	/**
	 * @fixme Make use of some info to get the appropriate master DB
	 */
	public static function getCentralDB() {
		return CentralAuthHelper::get( 'centralauth' );
	}
	
	/**
	 * @fixme Make use of some info to get the appropriate master DB
	 */
	public static function getLocalDB( $dbname ) {
		return CentralAuthHelper::get( $dbname );
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
		$this->mProperties = null;
		$this->mHomeWiki = null;
	}
	
	/**
	 * Lazy-load up the most commonly required state information
	 */
	private function loadState() {
		if( !isset( $this->mGlobalId ) ) {
			global $wgDBname;
			$dbr = self::getCentralDB();
			$globaluser = self::tableName( 'globaluser' );
			$localuser = self::tableName( 'localuser' );
		
			$sql =
				"SELECT gu_id, lu_dbname
					FROM $globaluser
					LEFT OUTER JOIN $localuser
						ON gu_name=lu_name
						AND lu_dbname=?
					WHERE gu_name=?";
			$result = $dbr->safeQuery( $sql, $wgDBname, $this->mName );
			$row = $dbr->fetchObject( $result );
			$dbr->freeResult( $result );
		
			if( $row ) {
				$this->mGlobalId = intval( $row->gu_id );
				$this->mIsAttached = ($row->lu_dbname !== null);
			} else {
				$this->mGlobalId = 0;
				$this->mIsAttached = false;
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
		return $this->mIsAttached;
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
	
	/**
	 * Lazy-load misc properties that may be used at times
	 */
	private function loadProperties() {
		if( !isset( $this->mProperties ) ) {
			$dbw = self::getCentralDB();
			$row = $dbw->selectRow( self::tableName( 'globaluser' ),
				array( 'gu_locked', 'gu_hidden', 'gu_registration' ),
				array( 'gu_name' => $this->mName ),
				__METHOD__ );
			$this->mProperties = $row;
		}
	}
	
	/**
	 * @return bool
	 */
	public function isLocked() {
		$this->loadProperties();
		return (bool)$this->mProperties->gu_locked;
	}
	
	/**
	 * @return bool
	 */
	public function isHidden() {
		$this->loadProperties();
		return (bool)$this->mProperties->gu_hidden;
	}
	
	/**
	 * @return string timestamp
	 */
	public function getRegistration() {
		$this->loadProperties();
		return wfTimestamp( TS_MW, $this->mProperties->gu_registration );
	}
	
	private function lazyMigrate() {
		global $wgCentralAuthAutoMigrate;
		if( $wgCentralAuthAutoMigrate ) {
			$dbw = self::getCentralDB();
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
		$dbw = self::getCentralDB();
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
			$dbw = self::getCentralDB();
			$tuples = array();
			foreach( $users as $name ) {
				$tuples[] = array(
					'ln_dbname'   => $dbname,
					'ln_name'     => $name );
			}
			$dbw->insert(
				self::tableName( 'localnames' ),
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
		$dbw = self::getCentralDB();
		$dbw->insert( self::tableName( 'globaluser' ),
			array(
				'gu_name' => $this->mName,
				'gu_salt' => $salt,
				'gu_password' => $hash,
				'gu_email' => $email,
				'gu_email_authenticated' => $emailAuth,
				'gu_registration' => $dbw->timestamp(), // hmmmm
			),
			__METHOD__,
			array( 'IGNORE' ) );
		
		$this->resetState();
		return $dbw->affectedRows() != 0;
	}
	
	public function storeAndMigrate( $passwords=array() ) {
		$dbw = self::getCentralDB();
		$dbw->begin();
		
		$ret = $this->attemptAutoMigration( $passwords );
		
		$dbw->commit();
		return $ret;
	}
	
	/**
	 * Out of the given set of local account data, pick which will be the
	 * initially-assigned home wiki.
	 *
	 * This will be the account with the highest edit count, either out of
	 * all privileged accounts or all accounts if none are privileged.
	 *
	 * @param array $migrationSet
	 * @return string
	 */
	function chooseHomeWiki( $migrationSet ) {
		if( empty( $migrationSet ) ) {
			throw new MWException( 'Logic error -- empty migration set in chooseHomeWiki' );
		}
		
		// Sysops get priority
		$priorityGroups = array( 'sysop', 'bureaucrat', 'steward' );
		$workingSet = array();
		foreach( $migrationSet as $db => $local ) {
			if( array_intersect( $priorityGroups, $local['groups'] ) ) {
				if( $local['editCount'] ) {
					// Ignore unused sysop accounts
					$workingSet[$db] = $local;
				}
			}
		}
		
		if( !$workingSet ) {
			// No privileged accounts; look among the plebes...
			$workingSet = $migrationSet;
		}
		
		// Blocked accounts not allowed to get automatic home wiki
		foreach( $workingSet as $db => $local ) {
			if( $local['blocked'] ) {
				wfDebugLog( 'CentralAuth',
					"Striking blocked account $this->mName@$db from working set\n" );
				unset( $workingSet[$db] );
			}
		}
		
		$maxEdits = -1;
		$homeWiki = null;
		foreach( $workingSet as $db => $local ) {
			if( $local['editCount'] > $maxEdits ) {
				$homeWiki = $db;
				$maxEdits = $local['editCount'];
			}
		}
		
		if( !isset( $homeWiki ) ) {
			throw new MWException( "Logic error in migration: " .
				"Unable to determine primary account for $this->mName" );
		}
		
		return $homeWiki;
	}
	
	/**
	 * Go through a list of migration data looking for those which
	 * can be automatically migrated based on the available criteria.
	 * @param array $migrationSet
	 * @param string $passwords Optional, pre-authenticated passwords.
	 *                          Should match an account which is known
	 *                          to be attached.
	 */
	function prepareMigration( $migrationSet, $passwords=array() ) {
		// If the primary account has an e-mail address set,
		// we can use it to match other accounts. If it doesn't,
		// we can't be sure that the other accounts with no mail
		// are the same person, so err on the side of caution.
		//
		// For additional safety, we'll only let the mail check
		// propagate from a confirmed account
		$passingMail = array();
		if( $this->mEmail != '' && $this->mEmailAuthenticated ) {
			$passingMail[$this->mEmail] = true;
		}
		
		// If we've got an authenticated password to work with, we can
		// also assume their e-mails are useful for this purpose...
		if( $passwords ) {
			foreach( $migrationSet as $db => $local ) {
				if( $local['email'] != ''
					&& $local['emailAuthenticated']
					&& $this->matchHashes( $passwords, $local['id'], $local['password'] ) ) {
					$passingMail[$local['email']] = true;
				}
			}
		}
		
		$attach = array();
		foreach( $migrationSet as $db => $local ) {
			$localName = "$this->mName@$db";
			if( $db == $this->mHomeWiki ) {
				// Primary account holder... duh
				$method = 'primary';
			} elseif( $this->matchHashes( $passwords, $local['id'], $local['password'] ) ) {
				// Matches the pre-authenticated password, yay!
				$method = 'password';
			} elseif( isset( $passingMail[$local['email']] ) ) {
				// Same e-mail as primary means we know they could
				// reset their password, so we give them the account.
				$method = 'mail';
			} elseif( $local['editCount'] == 0 ) {
				// Unused accounts are fair game for reclaiming
				$method = 'empty';
			} else {
				// Can't automatically resolve this account.
				//
				// If the password matches, it will be automigrated
				// at next login. If no match, user will have to input
				// the conflicting password or deal with the conflict.
				wfDebugLog( 'CentralAuth', "unresolvable $localName" );
				continue;
			}
			wfDebugLog( 'CentralAuth', "$method $localName" );
			$attach[$db] = $method;
		}
		
		return $attach;
	}
	
	/**
	 * Do a dry run -- pick a winning master account and try to auto-merge
	 * as many as possible, but don't perform any actions yet.
	 *
	 * @param array $passwords
	 * @param string &$home set to false if no permission to do checks
	 * @param array &$attached on success, list of wikis which will be auto-attached
	 * @param array &$unattached on success, list of wikis which won't be auto-attached
	 * @param array &$methods on success, associative array of each wiki's attachment method
	 * @return bool true if password matched current and home account
	 */
	function migrationDryRun( $passwords, &$home, &$attached, &$unattached, &$methods ) {
		global $wgDBname;
		
		$home = false;
		$attached = array();
		$unattached = array();
		
		// First, make sure we were given the current wiki's password.
		$self = $this->localUserData( $wgDBname );
		if( !$this->matchHashes( $passwords, $self['id'], $self['password'] ) ) {
			wfDebugLog( 'CentralAuth', "dry run: failed self-password check" );
			return false;
		}
		
		$migrationSet = $this->queryUnattached();
		$home = $this->chooseHomeWiki( $migrationSet );
		$local = $migrationSet[$home];
		
		// And we need to match the home wiki before proceeding...
		if( $this->matchHashes( $passwords, $local['id'], $local['password'] ) ) {
			wfDebugLog( 'CentralAuth', "dry run: passed password match to home $home" );
		} else {
			wfDebugLog( 'CentralAuth', "dry run: failed password match to home $home" );
			return false;
		}
		
		$this->mHomeWiki = $home;
		$this->mEmail = $local['email'];
		$this->mEmailAuthenticated = $local['emailAuthenticated'];
		$attach = $this->prepareMigration( $migrationSet, $passwords );
		
		$all = array_keys( $migrationSet );
		$attached = array_keys( $attach );
		$unattached = array_diff( $all, $attached );
		$methods = $attach;
		
		sort( $attached );
		sort( $unattached );
		ksort( $methods );
		
		return true;
	}
	
	/**
	 * Pick a winning master account and try to auto-merge as many as possible.
	 * @fixme add some locking or something
	 * @return bool Whether full automatic migration completed successfully.
	 */
	private function attemptAutoMigration( $passwords=array() ) {
		$migrationSet = $this->queryUnattached();
		
		
		$this->mHomeWiki = $this->chooseHomeWiki( $migrationSet );
		$home = $migrationSet[$this->mHomeWiki];
		$this->mEmail = $home['email'];
		$this->mEmailAuthenticated = $home['emailAuthenticated'];
		
		$attach = $this->prepareMigration( $migrationSet, $passwords );

		$ok = $this->storeGlobalData(
				$home['id'],
				$home['password'],
				$home['email'],
				$home['emailAuthenticated'] );
		
		if( !$ok ) {
			wfDebugLog( 'CentralAuth',
				"attemptedAutoMigration for existing entry '$this->mName'" );
			return false;
		}
		
		if( count( $attach ) < count( $migrationSet ) ) {
			wfDebugLog( 'CentralAuth',
				"Incomplete migration for '$this->mName'" );
		} else {
			if( count( $migrationSet ) == 1 ) {
				wfDebugLog( 'CentralAuth',
					"Singleton migration for '$this->mName' on " . $this->mHomeWiki );
			} else {
				wfDebugLog( 'CentralAuth',
					"Full automatic migration for '$this->mName'" );
			}
		}
		
		foreach( $attach as $db => $method ) {
			$this->attach( $db, $method );
		}
		
		return count( $attach ) == count( $migrationSet );
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
			if( $this->matchHash( $password, $row['id'], $row['password'] ) ) {
				wfDebugLog( 'CentralAuth',
					"Attaching '$this->mName' on $db by password" );
				$this->attach( $db, 'password' );
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
				$this->attach( $row['dbName'], 'admin' );
				$migrated[] = $row['dbName'];
			} else {
				$remaining[] = $row['dbName'];
			}
		}
		
		return count( $migrated ) == count( $valid );
	}
	
	public function adminUnattach( $list, &$migrated=null, &$remaining=null ) {
		$valid = $this->validateList( $list );
		
		$dbw = self::getCentralDB();
		$dbw->delete( self::tableName( 'localuser' ),
			array(
				'lu_name'   => $this->mName,
				'lu_dbname' => $valid ),
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
	public function attach( $dbname, $method='new' ) {
		$dbw = self::getCentralDB();
		$dbw->insert( self::tableName( 'localuser' ),
			array(
				'lu_dbname'             => $dbname,
				'lu_name'               => $this->mName ,
				'lu_attached_timestamp' => $dbw->timestamp(),
				'lu_attached_method'    => $method ),
			__METHOD__,
			array( 'IGNORE' ) );
		
		if( $dbw->affectedRows() == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Race condition? Already attached $this->mName@$dbname, just tried by '$method'" );
		} else {
			wfDebugLog( 'CentralAuth',
				"Attaching local user $this->mName@$dbname by '$method'" );
		
			global $wgDBname;
			if( $dbname == $wgDBname ) {
				$this->resetState();
			}
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
		
		$dbw = self::getCentralDB();
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
	
	private function matchHashes( $passwords, $salt, $encrypted ) {
		foreach( $passwords as $plaintext ) {
			if( $this->matchHash( $plaintext, $salt, $encrypted ) ) {
				return true;
			}
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
		$unattached = $this->doListUnattached();
		if( empty( $unattached ) ) {
			if( $this->lazyImportLocalNames() ) {
				$unattached = $this->doListUnattached();
			}
		}
		return $unattached;
	}
	
	function doListUnattached() {
		$dbw = self::getCentralDB();
		
		$sql = "
		SELECT ln_dbname
		FROM localnames
		LEFT OUTER JOIN localuser
			ON ln_dbname=lu_dbname AND ln_name=lu_name
		WHERE ln_name=? AND lu_name IS NULL
		";
		$result = $dbw->safeQuery( $sql, $this->mName );
		
		$dbs = array();
		while( $row = $dbw->fetchObject( $result ) ) {
			$dbs[] = $row->ln_dbname;
		}
		$dbw->freeResult( $result );
		
		return $dbs;
	}
	
	function addLocalName( $dbname ) {
		$dbw = self::getCentralDB();
		$dbw->begin();
		$this->lazyImportLocalNames();
		$dbw->insert( self::tableName( 'localnames' ),
			array(
				'ln_dbname' => $dbname,
				'ln_name' => $this->mName ),
			__METHOD__,
			array( 'IGNORE' ) );
		$dbw->commit();
	}
	
	function removeLocalName( $dbname ) {
		$dbw = self::getCentralDB();
		$dbw->begin();
		$this->lazyImportLocalNames();
		$dbw->delete( self::tableName( 'localnames' ),
			array(
				'ln_dbname' => $dbname,
				'ln_name' => $this->mName ),
			__METHOD__ );
		$dbw->commit();
	}
	
	function lazyImportLocalNames() {
		$dbw = self::getCentralDB();
		
		$result = $dbw->select( self::tableName( 'globalnames' ),
			array( '1' ),
			array( 'gn_name' => $this->mName ),
			__METHOD__,
			array( 'LIMIT' => 1 ) );
		$known = $result->numRows();
		$result->free();
		
		if( $known ) {
			// No need...
			return false;
		}
		
		return $this->importLocalNames();
	}
	
	/**
	 * Troll through the full set of local databases and list those
	 * which exist into the 'localnames' table.
	 */
	function importLocalNames() {
		global $wgLocalDatabases;
		
		$rows = array();
		foreach( $wgLocalDatabases as $db ) {
			$dbr = self::getLocalDB( $db );
			$id = $dbr->selectField(
				"`$db`.`user`",
				'user_id',
				array( 'user_name' => $this->mName ),
				__METHOD__ );
			if( $id ) {
				$rows[] = array(
					'ln_dbname' => $db,
					'ln_name' => $this->mName );
			}
		}
		
		$dbw = self::getCentralDB();
		$dbw->begin();
		$dbw->insert( self::tableName( 'globalnames' ),
			array( 'gn_name' => $this->mName ),
			__METHOD__,
			array( 'IGNORE' ) );
		if( $rows ) {
			$dbw->insert( self::tableName( 'localnames' ),
				$rows,
				__METHOD__,
				array( 'IGNORE' ) );
		}
		$dbw->commit();
		
		return !empty( $rows );
	}
	
	/**
	 * Fetch a list of database where this account has been successfully
	 * attached.
	 *
	 * @return array database name strings
	 */
	public function listAttached() {
		$dbw = self::getCentralDB();
		
		$result = $dbw->select( self::tableName( 'localuser' ),
			array( 'lu_dbname' ),
			array( 'lu_name' => $this->mName ),
			__METHOD__ );
		
		$dbs = array();
		while( $row = $result->fetchObject() ) {
			$dbs[] = $row->lu_dbname;
		}
		$dbw->freeResult( $result );
		
		return $dbs;
	}
	
	/**
	 * Flooobie!
	 */
	private function listLocalDatabases( $attached ) {
		$dbw = self::getCentralDB();
		
		$result = $dbw->select( self::tableName( 'localuser' ),
			array( 'lu_dbname' ),
			array(
				'lu_name'     => $this->mName,
				'lu_attached' => $attached ),
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
		$dbw = self::getCentralDB();
		
		$result = $dbw->select(
			array( self::tableName( 'localuser' ) ),
			array(
				'lu_dbname',
				'lu_attached_timestamp',
				'lu_attached_method' ),
			array(
				'lu_name' => $this->mName ),
			__METHOD__ );
		
		$dbs = array();
		while( $row = $dbw->fetchObject( $result ) ) {
			$dbs[$row->lu_dbname] = array(
				'dbName' => $row->lu_dbname,
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
		$dbnames = $this->listUnattached();
		
		$items = array();
		foreach( $dbnames as $db ) {
			$data = $this->localUserData( $db );
			if( empty( $data ) ) {
				throw new MWException(
					"Bad user row looking up local user $this->mName@$db" );
			}
			$items[$db] = $data;
		}
		
		return $items;
	}
	
	/**
	 * Fetch a row of user data needed for migration.
	 */
	protected function localUserData( $dbname ) {
		$db = self::getLocalDB( $dbname );
		$row = $db->selectRow( "`$dbname`.user",
			array(
				'user_id',
				'user_email',
				'user_email_authenticated',
				'user_password',
				'user_editcount' ),
			array( 'user_name' => $this->mName ),
			__METHOD__ );
		
		$data = array(
			'dbName' => $dbname,
			'id' => $row->user_id,
			'email' => $row->user_email,
			'emailAuthenticated' => $row->user_email_authenticated,
			'password' => $row->user_password,
			'editCount' => $row->user_editcount,
			'groups' => array(),
			'blocked' => false );
		
		// Edit count field may not be initialized...
		if( is_null( $row->user_editcount ) ) {
			$data['editCount'] = $db->selectField(
				"`$dbname`.revision",
				'COUNT(*)',
				array( 'rev_user' => $data['id'] ),
				__METHOD__ );
		}
		
		// And we have to fetch groups separately, sigh...
		$groups = array();
		$result = $db->select( "`$dbname`.user_groups",
			array( 'ug_group' ),
			array( 'ug_user' => $data['id'] ),
			__METHOD__ );
		foreach( $result as $row ) {
			$data['groups'][] = $row->ug_group;
		}
		$result->free();
		
		// And while we're in here, look for user blocks :D
		$blocks = array();
		$result = $db->select( "`$dbname`.ipblocks",
			array( 'ipb_expiry' ),
			array( 'ipb_user' => $data['id'] ),
			__METHOD__ );
		foreach( $result as $row ) {
			if( Block::decodeExpiry( $row->ipb_expiry ) > wfTimestampNow() ) {
				$data['blocked'] = true;
			}
		}
		$result->free();
		
		return $data;
	}
	
	function getEmail() {
		$dbr = self::getCentralDB();
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
		
		$dbw = self::getCentralDB();
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

