<?php

/**
 * The sleep madness take you!
 */

/*

Ye olde milestones:

1) logging in on local DBs
2) new account creation on local DBs
3) migration-on-first-login of matching local accounts on local DBs
4) migration-on-first-login of non-matching local accounts on local DBs
5) renaming-on-first-login of non-matching local accounts on local DBs
6) provision for forced rename on local DBs
7) basic login for remote DBs
8) new account for remote DBs
9) migration for remote DBs
10) profit!

additional goodies:
11) secure login form
12) multiple-domain cookies to allow site-hopping



Ye olde tables:

CREATE TABLE globaluser (
  -- Internal unique ID for the authentication server
  gu_id int auto_increment,
  
  -- Username. [Could change... or not? How to best handle renames...]
  gu_name varchar(255) binary,
  
  -- Registered email address, may be empty.
  gu_email varchar(255) binary,
  
  -- Timestamp when the address was confirmed as belonging to the user.
  -- NULL if not confirmed.
  gu_email_authenticated char(14) binary,
  
  -- Salt and hashed password
  gu_salt char(16), -- or should this be an int? usually the old user_id
  gu_password char(32),
  
  -- If true, this account cannot be used to log in on any wiki.
  gu_locked bool not null default 0,
  
  -- If true, this account should be hidden from most public user lists.
  -- Used for "deleting" accounts without breaking referential integrity.
  gu_hidden bool not null default 0,
  
  -- Registration time
  gu_registration char(14) binary,
  
  primary key (gu_id),
  unique key (gu_name)
) CHARSET=latin1;


-- Migration state table
CREATE TABLE localuser (
  -- Database name of the wiki
  lu_dbname varchar(32) binary,
  
  -- user_id on the local wiki
  lu_id int,
  
  -- Username
  lu_name varchar(255) binary,
  
  -- User'd old password hash; salt is lu_id
  lu_password varchar(255) binary,
  
  -- The user_email and user_email_authenticated state from local wiki
  lu_email varchar(255) binary,
  lu_email_authenticated char(14) binary,
  
  -- A count of revisions and/or other actions made during migration
  -- May be null if it hasn't yet been checked
  lu_editcount int,
  
  -- Set to 1 if already migrated successfully,
  -- 0 if the account is still awaiting migration and attachment.
  lu_attached tinyint,
  
  primary key (lu_dbname,lu_id),
  unique key (lu_dbname,lu_name),
  key (lu_name,lu_dbname)
) CHARSET=latin1;



*/

$wgCentralAuthDatabase = 'authtest';

/**
 * Migration states: [not yet implemented fully]
 * 'premigrate': Local 'user' tables are still used for authentication,
 *               but with certain operations disabled to prevent conflicts
 *               while data is migrated to the central auth server.
 *
 * 'migration': Authentication is done against 'globaluser', with automatic
 *              transparent migration on login.
 *
 * 'production': Any remaining non-migrated accounts are locked out.
 *
 * 'testing': As 'premigrate', but no locking is done. Use to run tests
 *            of the pass-0 data generation.
 */
$wgCentralAuthState = 'disabled';


class CentralAuthUser {
	function __construct( $username ) {
		$this->mName = $username;
	}
	
	/**
	 * this code is crap
	 */
	function exists() {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		$ok = $dbr->selectField(
			'globaluser',
			'1',
			array( 'gu_name' => $this->mName ),
			__METHOD__ );
		if( $ok ) {
			wfDebugLog( 'CentralAuth',
				"checked for global account '$this->mName', found" );
		} else {
			wfDebugLog( 'CentralAuth',
				"checked for global account '$this->mName', missing" );
		}
		return (bool)$ok;
	}
	
	/**
	 * this code is crapper
	 */
	function register( $password ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		list( $salt, $hash ) = $this->saltedPassword( $password );
		$ok = $dbw->insert(
			'globaluser',
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
			'localuser',
			array(
				'lu_dbname'    => $dbname,
				'lu_id'        => $row->user_id,
				'lu_name'      => $row->user_name,
				'lu_password'  => $row->user_password,
				'lu_email'     => $row->user_email,
				'lu_email_authenticated' => $row->user_email_authenticated,
				'lu_editcount' => $editCount,
				'lu_attached'  => 0, // Not yet migrated!
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
		$dbw->insert( 'globaluser',
			array(
				'gu_name' => $this->mName,
				'gu_salt' => $salt,
				'gu_password' => $hash,
				'gu_email' => $email,
				'gu_email_authenticated' => $emailAuth,
			),
			__METHOD__ );
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
	function attemptAutoMigration( $password='' ) {
		$rows = $this->fetchUnattached();
		
		$winner = false;
		$max = -1;
		$attach = array();
		$unattach = array();
		
		// We have to pick a master account
		// The winner is the one with the most edits, usually
		foreach( $rows as $row ) {
			if( $row->lu_editcount > $max ) {
				$winner = $row;
				$max = $row->lu_editcount;
			}
		}
		assert( isset( $winner ) );
		
		// Do they all match?
		$allMatch = true;
		$allMatchOrEmpty = true;
		$allMatchOrUnused = true;
		$isConflict = false;
		$winningMail = ($winner->lu_email == '' ? false : $winner->lu_email);
		
		foreach( $rows as $row ) {
			if( $row->lu_dbname == $winner->lu_dbname ) {
				$attach[] = $row;
			} else {
				if( $row->lu_email !== $winningMail ) {
					$allMatch = false;
					if( $row->lu_email !== '' ) {
						$allMatchOrEmpty = false;
					}
					if( $row->lu_editcount == 0 ) {
						// Unused accounts are fair game for reclaiming
						$attach[] = $row;
					} else {
						$allMatchOrUnused = false;
						$unattach[] = $row;
						$isConflict = true;
					}
				} else {
					$attach[] = $row;
				}
			}
		}
		
		if( $allMatch ) {
			if( count( $rows ) == 1 ) {
				wfDebugLog( 'CentralAuth',
					"Singleton migration for '$this->mName'" );
			} else {
				wfDebugLog( 'CentralAuth',
					"Full automatic migration for '$this->mName'" );
			}
		} else {
			wfDebugLog( 'CentralAuth',
				"Incomplete migration for '$this->mName'" );
		}

		$this->storeGlobalData(
			$winner->lu_id,
			$winner->lu_password,
			$winner->lu_email,
			$winner->lu_email_authenticated );
		
		foreach( $attach as $row ) {
			$this->attach( $row->lu_dbname );
		}
	
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
			if( $this->matchHash( $password, $row->lu_id, $row->lu_password ) ) {
				wfDebugLog( 'CentralAuth',
					"Attaching '$this->mName' on $row->lu_dbname by password" );
				$this->attach( $row->lu_dbname );
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
	 * Check if the current username is defined and attached on this wiki yet
	 * @param $dbname Local database key to look up
	 * @return ("attached", "unattached", "no local user")
	 */
	function isAttached( $dbname ) {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		$row = $dbr->selectRow( 'localuser',
			array( 'lu_attached' ),
			array( 'lu_name' => $this->mName, 'lu_database' => $dbname ),
			__METHOD__ );
		
		if( !$row ) {
			return "no local user";
		}
		
		if( $row->lu_attached ) {
			return "attached";
		} else {
			return "unattached";
		}
	}
	
	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param 
	 */
	function addLocal( $dbname, $localid ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->insert( 'localuser',
			array(
				'lu_dbname'   => $dbname,
				'lu_id'       => $localid,
				'lu_name'     => $this->mName,
				'lu_attached' => 1 ),
			__METHOD__ );
	}
	
	/**
	 * Declare the local account for a given wiki to be attached
	 * to the global account for the current username.
	 *
	 * @return true on success
	 */
	public function attach( $dbname ) {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$dbw->update( 'localuser',
			array(
				// Boo-yah!
				'lu_attached' => 1,
				
				// Local information fields become obsolete
				/*
				'lu_email'               => NULL,
				'lu_email_authenticated' => NULL,
				'lu_password'            => NULL,
				*/
				),
			array(
				'lu_dbname' => $dbname,
				'lu_name'   => $this->mName ),
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
		$row = $dbw->selectRow( 'globaluser',
			array( 'gu_salt', 'gu_password', 'gu_locked' ),
			array( 'gu_name' => $this->mName ),
			__METHOD__ );
		
		if( !$row ) {
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
		$rows = $this->fetchUnattached;
		$dbs = array();
		foreach( $rows as $row ) {
			$dbs[] = $row->lu_dbname;
		}
		return $dbs;
	}
	
	function fetchUnattached() {
		$dbw = wfGetDB( DB_MASTER, 'CentralAuth' );
		$result = $dbw->select( 'localuser',
			array(
				'lu_dbname',
				'lu_id',
				'lu_name',
				'lu_password',
				'lu_email',
				'lu_email_authenticated',
				'lu_editcount',
				'lu_attached',
			),
			array(
				'lu_name'     => $this->mName,
				'lu_attached' => 0,
			),
			__METHOD__ );
		while( $row = $dbw->fetchObject( $result ) ) {
			$rows[] = $row;
		}
		$dbw->freeResult( $result );
		return $rows;
	}
	
	function getEmail() {
		$dbr = wfGetDB( DB_MASTER, 'CentralAuth' );
		return $dbr->selectField( 'globaluser', 'gu_email',
			array( 'gu_name' => $this->mName ),
			__METHOD__ );
	}

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
		$result = $dbr->update( 'globaluser',
			array(
				'gu_salt'     => $salt,
				'gu_password' => $hash,
			),
			array(
				'gu_name' => $this->mName,
			),
			__METHOD__ );
		
		$rows = $dbw->numRows( $result );
		$dbw->freeResult( $result );
		
		return $rows > 0;
	}

}

/**
 * Quickie test implementation using local test database
 */
class CentralAuth extends AuthPlugin {
	static function factory() {
		global $wgCentralAuthState;
		switch( $wgCentralAuthState ) {
		case 'premigrate':
		case 'testing':
			// FIXME
			return new AuthPlugin();
		case 'migration':
		case 'production':
			return new CentralAuth();
		default:
			die('wtf');
		}
	}
	
	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @return bool
	 * @public
	 */
	function userExists( $username ) {
		$user = new CentralAuthUser( $username );
		return $user->exists();
	}

	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @param $password String: user password.
	 * @return bool
	 * @public
	 */
	function authenticate( $username, $password ) {
		$user = new CentralAuthUser( $username );
		return $user->authenticate( $password ) == "ok";
	}

	/**
	 * When a user logs in, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param User $user
	 * @public
	 */
	function updateUser( &$user ) {
		# Override this and do something
		return true;
	}


	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	function autoCreate() {
		return true;
	}

	/**
	 * Set the given password in the authentication database.
	 * Return true if successful.
	 *
	 * @param $password String: password.
	 * @return bool
	 * @public
	 */
	function setPassword( $password ) {
		// Fixme: password changes should happen through central interface.
		$global = CentralAuthUser( $user->getName() );
		return $global->setPassword( $password );
	}

	/**
	 * Update user information in the external authentication database.
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @return bool
	 * @public
	 */
	function updateExternalDB( $user ) {
		return true;
	}

	/**
	 * Check to see if external accounts can be created.
	 * Return true if external accounts can be created.
	 * @return bool
	 * @public
	 */
	function canCreateAccounts() {
		// Require accounts to be created through the central login interface?
		return true;
	}

	/**
	 * Add a user to the external authentication database.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 * @public
	 */
	function addUser( $user, $password ) {
		$global = new CentralAuthUser( $user->getName() );
		return $global->register( $password );
	}


	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	function strict() {
		return true;
	}

	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object.
	 * @public
	 */
	function initUser( &$user ) {
		# Override this to do something.
		$global = new CentralAuthUser( $user->getName() );
		$user->setEmail( $global->getEmail() );
		// etc
	}
}

?>
