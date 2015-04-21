<?php
/*

likely construction types...

- give me the global account for this local user id
- none? give me the global account for this name

- create me a global account for this name

*/

class CentralAuthUser extends AuthPluginUser {
	/**
	 * The username of the current user.
	 * @var string
	 */
	/*private*/ var $mName;
	/*private*/ var $mStateDirty = false;
	/*private*/ var $mVersion = 6;
	/*private*/ var $mDelayInvalidation = 0;

	var $mAttachedArray, $mEmail, $mEmailAuthenticated, $mHomeWiki, $mHidden, $mLocked, $mAttachedList, $mAuthenticationTimestamp;
	var $mGroups, $mRights, $mPassword, $mAuthToken, $mSalt, $mGlobalId, $mFromMaster, $mIsAttached, $mRegistration, $mGlobalEditCount;
	var $mBeingRenamed, $mBeingRenamedArray;
	protected $mAttachedInfo;
	/** @var integer */
	protected $mCasToken = 0;

	static $mCacheVars = array(
		'mGlobalId',
		'mSalt',
		'mPassword',
		'mAuthToken',
		'mLocked',
		'mHidden',
		'mRegistration',
		'mEmail',
		'mAuthenticationTimestamp',
		'mGroups',
		'mRights',
		'mHomeWiki',
		'mBeingRenamed',

		# Store the string list instead of the array, to save memory, and
		# avoid unserialize() overhead
		'mAttachedList',

		'mCasToken',
		'mVersion',
	);

	const HIDDEN_NONE = '';
	const HIDDEN_LISTS = 'lists';
	const HIDDEN_OVERSIGHT = 'suppressed';

	// The maximum number of edits a user can have and still be hidden
	const HIDE_CONTRIBLIMIT = 1000;

	/**
	 * @param $username string
	 */
	function __construct( $username ) {
		$this->mName = $username;
		$this->resetState();
	}

	/**
	 * Create a CentralAuthUser object corresponding to the supplied User, and
	 * cache it in the User object.
	 * @param User $user
	 *
	 * @return CentralAuthUser
	 */
	static function getInstance( User $user ) {
		if ( !isset( $user->centralAuthObj ) ) {
			$user->centralAuthObj = new self( $user->getName() );
		}
		return $user->centralAuthObj;
	}

	/**
	 * Gets a master (read/write) database connection to the CentralAuth database
	 *
	 * @return DatabaseBase
	 * @throws CentralAuthReadOnlyError
	 */
	public static function getCentralDB() {
		global $wgCentralAuthDatabase, $wgCentralAuthReadOnly;

		if ( $wgCentralAuthReadOnly ) {
			throw new CentralAuthReadOnlyError();
		}

		return wfGetLB( $wgCentralAuthDatabase )->getConnection( DB_MASTER, array(),
			$wgCentralAuthDatabase );
	}

	/**
	 * Gets a slave (readonly) database connection to the CentralAuth database
	 *
	 * @return DatabaseBase
	 */
	public static function getCentralSlaveDB() {
		global $wgCentralAuthDatabase;

		return wfGetLB( $wgCentralAuthDatabase )->getConnection(
			DB_SLAVE, 'centralauth', $wgCentralAuthDatabase );
	}

	/**
	 * @return bool hasOrMadeRecentMasterChanges() on the central load balancer
	 */
	protected static function centralLBHasRecentMasterChanges() {
		global $wgCentralAuthDatabase;

		return wfGetLB( $wgCentralAuthDatabase )->hasOrMadeRecentMasterChanges();
	}

	public static function waitForSlaves() {
		global $wgCentralAuthDatabase;

		wfWaitForSlaves( false, $wgCentralAuthDatabase );
	}

	/**
	 * @param $wikiID
	 * @return DatabaseBase
	 */
	public static function getLocalDB( $wikiID ) {
		return wfGetLB( $wikiID )->getConnection( DB_MASTER, array(), $wikiID );
	}

	/**
	 * Get a CentralAuthUser object from a user's id
	 *
	 * @param int $id
	 * @return CentralAuthUser|bool false if no user exists with that id
	 */
	public static function newFromId( $id ) {
		$name = self::getCentralSlaveDB()->selectField(
			'globaluser',
			'gu_name',
			array( 'gu_id' => $id ),
			__METHOD__
		);

		if ( $name ) {
			return new CentralAuthUser( $name );
		} else {
			return false;
		}
	}

	/**
	 * Create a CentralAuthUser object from a joined globaluser/localuser row
	 *
	 * @param $row ResultWrapper|object
	 * @param $renameUser array Empty if no rename is going on, else (oldname, newname)
	 * @param $fromMaster bool
	 * @return CentralAuthUser
	 */
	public static function newFromRow( $row, $renameUser, $fromMaster = false ) {
		$caUser = new self( $row->gu_name );
		$caUser->loadFromRow( $row, $renameUser, $fromMaster );
		return $caUser;
	}

	/**
	 * Create a CentralAuthUser object for a user who is known to be unattached.
	 * @param $name string The user name
	 * @param $fromMaster bool
	 * @return CentralAuthUser
	 */
	public static function newUnattached( $name, $fromMaster = false ) {
		$caUser = new self( $name );
		$caUser->loadFromRow( false, array(), $fromMaster );
		return $caUser;
	}

	/**
	 * Clear state information cache
	 * Does not clear $this->mName, so the state information can be reloaded with loadState()
	 */
	protected function resetState() {
		unset( $this->mGlobalId );
		unset( $this->mGroups );
		unset( $this->mAttachedArray );
		unset( $this->mAttachedList );
		unset( $this->mHomeWiki );
	}

	/**
	 * Load up state information, but don't use the cache
	 */
	public function loadStateNoCache() {
		$this->loadState( true );
	}

	/**
	 * Lazy-load up the most commonly required state information
	 * @param boolean $recache Force a load from the database then save back to the cache
	 */
	protected function loadState( $recache = false ) {
		if ( $recache ) {
			$this->resetState();
		} elseif ( isset( $this->mGlobalId ) ) {
			// Already loaded
			return;
		}

		// Check the cache
		if ( !$recache && $this->loadFromCache() ) {
			return;
		}

		wfDebugLog( 'CentralAuthVerbose', "Loading state for global user {$this->mName} from DB" );

		$this->mFromMaster = self::centralLBHasRecentMasterChanges();
		if ( $this->mFromMaster ) {
			$db = self::getCentralDB();
		} else {
			$db = self::getCentralSlaveDB();
		}

		$row = $db->selectRow(
			array( 'globaluser', 'localuser' ),
			array(
				'gu_id', 'lu_wiki', 'gu_salt', 'gu_password', 'gu_auth_token',
				'gu_locked', 'gu_hidden', 'gu_registration', 'gu_email',
				'gu_email_authenticated', 'gu_home_db', 'gu_cas_token'
			),
			array( 'gu_name' => $this->mName ),
			__METHOD__,
			array(),
			array(
				'localuser' => array( 'LEFT OUTER JOIN', array( 'gu_name=lu_name', 'lu_wiki' => wfWikiID() ) )
			)
		);

		$renameUserStatus = new GlobalRenameUserStatus( $this->mName );
		$renameUser = $renameUserStatus->getNames( null, $this->mFromMaster ? 'master' : 'slave' );

		$this->loadFromRow( $row, $renameUser, $this->mFromMaster );
		$this->saveToCache();
	}

	/**
	 * Load user groups and rights from the database.
	 */
	protected function loadGroups() {
		if ( isset( $this->mGroups ) ) {
			// Already loaded
			return;
		}
		// We need the user id from the database, but this should be checked by the getId accessor.

		wfDebugLog( 'CentralAuthVerbose', "Loading groups for global user {$this->mName}" );

		$db = $this->mFromMaster ? self::getCentralDB() : self::getCentralSlaveDB();

		$res = $db->select(
			array( 'global_group_permissions', 'global_user_groups' ),
			array( 'ggp_permission', 'ggp_group' ),
			array( 'ggp_group=gug_group', 'gug_user' => $this->getId() ),
			__METHOD__
		);

		$resSets = $db->select(
			array( 'global_user_groups', 'global_group_restrictions', 'wikiset' ),
			array( 'ggr_group', 'ws_id', 'ws_name', 'ws_type', 'ws_wikis' ),
			array( 'ggr_group=gug_group', 'ggr_set=ws_id', 'gug_user' => $this->getId() ),
			__METHOD__
		);

		$sets = array();
		foreach ( $resSets as $row ) {
			/* @var $row object */
			$sets[$row->ggr_group] = WikiSet::newFromRow( $row );
		}

		// Grab the user's rights/groups.
		$rights = array();
		$groups = array();

		foreach ( $res as $row ) {
			/** @var $set User|bool */
			$set = isset( $sets[$row->ggp_group] ) ? $sets[$row->ggp_group] : '';
			$rights[] = array( 'right' => $row->ggp_permission, 'set' => $set ? $set->getID() : false );
			$groups[$row->ggp_group] = 1;
		}

		$this->mRights = $rights;
		$this->mGroups = array_keys( $groups );
	}

	/**
	 * Load user state from a joined globaluser/localuser row
	 *
	 * @param $row ResultWrapper|object|bool
	 * @param $renameUser array Empty if no rename is going on, else (oldname, newname)
	 * @param $fromMaster bool
	 */
	protected function loadFromRow( $row, $renameUser, $fromMaster = false ) {
		if ( $row ) {
			$this->mGlobalId = intval( $row->gu_id );
			$this->mIsAttached = ( $row->lu_wiki !== null );
			$this->mSalt = $row->gu_salt;
			$this->mPassword = $row->gu_password;
			$this->mAuthToken = $row->gu_auth_token;
			$this->mLocked = $row->gu_locked;
			$this->mHidden = $row->gu_hidden;
			$this->mRegistration = wfTimestamp( TS_MW, $row->gu_registration );
			$this->mEmail = $row->gu_email;
			$this->mAuthenticationTimestamp =
				wfTimestampOrNull( TS_MW, $row->gu_email_authenticated );
			$this->mFromMaster = $fromMaster;
			$this->mHomeWiki = $row->gu_home_db;
			$this->mCasToken = $row->gu_cas_token;
		} else {
			$this->mGlobalId = 0;
			$this->mIsAttached = false;
			$this->mFromMaster = $fromMaster;
			$this->mLocked = false;
			$this->mHidden = '';
			$this->mCasToken = 0;
		}

		if ( $renameUser ) {
			$this->mBeingRenamedArray = $renameUser;
			$this->mBeingRenamed = implode( '|', $this->mBeingRenamedArray );
		} else {
			$this->mBeingRenamedArray = array();
			$this->mBeingRenamed = '';
		}
	}

	/**
	 * Load data from memcached
	 *
	 * @param $cache Array
	 * @param $fromMaster Bool
	 * @return bool
	 */
	protected function loadFromCache( $cache = null, $fromMaster = false ) {
		if ( $cache == null ) {
			$cache = ObjectCache::getMainWANInstance()->get( $this->getCacheKey() );
			$fromMaster = true;
		}

		if ( !is_array( $cache ) || $cache['mVersion'] < $this->mVersion ) {
			// Out of date cache.
			wfDebugLog( 'CentralAuthVerbose', "Global User: cache miss for {$this->mName}, " .
				"version {$cache['mVersion']}, expected {$this->mVersion}" );
			return false;
		}

		$this->loadFromCacheObject( $cache, $fromMaster );

		return true;
	}

	/**
	 * Load user state from a cached array.
	 *
	 * @param $object Array
	 * @param $fromMaster Bool
	 */
	protected function loadFromCacheObject( $object, $fromMaster = false ) {
		wfDebugLog( 'CentralAuthVerbose', "Loading CentralAuthUser for user {$this->mName} from cache object" );
		foreach ( self::$mCacheVars as $var ) {
			$this->$var = $object[$var];
		}

		$this->loadAttached();

		$this->mIsAttached = $this->exists() && in_array( wfWikiID(), $this->mAttachedArray );
		$this->mFromMaster = $fromMaster;
	}

	/**
	 * Get the object data as an array ready for caching
	 * @return Object to cache.
	 */
	protected function getCacheObject() {
		$this->loadState();
		$this->loadAttached();
		$this->loadGroups();

		$obj = array();
		foreach ( self::$mCacheVars as $var ) {
			if ( isset( $this->$var ) ) {
				$obj[$var] = $this->$var;
			} else {
				$obj[$var] = null;
			}
		}

		return $obj;
	}

	/**
	 * Save cachable data to memcached.
	 */
	protected function saveToCache() {
		// Make sure the data is fresh
		if ( isset( $this->mGlobalId ) && !$this->mFromMaster ) {
			$this->resetState();
		}

		$ttl = $this->mFromMaster ? 86400 : 30;

	 	$obj = $this->getCacheObject();
	 	wfDebugLog( 'CentralAuthVerbose', "Saving user {$this->mName} to cache." );
		ObjectCache::getMainWANInstance()->set( $this->getCacheKey(), $obj, $ttl );
	}

	/**
	 * Return the global account ID number for this account, if it exists.
	 * @return Int
	 */
	public function getId() {
		$this->loadState();
		return $this->mGlobalId;
	}

	/**
	 * Generate a valid memcached key for caching the object's data.
	 * @return String
	 */
	protected function getCacheKey() {
		return "centralauth-user-" . md5( $this->mName );
	}

	/**
	 * Return the global account's name, whether it exists or not.
	 * @return String
	 */
	public function getName() {
		return $this->mName;
	}

	/**
	 * @return bool True if the account is attached on the local wiki
	 */
	public function isAttached() {
		$this->loadState();
		return $this->mIsAttached;
	}

	/**
	 * Return the password.
	 *
	 * @return Password
	 */
	protected function getPasswordObject() {
		$this->loadState();
		return $this->getPasswordFromString( $this->mPassword, $this->mSalt );
	}

	/**
	 * Return the global-login token for this account.
	 */
	public function getAuthToken() {
		$this->loadState();

		if ( !isset( $this->mAuthToken ) || !$this->mAuthToken ) {
			$this->resetAuthToken();
		}
		return $this->mAuthToken;
	}

	/**
	 * Check whether a global user account for this name exists yet.
	 * If migration state is set for pass 1, this may trigger lazy
	 * evaluation of automatic migration for the account.
	 *
	 * @return bool
	 */
	public function exists() {
		$id = $this->getId();
		return $id != 0;
	}

	/**
	 * Returns whether the account is
	 * locked.
	 * @return bool
	 */
	public function isLocked() {
		$this->loadState();
		return (bool)$this->mLocked;
	}

	/**
	 * Returns whether user name should not
	 * be shown in public lists.
	 * @return bool
	 */
	public function isHidden() {
		$this->loadState();
		return (bool)$this->mHidden;
	}

	/**
	 * Returns whether user's name should
	 * be hidden from all public views because
	 * of privacy issues.
	 * @return bool
	 */
	public function isOversighted() {
		$this->loadState();
		return $this->mHidden == self::HIDDEN_OVERSIGHT;
	}

	/**
	 * Returns the hidden level of
	 * the account.
	 */
	public function getHiddenLevel() {
		$this->loadState();

		// backwards compatibility for mid-migration
		if ( strval( $this->mHidden ) === '0' ) {
			$this->mHidden = '';
		} elseif ( strval( $this->mHidden ) === '1' ) {
			$this->mHidden = self::HIDDEN_LISTS;
		}

		return $this->mHidden;
	}

	/**
	 * @return string timestamp
	 */
	public function getRegistration() {
		$this->loadState();
		return wfTimestamp( TS_MW, $this->mRegistration );
	}

	/**
	 * Return the id of the user's home wiki.
	 *
	 * @return string|null Null if the account has no attached wikis
	 */
	public function getHomeWiki() {
		$this->loadState();

		if ( $this->mHomeWiki !== null && $this->mHomeWiki !== '' ) {
			return $this->mHomeWiki;
		}

		$attached = $this->queryAttached();

		if ( !count( $attached ) ) {
			return null;
		}

		foreach ( $attached as $wiki => $acc ) {
			if ( $acc['attachedMethod'] == 'primary' || $acc['attachedMethod'] == 'new' ) {
				$this->mHomeWiki = $wiki;
				break;
			}
		}

		// Still null... try harder.
		if ( $this->mHomeWiki === null || $this->mHomeWiki === '' ) {
			reset( $attached );
			$this->mHomeWiki = key( $attached ); // Make sure we always have some value
			$maxEdits = -1;
			foreach ( $attached as $wiki => $acc ) {
				if ( isset( $acc['editCount'] ) && $acc['editCount'] > $maxEdits ) {
					$this->mHomeWiki = $wiki;
					$maxEdits = $acc['editCount'];
				}
			}
		}

		return $this->mHomeWiki;
	}

	/**
	 * @return integer total number of edits for all wikis
	 */
	public function getGlobalEditCount() {
		if ( $this->mGlobalEditCount === null ) {
			$this->mGlobalEditCount = 0;
			foreach ( $this->queryAttached() as $acc ) {
				if ( isset( $acc['editCount'] ) ) {
					$this->mGlobalEditCount += (int)$acc['editCount'];
				}
			}
		}
		return $this->mGlobalEditCount;
	}

	/**
	 * Register a new, not previously existing, central user account
	 * Remaining fields are expected to be filled out shortly...
	 * eeeyuck
	 *
	 * @param $password String
	 * @param $email String
	 * @return bool
	 */
	function register( $password, $email ) {
		$dbw = self::getCentralDB();
		list( $salt, $hash ) = $this->saltedPassword( $password );
		$dbw->insert(
			'globaluser',
			array(
				'gu_name'  => $this->mName,

				'gu_email' => $email,
				'gu_email_authenticated' => null,

				'gu_salt'     => $salt,
				'gu_password' => $hash,

				'gu_locked' => 0,
				'gu_hidden' => '',

				'gu_registration' => $dbw->timestamp(),
			),
			__METHOD__,
			array( 'IGNORE' )
		);

		$ok = $dbw->affectedRows() === 1;
		if ( $ok ) {
			wfDebugLog( 'CentralAuth',
				"registered global account '$this->mName'" );
		} else {
			wfDebugLog( 'CentralAuth',
				"registration failed for global account '$this->mName'" );
		}

		// Kill any cache entries saying we don't exist
		$this->invalidateCache();
		return $ok;
	}

	/**
	 * For use in migration pass zero.
	 * Store local user data into the auth server's migration table.
	 * @param string $wiki Source wiki ID
	 * @param array $users Associative array of ids => names
	 */
	static function storeMigrationData( $wiki, $users ) {
		if ( $users ) {
			$dbw = self::getCentralDB();
			$globalTuples = array();
			$tuples = array();
			foreach ( $users as $name ) {
				$globalTuples[] = array( 'gn_name' => $name );
				$tuples[] = array(
					'ln_wiki' => $wiki,
					'ln_name' => $name
				);
			}
			$dbw->insert(
				'globalnames',
				$globalTuples,
				__METHOD__,
				array( 'IGNORE' ) );
			$dbw->insert(
				'localnames',
				$tuples,
				__METHOD__,
				array( 'IGNORE' ) );
		}
	}

	/**
	 * Store global user data in the auth server's main table.
	 *
	 * @param $salt String
	 * @param $hash String
	 * @param $email String
	 * @param $emailAuth String timestamp
	 * @return bool Whether we were successful or not.
	 */
	protected function storeGlobalData( $salt, $hash, $email, $emailAuth ) {
		$dbw = self::getCentralDB();
		$dbw->insert( 'globaluser',
			array(
				'gu_name' => $this->mName,
				'gu_salt' => $salt,
				'gu_password' => $hash,
				'gu_email' => $email,
				'gu_email_authenticated' => $dbw->timestampOrNull( $emailAuth ),
				'gu_registration' => $dbw->timestamp(), // hmmmm
				'gu_locked' => 0,
				'gu_hidden' => '',
			),
			__METHOD__,
			array( 'IGNORE' ) );

		$this->resetState();
		return $dbw->affectedRows() != 0;
	}

	/**
	 * @param array $passwords
	 * @param bool $sendToRC
	 * @param bool $safe Only allow migration if all users can be migrated
	 * @param bool $checkHome Re-check the user's ownership of the home wiki
	 * @return bool
	 */
	public function storeAndMigrate( $passwords = array(), $sendToRC = true, $safe = false, $checkHome = false ) {
		$dbw = self::getCentralDB();
		$dbw->begin();

		$ret = $this->attemptAutoMigration( $passwords, $sendToRC, $safe, $checkHome );
		if ( $ret === true ) {
			$this->recordAntiSpoof();
		}

		$dbw->commit();
		return $ret;
	}

	/**
	 * Record the current username in the central AntiSpoof system
	 * if that feature is enabled
	 */
	protected function recordAntiSpoof() {
		if ( class_exists( 'CentralAuthSpoofUser' ) ) {
			$spoof = new CentralAuthSpoofUser( $this->mName );
			$spoof->record();
		}
	}

	/**
	 * Remove the current username from the central AntiSpoof system
	 * if that feature is enabled
	 */
	public function removeAntiSpoof() {
		if ( class_exists( 'CentralAuthSpoofUser' ) ) {
			$spoof = new CentralAuthSpoofUser( $this->mName );
			$spoof->remove();
		}
	}

	/**
	 * Out of the given set of local account data, pick which will be the
	 * initially-assigned home wiki.
	 *
	 * This will be the account with the highest edit count, either out of
	 * all privileged accounts or all accounts if none are privileged.
	 *
	 * @param array $migrationSet
	 * @throws Exception
	 * @return string
	 */
	function chooseHomeWiki( $migrationSet ) {
		if ( empty( $migrationSet ) ) {
			throw new Exception( 'Logic error -- empty migration set in chooseHomeWiki' );
		}

		// Sysops get priority
		$found = array();
		$priorityGroups = array( 'checkuser', 'oversight', 'bureaucrat', 'sysop' );
		foreach ( $priorityGroups as $group ) {
			foreach ( $migrationSet as $wiki => $local ) {
				if ( in_array( $group, $local['groups'] ) ) {
					$found[] = $wiki;
				}
			}
			if ( count( $found ) === 1 ) {
				// Easy!
				return $found[0];
			} elseif ( $found ) {
				// We'll check edit counts now...
				break;
			}
		}

		if ( !$found ) {
			// No privileged accounts; look among the plebes...
			$found = array_keys( $migrationSet );
		}

		$maxEdits = -1;
		$homeWiki = null;
		foreach ( $found as $wiki ) {
			$count = $migrationSet[$wiki]['editCount'];
			if ( $count > $maxEdits ) {
				$homeWiki = $wiki;
				$maxEdits = $count;
			} elseif ( $count === $maxEdits ) {
				// Tie, check earlier registration
				// Note that registration might be "null", which means they're a super old account.
				if ( $migrationSet[$wiki]['registration'] < $migrationSet[$homeWiki]['registration'] ) {
					$homeWiki = $wiki;
				} elseif ( $migrationSet[$wiki]['registration'] === $migrationSet[$homeWiki]['registration'] ) {
					// Another tie? Screw it, pick one randomly.
					$wikis = array( $wiki, $homeWiki );
					$homeWiki = $wikis[mt_rand( 0, 1 )];
				}
			}
		}

		return $homeWiki;
	}

	/**
	 * Go through a list of migration data looking for those which
	 * can be automatically migrated based on the available criteria.
	 *
	 * @param $migrationSet Array
	 * @param $passwords Array Optional, pre-authenticated passwords.
	 *     Should match an account which is known to be attached.
	 * @return Array of <wiki> => <authentication method>
	 */
	function prepareMigration( $migrationSet, $passwords = array() ) {
		// If the primary account has an email address set,
		// we can use it to match other accounts. If it doesn't,
		// we can't be sure that the other accounts with no mail
		// are the same person, so err on the side of caution.
		//
		// For additional safety, we'll only let the mail check
		// propagate from a confirmed account
		$passingMail = array();
		if ( $this->mEmail != '' && $this->mEmailAuthenticated ) {
			$passingMail[$this->mEmail] = true;
		}

		$passwordConfirmed = array();
		// If we've got an authenticated password to work with, we can
		// also assume their email addresses are useful for this purpose...
		if ( $passwords ) {
			foreach ( $migrationSet as $wiki => $local ) {
				if ( $local['email'] && $local['emailAuthenticated'] && !isset( $passingMail[$local['email']] ) ) {
					// Test passwords only once here as comparing hashes is very expensive
					$passwordConfirmed[$wiki] = $this->matchHashes(
						$passwords,
						$this->getPasswordFromString( $local['password'],  $local['id'] )
					);

					if ( $passwordConfirmed[$wiki] ) {
						$passingMail[$local['email']] = true;
					}
				}
			}
		}

		$attach = array();
		foreach ( $migrationSet as $wiki => $local ) {
			$localName = "$this->mName@$wiki";
			if ( $wiki == $this->mHomeWiki ) {
				// Primary account holder... duh
				$method = 'primary';
			} elseif ( $local['emailAuthenticated'] && isset( $passingMail[$local['email']] ) ) {
				// Same email address as the primary account, or the same email address as another
				// password confirmed account, means we know they could reset their password, so we
				// give them the account.
				// Authenticated email addresses only to prevent merges with malicious users
				$method = 'mail';
			} elseif (
				isset( $passwordConfirmed[$wiki] ) && $passwordConfirmed[$wiki] ||
				!isset( $passwordConfirmed[$wiki] ) &&
					$this->matchHashes( $passwords, $this->getPasswordFromString( $local['password'],  $local['id'] ) )
			) {
				// Matches the pre-authenticated password, yay!
				$method = 'password';
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
			$attach[$wiki] = $method;
		}

		return $attach;
	}

	/**
	 * Do a dry run -- pick a winning master account and try to auto-merge
	 * as many as possible, but don't perform any actions yet.
	 *
	 * @param $passwords array
	 * @param $home String set to false if no permission to do checks
	 * @param $attached Array on success, list of wikis which will be auto-attached
	 * @param $unattached Array on success, list of wikis which won't be auto-attached
	 * @param $methods Array on success, associative array of each wiki's attachment method	 *
	 * @return Status object
	 */
	function migrationDryRun( $passwords, &$home, &$attached, &$unattached, &$methods ) {
		$home = false;
		$attached = array();
		$unattached = array();

		// First, make sure we were given the current wiki's password.
		$self = $this->localUserData( wfWikiID() );
		$selfPassword = $this->getPasswordFromString( $self['password'], $self['id'] );
		if ( !$this->matchHashes( $passwords, $selfPassword ) ) {
			wfDebugLog( 'CentralAuth', "dry run: failed self-password check" );
			return Status::newFatal( 'wrongpassword' );
		}

		$migrationSet = $this->queryUnattached();
		if ( empty( $migrationSet ) ) {
			wfDebugLog( 'CentralAuth', 'dry run: no accounts to merge, failed migration' );
			return Status::newFatal( 'centralauth-merge-no-accounts' );
		}
		$home = $this->chooseHomeWiki( $migrationSet );
		$local = $migrationSet[$home];

		// And we need to match the home wiki before proceeding...
		$localPassword = $this->getPasswordFromString( $local['password'], $local['id'] );
		if ( $this->matchHashes( $passwords, $localPassword ) ) {
			wfDebugLog( 'CentralAuth', "dry run: passed password match to home $home" );
		} else {
			wfDebugLog( 'CentralAuth', "dry run: failed password match to home $home" );
			return Status::newFatal( 'centralauth-merge-home-password' );
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

		return Status::newGood();
	}

	/**
	 * Promote an unattached account to a
	 * global one, using the provided homewiki
	 *
	 * @param string $wiki
	 * @return Status
	 */
	public function promoteToGlobal( $wiki ) {
		$unattached = $this->queryUnattached();
		if ( !isset( $unattached[$wiki] ) ) {
			return Status::newFatal( 'promote-not-on-wiki' );
		}

		$info = $unattached[$wiki];

		if ( $this->exists() ) {
			return Status::newFatal( 'promote-already-exists' );
		}

		$ok = $this->storeGlobalData(
			$info['id'],
			$info['password'],
			$info['email'],
			$info['emailAuthenticated']
		);
		if ( !$ok ) {
			// Race condition?
			return Status::newFatal( 'promote-already-exists' );
		}

		$this->attach( $wiki, 'primary' );

		$this->recordAntiSpoof();

		return Status::newGood();
	}

	/**
	 * Choose an email address to use from an array as obtained via self::queryUnattached.
	 *
	 * @param array[] $wikisToAttach
	 */
	private function chooseEmail( array $wikisToAttach ) {
		if ( $this->mEmail ) {
			return;
		}

		foreach ( $wikisToAttach as $attachWiki ) {
			if ( $attachWiki['email'] ) {
				$this->mEmail = $attachWiki['email'];
				$this->mEmailAuthenticated = $attachWiki['emailAuthenticated'];
				if ( $attachWiki['emailAuthenticated'] ) {
					// If the email is authenticated, stop searching
					return;
				}
			}
		}
	}

	/**
	 * Pick a winning master account and try to auto-merge as many as possible.
	 * @fixme add some locking or something
	 *
	 * @param $passwords Array
	 * @param $sendToRC bool
	 * @param $safe bool Only migrate if all accounts can be merged
	 * @param $checkHome bool Re-check the user's ownership of the home wiki
	 * @return bool Whether full automatic migration completed successfully.
	 */
	protected function attemptAutoMigration( $passwords = array(), $sendToRC = true, $safe = false, $checkHome = false ) {
		$migrationSet = $this->queryUnattached();
		if ( empty( $migrationSet ) ) {
			wfDebugLog( 'CentralAuth', 'no accounts to merge, failed migration' );
			return false;
		}

		if ( isset( $this->mHomeWiki ) ) {
			if ( !array_key_exists( $this->mHomeWiki, $migrationSet ) ) {
				wfDebugLog( 'CentralAuth',
					"Invalid home wiki specification '$this->mName'@'$this->mHomeWiki'" );
				return false;
			}
		} else {
			$this->mHomeWiki = $this->chooseHomeWiki( $migrationSet );
		}

		$home = $migrationSet[$this->mHomeWiki];

		// Check home wiki when the user is initiating this merge, just
		// like we did in migrationDryRun
		$homePassword = $this->getPasswordFromString( $home['password'], $home['id'] );
		if ( $checkHome && !$this->matchHashes( $passwords, $homePassword ) ) {
			wfDebugLog( 'CentralAuth',
				"auto migrate: failed password match to home {$this->mHomeWiki}" );
			return false;
		}

		$this->mEmail = $home['email'];
		$this->mEmailAuthenticated = $home['emailAuthenticated'];

		// Pick all the local accounts matching the "master" home account
		$attach = $this->prepareMigration( $migrationSet, $passwords );

		if ( $safe && count( $attach ) !== count( $migrationSet ) ) {
			wfDebugLog( 'CentralAuth', "Safe auto-migration for '$this->mName' failed" );
			return false;
		}

		$wikisToAttach = array_intersect_key( $migrationSet, $attach );

		// The home wiki might not have an email set, but maybe an other account has one?
		$this->chooseEmail( $wikisToAttach );

		// storeGlobalData clears $this->mHomeWiki
		$homeWiki = $this->mHomeWiki;
		// Actually do the migration
		$ok = $this->storeGlobalData(
			$home['id'],
			$home['password'],
			$this->mEmail,
			$this->mEmailAuthenticated
		);

		if ( !$ok ) {
			wfDebugLog( 'CentralAuth',
				"attemptedAutoMigration for existing entry '$this->mName'" );
			return false;
		}

		if ( count( $attach ) < count( $migrationSet ) ) {
			wfDebugLog( 'CentralAuth',
				"Incomplete migration for '$this->mName'" );
		} else {
			if ( count( $migrationSet ) == 1 ) {
				wfDebugLog( 'CentralAuth',
					"Singleton migration for '$this->mName' on $homeWiki" );
			} else {
				wfDebugLog( 'CentralAuth',
					"Full automatic migration for '$this->mName'" );
			}
		}

		// Don't purge the cache 50 times.
		$this->startTransaction();

		foreach ( $attach as $wiki => $method ) {
			$this->attach( $wiki, $method, $sendToRC );
		}

		$this->endTransaction();

		return count( $attach ) == count( $migrationSet );
	}

	/**
	 * Attempt to migrate any remaining unattached accounts by virtue of
	 * the password check.
	 *
	 * @param $password string plaintext password to try matching
	 * @param $migrated array array of wiki IDs for records which were
	 *                  successfully migrated by this operation
	 * @param $remaining array of wiki IDs for records which are still
	 *                   unattached after the operation
	 * @return bool true if all accounts are migrated at the end
	 */
	public function attemptPasswordMigration( $password, &$migrated = null, &$remaining = null ) {
		$rows = $this->queryUnattached();

		if ( count( $rows ) == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Already fully migrated user '$this->mName'" );
			return true;
		}

		$migrated = array();
		$remaining = array();

		// Don't invalidate the cache 50 times
		$this->startTransaction();

		// Look for accounts we can match by password
		foreach ( $rows as $row ) {
			$wiki = $row['wiki'];
			if ( $this->matchHash( $password, $this->getPasswordFromString( $row['password'], $row['id'] ) )->isGood() ) {
				wfDebugLog( 'CentralAuth',
					"Attaching '$this->mName' on $wiki by password" );
				$this->attach( $wiki, 'password' );
				$migrated[] = $wiki;
			} else {
				wfDebugLog( 'CentralAuth',
					"No password match for '$this->mName' on $wiki" );
				$remaining[] = $wiki;
			}
		}

		$this->endTransaction();

		if ( count( $remaining ) == 0 ) {
			wfDebugLog( 'CentralAuth',
				"Successful auto migration for '$this->mName'" );
			return true;
		}

		wfDebugLog( 'CentralAuth',
			"Incomplete migration for '$this->mName'" );
		return false;
	}

	/**
	 * @static
	 * @throws Exception
	 * @param  $list
	 * @return array
	 */
	protected static function validateList( $list ) {
		$unique = array_unique( $list );
		$valid = array_intersect( $unique, self::getWikiList() );

		if ( count( $valid ) != count( $list ) ) {
			// fixme: handle this gracefully
			throw new Exception( "Invalid input" );
		}

		return $valid;
	}

	/**
	 * @static
	 * @return array
	 */
	public static function getWikiList() {
		global $wgLocalDatabases;
		static $wikiList;
		if ( is_null( $wikiList ) ) {
			Hooks::run( 'CentralAuthWikiList', array( &$wikiList ) );
			if ( is_null( $wikiList ) ) {
				$wikiList = $wgLocalDatabases;
			}
		}
		return $wikiList;
	}

	/**
	 * Unattach a list of local accounts from the global account
	 * @param array $list List of wiki names
	 * @return Status
	 */
	public function adminUnattach( $list ) {
		if ( !count( $list ) ) {
			return Status::newFatal( 'centralauth-admin-none-selected' );
		}
		$status = new Status;
		$valid = $this->validateList( $list );
		$invalid = array_diff( $list, $valid );
		foreach ( $invalid as $wikiName ) {
			$status->error( 'centralauth-invalid-wiki', $wikiName );
			$status->failCount++;
		}

		$dbcw = self::getCentralDB();
		$password = $this->getPassword();

		foreach ( $valid as $wikiName ) {
			# Delete the user from the central localuser table
			$dbcw->delete( 'localuser',
				array(
					'lu_name'   => $this->mName,
					'lu_wiki' => $wikiName ),
				__METHOD__ );
			if ( !$dbcw->affectedRows() ) {
				$wiki = WikiMap::getWiki( $wikiName );
				$status->error( 'centralauth-admin-already-unmerged', $wiki->getDisplayName() );
				$status->failCount++;
				continue;
			}

			# Touch the local user row, update the password
			$lb = wfGetLB( $wikiName );
			$dblw = $lb->getConnection( DB_MASTER, array(), $wikiName );
			$dblw->update( 'user',
				array(
					'user_touched' => wfTimestampNow(),
					'user_password' => $password
				), array( 'user_name' => $this->mName ), __METHOD__
			);

			$id = $dblw->selectField( 'user', 'user_id', array( 'user_name' => $this->mName ), __METHOD__ );
			$this->clearLocalUserCache( $wikiName, $id );

			$lb->reuseConnection( $dblw );

			$status->successCount++;
		}

		if ( in_array( wfWikiID(), $valid ) ) {
			$this->resetState();
		}

		$this->invalidateCache();

		return $status;
	}

	/**
	 * Delete a global account and log what happened
	 *
	 * @param $reason string Reason for the deletion
	 * @return Status
	 */
	function adminDelete( $reason ) {
		wfDebugLog( 'CentralAuth', "Deleting global account for user {$this->mName}" );
		$centralDB = self::getCentralDB();

		# Synchronise passwords
		$password = $this->getPassword();
		$localUserRes = $centralDB->select( 'localuser', '*',
			array( 'lu_name' => $this->mName ), __METHOD__ );
		$name = $this->getName();
		foreach ( $localUserRes as $localUserRow ) {
			/** @var $localUserRow object */
			$wiki = $localUserRow->lu_wiki;
			wfDebug( __METHOD__ . ": Fixing password on $wiki\n" );
			$lb = wfGetLB( $wiki );
			$localDB = $lb->getConnection( DB_MASTER, array(), $wiki );
			$localDB->update( 'user',
				array( 'user_password' => $password ),
				array( 'user_name' => $name ),
				__METHOD__
			);

			$id = $localDB->selectField( 'user', 'user_id',
				array( 'user_name' => $this->mName ), __METHOD__ );
			$this->clearLocalUserCache( $wiki, $id );

			$lb->reuseConnection( $localDB );
		}
		$wasSuppressed = $this->isOversighted();

		$centralDB->begin();
		# Delete and lock the globaluser row
		$centralDB->delete( 'globaluser', array( 'gu_name' => $this->mName ), __METHOD__ );
		if ( !$centralDB->affectedRows() ) {
			$centralDB->commit();
			return Status::newFatal( 'centralauth-admin-delete-nonexistent', $this->mName );
		}
		# Delete all global user groups for the user
		$centralDB->delete( 'global_user_groups', array( 'gug_user' => $this->getId() ), __METHOD__ );
		# Delete the localuser rows
		$centralDB->delete( 'localuser', array( 'lu_name' => $this->mName ), __METHOD__ );
		$centralDB->commit();

		if ( $wasSuppressed ) {
			// "suppress/delete" is taken by core, so use "cadelete"
			$this->logAction( 'cadelete', $reason, array(), /* $suppressLog = */ true );
		} else {
			$this->logAction( 'delete', $reason, array(), /* $suppressLog = */ false );
		}
		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Lock a global account
	 *
	 * @return Status
	 */
	function adminLock() {
		$dbw = self::getCentralDB();
		$dbw->begin();
		$dbw->update( 'globaluser', array( 'gu_locked' => 1 ),
			array( 'gu_name' => $this->mName ), __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			$dbw->commit();
			return Status::newFatal( 'centralauth-state-mismatch' );
		}
		$dbw->commit();

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Unlock a global account
	 *
	 * @return Status
	 */
	function adminUnlock() {
		$dbw = self::getCentralDB();
		$dbw->begin();
		$dbw->update( 'globaluser', array( 'gu_locked' => 0 ),
			array( 'gu_name' => $this->mName ), __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			$dbw->commit();
			return Status::newFatal( 'centralauth-state-mismatch' );
		}
		$dbw->commit();

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Change account hiding level.
	 *
	 * @param $level String CentralAuthUser::HIDDEN_ class constant
	 * @return Status
	 */
	function adminSetHidden( $level ) {
		$dbw = self::getCentralDB();
		$dbw->begin();
		$dbw->update( 'globaluser', array( 'gu_hidden' => $level ),
			array( 'gu_name' => $this->mName ), __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			$dbw->commit();
			return Status::newFatal( 'centralauth-admin-unhide-nonexistent', $this->mName );
		}
		$dbw->commit();

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Set locking and hiding settings for a Global User and log the changes made.
	 *
	 * @param $setLocked Bool|null
	 *  true = lock
	 *  false = unlock
	 *  null = don't change
	 * @param $setHidden String|null
	 *  hidden level, one of the HIDDEN_ constants
	 *  null = don't change
	 * @param $reason String reason for hiding
	 * @param $context IContextSource
	 * @return Status
	 */
	public function adminLockHide( $setLocked, $setHidden, $reason, IContextSource $context ) {
		$isLocked = $this->isLocked();
		$oldHiddenLevel = $this->getHiddenLevel();
		$lockStatus = $hideStatus = null;
		$added = array();
		$removed = array();

		if ( is_null( $setLocked ) ) {
			$setLocked = $isLocked;
		} elseif ( !$context->getUser()->isAllowed( 'centralauth-lock' ) ) {
			return Status::newFatal( 'centralauth-admin-not-authorized' );
		}

		if ( is_null( $setHidden ) ) {
			$setHidden = $oldHiddenLevel;
		} elseif ( $setHidden != self::HIDDEN_NONE
			|| $oldHiddenLevel != self::HIDDEN_NONE ) {
			if ( !$context->getUser()->isAllowed( 'centralauth-oversight' ) ) {
				return Status::newFatal( 'centralauth-admin-not-authorized' );
			} elseif ( $this->getGlobalEditCount() > self::HIDE_CONTRIBLIMIT ) {
				return Status::newFatal( $context->msg( 'centralauth-admin-too-many-edits', $this->mName )->numParams( self::HIDE_CONTRIBLIMIT ) );
			}
		}

		$returnStatus = Status::newGood();

		$hiddenLevels = array(
			self::HIDDEN_NONE,
			self::HIDDEN_LISTS,
			self::HIDDEN_OVERSIGHT
		);

		if ( !in_array( $setHidden, $hiddenLevels ) ) {
			$setHidden = self::HIDDEN_NONE;
		}

		if ( !$isLocked && $setLocked ) {
			$lockStatus = $this->adminLock();
			$added[] = $context->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		} elseif ( $isLocked && !$setLocked ) {
			$lockStatus = $this->adminUnlock();
			$removed[] = $context->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		}

		if ( $oldHiddenLevel != $setHidden ) {
			$hideStatus = $this->adminSetHidden( $setHidden );
			switch( $setHidden ) {
				case self::HIDDEN_NONE:
					if ( $oldHiddenLevel == self::HIDDEN_OVERSIGHT ) {
						$removed[] = $context->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text();
					} else {
						$removed[] = $context->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					}
					break;
				case self::HIDDEN_LISTS:
					$added[] = $context->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					if ( $oldHiddenLevel == self::HIDDEN_OVERSIGHT ) {
						$removed[] = $context->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text();
					}
					break;
				case self::HIDDEN_OVERSIGHT:
					$added[] = $context->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text();
					if ( $oldHiddenLevel == self::HIDDEN_LISTS ) {
						$removed[] = $context->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					}
					break;
			}

			if ( $setHidden == self::HIDDEN_OVERSIGHT ) {
				$this->suppress( $reason );
			} elseif ( $oldHiddenLevel == self::HIDDEN_OVERSIGHT ) {
				$this->unsuppress( $reason );
			}
		}

		$good =
			( is_null( $lockStatus ) || $lockStatus->isGood() ) &&
			( is_null( $hideStatus ) || $hideStatus->isGood() );

		// Setup Status object to return all of the information for logging
		if ( $good && ( count( $added ) || count( $removed ) ) ) {
			$added = count( $added ) ?
				implode( ', ', $added ) : $context->msg( 'centralauth-log-status-none' )->inContentLanguage()->text();
			$removed = count( $removed ) ?
				implode( ', ', $removed ) : $context->msg( 'centralauth-log-status-none' )->inContentLanguage()->text();

			$returnStatus->successCount = count( $added ) + count( $removed );
			$returnStatus->success['added'] = $added;
			$returnStatus->success['removed'] = $removed;

			$this->logAction(
				'setstatus',
				$reason,
				$returnStatus->success,
				$setHidden != self::HIDDEN_NONE
			);

		} elseif ( !$good ) {
			if ( !is_null( $lockStatus ) && !$lockStatus->isGood() ) {
				$returnStatus->merge( $lockStatus );
			}
			if ( !is_null( $hideStatus ) && !$hideStatus->isGood() ) {
				$returnStatus->merge( $hideStatus );
			}
		}

		return $returnStatus;
	}

	/**
	 * Suppresses all user accounts in all wikis.
	 * @param $reason String
	 */
	function suppress( $reason ) {
		global $wgUser;
		$this->doCrosswikiSuppression( true, $wgUser->getName(), $reason );
	}

	/**
	 * Unsuppresses all user accounts in all wikis.
	 *
	 * @param $reason String
	 */
	function unsuppress( $reason ) {
		global $wgUser;
		$this->doCrosswikiSuppression( false, $wgUser->getName(), $reason );
	}

	/**
	 * @param $suppress Bool
	 * @param $by String
	 * @param $reason String
	 */
	protected function doCrosswikiSuppression( $suppress, $by, $reason ) {
		global $wgCentralAuthWikisPerSuppressJob;
		$this->loadAttached();
		if ( count( $this->mAttachedArray ) <= $wgCentralAuthWikisPerSuppressJob ) {
			foreach ( $this->mAttachedArray as $wiki ) {
				$this->doLocalSuppression( $suppress, $wiki, $by, $reason );
			}
		} else {
			$jobParams = array(
				'username' => $this->getName(),
				'suppress' => $suppress,
				'by' => $by,
				'reason' => $reason,
			);
			$jobs = array();
			$chunks = array_chunk( $this->mAttachedArray, $wgCentralAuthWikisPerSuppressJob );
			foreach ( $chunks as $wikis ) {
				$jobParams['wikis'] = $wikis;
				$jobs[] = Job::factory(
					'crosswikiSuppressUser',
					Title::makeTitleSafe( NS_USER, $this->getName() ),
					$jobParams );
			}
			JobQueueGroup::singleton()->push( $jobs );
		}
	}

	/**
	 * Suppresses a local account of a user.
	 *
	 * @param $suppress Bool
	 * @param $wiki String
	 * @param $by String
	 * @param $reason String
	 * @return Array|null Error array on failure
	 */
	public function doLocalSuppression( $suppress, $wiki, $by, $reason ) {
		global $wgConf;

		$lb = wfGetLB( $wiki );
		$dbw = $lb->getConnection( DB_MASTER, array(), $wiki );
		$data = $this->localUserData( $wiki );

		if ( $suppress ) {
			list( , $lang ) = $wgConf->siteFromDB( $wiki );
			$langNames = Language::fetchLanguageNames();
			$lang = isset( $langNames[$lang] ) ? $lang : 'en';
			$blockReason = wfMessage( 'centralauth-admin-suppressreason', $by, $reason )
				->inLanguage( $lang )->text();

			$block = new Block( array(
				'address' => $this->mName,
				'user' => $data['id'],
				'reason' => $blockReason,
				'timestamp' => wfTimestampNow(),
				'expiry' => $dbw->getInfinity(),
				'createAccount' => true,
				'enableAutoblock' => true,
				'hideName' => true,
				'blockEmail' => true,
				'byText' => $by
			) );

			# On normal block, BlockIp hook would be run here, but doing
			# that from CentralAuth doesn't seem a good idea...

			if ( !$block->insert( $dbw ) ) {
				return array( 'ipb_already_blocked' );
			}
			# Ditto for BlockIpComplete hook.

			RevisionDeleteUser::suppressUserName( $this->mName, $data['id'], $dbw );

			# Locally log to suppress ?
		} else {
			$dbw->delete(
				'ipblocks',
				array(
					'ipb_user' => $data['id'],
					'ipb_by' => 0,	// Check whether this block was imposed globally
					'ipb_deleted' => true,
				),
				__METHOD__
			);

			// Unsuppress only if unblocked
			if ( $dbw->affectedRows() ) {
				RevisionDeleteUser::unsuppressUserName( $this->mName, $data['id'], $dbw );
			}
		}
		return null;
	}

	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param $wikiID String
	 * @param $method String
	 * @param $sendToRC bool
	 *
	 * Prerequisites:
	 * - completed migration state
	 */
	public function attach( $wikiID, $method = 'new', $sendToRC = true ) {
		$dbw = self::getCentralDB();
		$dbw->begin( __METHOD__ );
		$dbw->insert( 'localuser',
			array(
				'lu_wiki'               => $wikiID,
				'lu_name'               => $this->mName,
				'lu_attached_timestamp' => $dbw->timestamp(),
				'lu_attached_method'    => $method ),
			__METHOD__,
			array( 'IGNORE' )
		);
		$success = $dbw->affectedRows() === 1;
		$dbw->commit( __METHOD__ );

		if ( $wikiID === wfWikiID() ) {
			$this->resetState();
		}

		$this->invalidateCache();

		if ( !$success ) {
			wfDebugLog( 'CentralAuth',
				"Race condition? Already attached $this->mName@$wikiID, just tried by '$method'" );
			return;
		}
		wfDebugLog( 'CentralAuth',
			"Attaching local user $this->mName@$wikiID by '$method'" );

		if ( $dbw->writesOrCallbacksPending() ) {
			wfDebugLog(
				'CentralAuth-Bug39996', __METHOD__ . ": Database::writesOrCallbacksPending() returns "
					. "true after successful attach"
			);
		}

		if ( $sendToRC ) {
			global $wgCentralAuthRC;

			$userpage = Title::makeTitleSafe( NS_USER, $this->mName );

			foreach ( $wgCentralAuthRC as $rc ) {
				/** @var CARCFeedFormatter $formatter */
				$formatter = new $rc['formatter']();
				/** @var RCFeedEngine $engine */
				$engine = RecentChange::getEngine( $rc['uri'] );
				$engine->send( $rc, $formatter->getLine( $userpage, $wikiID ) );
			}
		}
	}

	/**
	 * If the user provides the correct password, would we let them log in?
	 * This encompasses checks on missing and locked accounts, at present.
	 * @return mixed: true if login available, or string status, one of: "no user", "locked"
	 */
	public function canAuthenticate() {
		if ( !$this->getId() ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' failed due to missing account" );
			return "no user";
		}

		// If the global account has been locked, we don't want to spam
		// other wikis with local account creations. But, if we have explicitly
		// given a list of pages that locked accounts should be able to edit,
		// we'll allow it.
		global $wgCentralAuthLockedCanEdit;
		if ( !count( $wgCentralAuthLockedCanEdit ) && $this->isLocked() ) {
			return "locked";
		}

		// Don't allow users to autocreate if they are oversighted.
		// If they do, their name will appear on local user list
		// (and since it contains private info, its inacceptable).
		// FIXME: this will give users "password incorrect" error.
		// Giving correct message requires AuthPlugin and SpecialUserlogin
		// rewriting.
		if ( !User::idFromName( $this->getName() ) && $this->isOversighted() )
			return "locked";

		return true;
	}

	/**
	 * Attempt to authenticate the global user account with the given password
	 * @param string $password
	 * @return string status, one of: "ok", "no user", "locked", or "bad password".
	 * @todo Currently only the "ok" result is used (i.e. either use, or return a bool).
	 */
	public function authenticate( $password ) {
		if ( ( $ret = $this->canAuthenticate() ) !== true ) {
			return $ret;
		}

		$status = $this->matchHash( $password, $this->getPasswordObject() );
		if ( $status->isGood() ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' succeeded" );
			if ( User::getPasswordFactory()->needsUpdate( $status->getValue() ) ) {
				$this->setPassword( $password );
				$this->saveSettings();
			}
			return "ok";
		} else {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' failed, bad pass" );
			return "bad password";
		}
	}

	/**
	 * Attempt to authenticate the global user account with the given global authtoken
	 * @param string $token
	 * @return string status, one of: "ok", "no user", "locked", or "bad token"
	 */
	public function authenticateWithToken( $token ) {
		if ( ( $ret = $this->canAuthenticate() ) !== true ) {
			return $ret;
		}

		if ( $this->validateAuthToken( $token ) ) {
			return "ok";
		} else {
			return "bad token";
		}
	}

	/**
	 * @param string $plaintext User-provided password plaintext.
	 * @param Password $password Password to check against
	 *
	 * @return Status
	 */
	protected function matchHash( $plaintext, Password $password ) {
		$matched = false;

		if ( $password->equals( $plaintext ) ) {
			$matched = true;
		} elseif ( !( $password instanceof Pbkdf2Password ) && function_exists( 'iconv' ) ) {
			// Some wikis were converted from ISO 8859-1 to UTF-8;
			// retained hashes may contain non-latin chars.
			$latin1 = iconv( 'UTF-8', 'WINDOWS-1252//TRANSLIT', $plaintext );
			if ( $password->equals( $latin1 ) ) {
				$matched = true;
			}
		}

		if ( $matched ) {
			return Status::newGood( $password );
		} else {
			return Status::newFatal( 'bad' );
		}
	}

	/**
	 * @param array $passwords
	 * @param Password $password Password to check against
	 *
	 * @return bool
	 */
	protected function matchHashes( array $passwords, Password $password ) {
		foreach ( $passwords as $plaintext ) {
			if ( $this->matchHash( $plaintext, $password )->isGood() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $encrypted string Fully salted and hashed database crypto text from db.
	 * @param $salt string The hash "salt", eg a local id for migrated passwords.
	 *
	 * @return Password
	 */
	private function getPasswordFromString( $encrypted, $salt ) {
		global $wgPasswordSalt;

		$passwordFactory = User::getPasswordFactory();

		if ( preg_match( '/^[0-9a-f]{32}$/', $encrypted ) ) {
			if ( $wgPasswordSalt ) {
				$encrypted = ":B:{$salt}:{$encrypted}";
			} else {
				$encrypted = ":A:{$encrypted}";
			}
		}

		return $passwordFactory->newFromCiphertext( $encrypted );
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
		if ( empty( $unattached ) ) {
			if ( $this->lazyImportLocalNames() ) {
				$unattached = $this->doListUnattached();
			}
		}
		return $unattached;
	}

	/**
	 * @return array
	 */
	private function doListUnattached() {
		// Make sure lazy-loading in listUnattached() works
		$db = ( $this->mFromMaster || self::centralLBHasRecentMasterChanges() )
			? self::getCentralDB()
			: self::getCentralSlaveDB();

		$result = $db->select(
			array( 'localnames', 'localuser' ),
			array( 'ln_wiki' ),
			array( 'ln_name' => $this->mName, 'lu_name IS NULL' ),
			__METHOD__,
			array(),
			array( 'localuser' => array( 'LEFT OUTER JOIN',
				array( 'ln_wiki=lu_wiki', 'ln_name=lu_name' ) ) )
		);

		$dbs = array();
		foreach ( $result as $row ) {
			/** @var $row object */
			$dbs[] = $row->ln_wiki;
		}

		return $dbs;
	}

	/**
	 * @param  $wikiID
	 * @return void
	 */
	function addLocalName( $wikiID ) {
		$dbw = self::getCentralDB();
		$this->lazyImportLocalNames();
		$dbw->insert( 'localnames',
			array(
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName ),
			__METHOD__,
			array( 'IGNORE' ) );
	}

	/**
	 * @param  $wikiID
	 * @return void
	 */
	function removeLocalName( $wikiID ) {
		$dbw = self::getCentralDB();
		$this->lazyImportLocalNames();
		$dbw->delete( 'localnames',
			array(
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName ),
			__METHOD__ );
	}

	/**
	 * Updates the localname table after a rename
	 * @param $wikiID
	 * @param $newname
	 */
	function updateLocalName( $wikiID, $newname ) {
		$dbw = self::getCentralDB();
		$dbw->update(
			'localnames',
			array( 'ln_name' => $newname ),
			array( 'ln_wiki' => $wikiID, 'ln_name' => $this->mName ),
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	function lazyImportLocalNames() {
		$dbw = self::getCentralDB();

		$result = $dbw->select( 'globalnames',
			array( '1' ),
			array( 'gn_name' => $this->mName ),
			__METHOD__,
			array( 'LIMIT' => 1 ) );
		$known = $result->numRows();
		$result->free();

		if ( $known ) {
			// No need...
			// Hmm.. what about wikis added after localnames was populated? -werdna
			return false;
		}

		return $this->importLocalNames();
	}

	/**
	 * Troll through the full set of local databases and list those
	 * which exist into the 'localnames' table.
	 *
	 * @return Bool whether any results were found
	 */
	function importLocalNames() {
		$rows = array();
		foreach ( self::getWikiList() as $wikiID ) {
			$lb = wfGetLB( $wikiID );
			$dbr = $lb->getConnection( DB_SLAVE, array(), $wikiID );
			$id = $dbr->selectField(
				"`$wikiID`.`user`",
				'user_id',
				array( 'user_name' => $this->mName ),
				__METHOD__ );
			if ( $id ) {
				$rows[] = array(
					'ln_wiki' => $wikiID,
					'ln_name' => $this->mName );
			}
			$lb->reuseConnection( $dbr );
		}

		$dbw = self::getCentralDB();
		$dbw->begin();
		$dbw->insert( 'globalnames',
			array( 'gn_name' => $this->mName ),
			__METHOD__,
			array( 'IGNORE' ) );
		if ( $rows ) {
			$dbw->insert( 'localnames',
				$rows,
				__METHOD__,
				array( 'IGNORE' ) );
		}
		$dbw->commit();

		return !empty( $rows );
	}

	/**
	 * Load the list of databases where this account has been successfully
	 * attached
	 */
	public function loadAttached() {
		if ( isset( $this->mAttachedArray ) ) {
			// Already loaded
			return;
		}

		if ( isset( $this->mAttachedList ) && $this->mAttachedList !== '' ) {
			// We have a list already, probably from the cache.
			$this->mAttachedArray = explode( "\n", $this->mAttachedList );

			return;
		}

		wfDebugLog( 'CentralAuthVerbose', "Loading attached wiki list for global user {$this->mName} from DB" );

		$db = $this->mFromMaster ? self::getCentralDB() : self::getCentralSlaveDB();

		$result = $db->select( 'localuser',
			array( 'lu_wiki' ),
			array( 'lu_name' => $this->mName ),
			__METHOD__ );

		$wikis = array();
		foreach ( $result as $row ) {
			/** @var $row object */
			$wikis[] = $row->lu_wiki;
		}

		$this->mAttachedArray = $wikis;
		$this->mAttachedList = implode( "\n", $wikis );
	}

	/**
	 * Fetch a list of databases where this account has been successfully
	 * attached.
	 *
	 * @return array database name strings
	 */
	public function listAttached() {
		$this->loadAttached();

		return $this->mAttachedArray;
	}

	/**
	 * Same as $this->renameInProgress, but only checks one wiki
	 * Not cached
	 * @see CentralAuthUser::renameInProgress
	 * @param string $wiki
	 * @return array|bool
	 */
	public function renameInProgressOn( $wiki ) {
		$renameState = new GlobalRenameUserStatus( $this->mName );

		// Use master as this is being used for various critical things
		$names = $renameState->getNames( $wiki, 'master' );

		if ( $names ) {
			return $names;
		} else {
			return false;
		}
	}

	/**
	 * Check if a rename from the old name is in progress
	 * @return array (oldname, newname) if being renamed, or empty if not
	 */
	public function renameInProgress() {
		$this->loadState();
		if ( $this->mBeingRenamedArray === null ) {
			$this->mBeingRenamedArray = $this->mBeingRenamed === ''
				? array() : explode( '|', $this->mBeingRenamed );
		}

		return $this->mBeingRenamedArray;
	}

	/**
	 * Get information about each local user attached to this account
	 *
	 * @return array Map of database name to property table with members:
	 *    wiki                  The wiki ID (database name)
	 *    attachedTimestamp     The MW timestamp when the account was attached
	 *    attachedMethod        Attach method: password, mail or primary
	 */
	public function queryAttached() {
		// Cache $wikis to avoid expensive query whenever possible
		if ( $this->mAttachedInfo !== null ) {
			return $this->mAttachedInfo;
		}

		$dbw = self::getCentralDB();

		$result = $dbw->select(
			'localuser',
			array(
				'lu_wiki',
				'lu_attached_timestamp',
				'lu_attached_method' ),
			array(
				'lu_name' => $this->mName ),
			__METHOD__ );

		$wikis = array();
		foreach ( $result as $row ) {
			/** @var $row object */
			$wikis[$row->lu_wiki] = array(
				'wiki' => $row->lu_wiki,
				'attachedTimestamp' => wfTimestampOrNull( TS_MW,
					 $row->lu_attached_timestamp ),
				'attachedMethod' => $row->lu_attached_method,
			);

			$localUser = $this->localUserData( $row->lu_wiki );

			// Just for fun, add local user data.
			// Displayed in the steward interface.
			$wikis[$row->lu_wiki] = array_merge( $wikis[$row->lu_wiki],
				$localUser );
		}

		$this->mAttachedInfo = $wikis;

		return $wikis;
	}

	/**
	 * Find any remaining migration records for this username which haven't gotten attached to some global account.
	 * Formatted as associative array with some data.
	 *
	 * @throws Exception
	 * @return array
	 */
	public function queryUnattached() {
		$wikiIDs = $this->listUnattached();

		$items = array();
		foreach ( $wikiIDs as $wikiID ) {
			$data = $this->localUserData( $wikiID );
			$items[$wikiID] = $data;
		}

		return $items;
	}

	/**
	 * Fetch a row of user data needed for migration.
	 *
	 * @param $wikiID String
	 * @throws Exception if local user not found
	 * @return array
	 */
	protected function localUserData( $wikiID ) {
		$lb = wfGetLB( $wikiID );
		$db = $lb->getConnection( DB_SLAVE, array(), $wikiID );
		$fields = array(
				'user_id',
				'user_email',
				'user_email_authenticated',
				'user_password',
				'user_editcount',
				'user_registration',
			);
		$conds = array( 'user_name' => $this->mName );
		$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		if ( !$row ) {
			# Row missing from slave, try the master instead
			$lb->reuseConnection( $db );
			$db = $lb->getConnection( DB_MASTER, array(), $wikiID );
			$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		}
		if ( !$row ) {
			$lb->reuseConnection( $db );
			throw new Exception( "Could not find local user data for {$this->mName}@{$wikiID}" );
		}

		/** @var $row object */

		$data = array(
			'wiki' => $wikiID,
			'id' => $row->user_id,
			'email' => $row->user_email,
			'emailAuthenticated' =>
				wfTimestampOrNull( TS_MW, $row->user_email_authenticated ),
			'registration' =>
				wfTimestampOrNull( TS_MW, $row->user_registration ),
			'password' => $row->user_password,
			'editCount' => $row->user_editcount,
			'groups' => array(),
			'blocked' => false );

		// Edit count field may not be initialized...
		if ( is_null( $row->user_editcount ) ) {
			$data['editCount'] = $db->selectField(
				'revision',
				'COUNT(*)',
				array( 'rev_user' => $data['id'] ),
				__METHOD__ );
		}

		// And we have to fetch groups separately, sigh...
		$result = $db->select( 'user_groups',
			array( 'ug_group' ),
			array( 'ug_user' => $data['id'] ),
			__METHOD__ );
		foreach ( $result as $row ) {
			$data['groups'][] = $row->ug_group;
		}
		$result->free();

		// And while we're in here, look for user blocks :D
		$result = $db->select( 'ipblocks',
			array(
				'ipb_expiry', 'ipb_reason', 'ipb_block_email',
				'ipb_anon_only', 'ipb_create_account',
				'ipb_enable_autoblock', 'ipb_allow_usertalk',
			),
			array( 'ipb_user' => $data['id'] ),
			__METHOD__ );
		global $wgLang;
		foreach ( $result as $row ) {
			if ( $wgLang->formatExpiry( $row->ipb_expiry, TS_MW ) > wfTimestampNow() ) {
				$data['block-expiry'] = $row->ipb_expiry;
				$data['block-reason'] = $row->ipb_reason;
				$data['block-anononly'] = (bool)$row->ipb_anon_only;
				$data['block-nocreate'] = (bool)$row->ipb_create_account;
				$data['block-noautoblock'] = !( (bool)$row->ipb_enable_autoblock );
				$data['block-nousertalk'] = !( (bool)$row->ipb_allow_usertalk ); // Poorly named database column
				$data['block-noemail'] = (bool)$row->ipb_block_email;
				$data['blocked'] = true;
			}
		}
		$result->free();
		$lb->reuseConnection( $db );

		return $data;
	}

	/**
	 * @return
	 */
	function getEmail() {
		$this->loadState();
		return $this->mEmail;
	}

	/**
	 * @return
	 */
	function getEmailAuthenticationTimestamp() {
		$this->loadState();
		return $this->mAuthenticationTimestamp;
	}

	/**
	 * @param string $email
	 * @return void
	 */
	function setEmail( $email ) {
		$this->loadState();
		if ( $this->mEmail !== $email ) {
			$this->mEmail = $email;
			$this->mStateDirty = true;
		}
	}

	/**
	 * @param  $ts
	 * @return void
	 */
	function setEmailAuthenticationTimestamp( $ts ) {
		$this->loadState();
		if ( $this->mAuthenticationTimestamp !== $ts ) {
			$this->mAuthenticationTimestamp = $ts;
			$this->mStateDirty = true;
		}
	}

	/**
	 * Salt and hash a new plaintext password.
	 * @param string $password plaintext
	 * @return array of strings, salt and hash
	 */
	protected function saltedPassword( $password ) {
		return array(
			'',
			User::getPasswordFactory()->newFromPlaintext( $password )->toString()
		);
	}

	/**
	 * Set the account's password
	 * @param $password String plaintext
	 * @param $resetAuthToken bool if we should reset the login token
	 * @return Bool true
	 */
	function setPassword( $password, $resetAuthToken = true ) {
		list( $salt, $hash ) = $this->saltedPassword( $password );

		$this->mPassword = $hash;
		$this->mSalt = $salt;

		if ( $this->getId() ) {
			$dbw = self::getCentralDB();
			$dbw->update( 'globaluser',
				array(
					'gu_salt'     => $salt,
					'gu_password' => $hash,
				),
				array(
					'gu_id' => $this->getId(),
				),
				__METHOD__ );

			wfDebugLog( 'CentralAuth',
				"Set global password for '$this->mName'" );
		} else {
			wfDebugLog( 'CentralAuth',
				__METHOD__ . " was called for a global user that doesn't exist ('$this->mName')." );
		}


		if ( $resetAuthToken ) {
			$this->resetAuthToken();
		}
		$this->invalidateCache();
		return true;
	}

	/**
	 * Get the password hash.
	 * Automatically converts to a new-style hash
	 */
	function getPassword() {
		$this->loadState();
		if ( substr( $this->mPassword, 0, 1 ) != ':' ) {
			$this->mPassword = ':B:' . $this->mSalt . ':' . $this->mPassword;
		}
		return $this->mPassword;
	}

	static function setP3P() {
		static $p3pSet = false;

		if ( !$p3pSet ) {
			// IE requires that a P3P header be provided for the cookies to be
			// visible to the auto-login check.
			global $wgCentralAuthCookiesP3P;
			if ( $wgCentralAuthCookiesP3P === true ) {
				// Note this policy is not valid: it has no valid tokens, while
				// a valid policy would contain an "access" token and at least
				// one statement, which would contain either the NID token or
				// at least one "purpose" token, one "recipient" token, and one
				// "retention" token.
				$url = Title::makeTitle( NS_SPECIAL, 'CentralAutoLogin/P3P' )->getCanonicalURL();
				header( "P3P: CP=\"This is not a P3P policy! See $url for more info.\"", true );
			} elseif ( $wgCentralAuthCookiesP3P ) {
				header( "P3P: $wgCentralAuthCookiesP3P", true );
			}
			$p3pSet = true;
		}
	}

	/**
	 * @static
	 * @param $name
	 * @param $value
	 * @param $exp
	 * @param bool $secure
	 *  true: Force setting the secure attribute when setting the cookie
	 *  false: Force NOT setting the secure attribute when setting the cookie
	 *  null (default): Use the default ($wgCookieSecure) to set the secure attribute
	 * @param string|bool $prefix cookie prefix; false to use $wgCentralAuthCookiePrefix
	 * @throws Exception
	 * @return void
	 */
	static function setCookie( $name, $value, $exp = -1, $secure = null, $prefix = false ) {
		global $wgCentralAuthCookiePrefix, $wgCentralAuthCookieDomain,
			$wgCookieExpiration, $wgCentralAuthCookiePath, $wgExtendedLoginCookieExpiration;

		if ( CentralAuthHooks::hasApiToken() ) {
			throw new Exception( "Cannot set cookies when API 'centralauthtoken' parameter is given" );
		}

		self::setP3P();

		if ( $exp == -1 ) {
			$exp = time();

			if ( $wgExtendedLoginCookieExpiration !== null ) {
				$exp += $wgExtendedLoginCookieExpiration;
			} else {
				$exp += $wgCookieExpiration;
			}
		} elseif ( $exp == 0 ) {
			// Session cookie
			$exp = null;
		} elseif ( $exp < 3.16e7 ) {
			// Relative expiry
			$exp += time();
		}

		if ( $prefix === false ) {
			 $prefix = $wgCentralAuthCookiePrefix;
		}

		RequestContext::getMain()->getRequest()->response()->setcookie(
			$name, $value, $exp, array(
				'prefix' => $prefix,
				'path' => $wgCentralAuthCookiePath,
				'domain' => $wgCentralAuthCookieDomain,
				'secure' => $secure,
			)
		);
	}

	/**
	 * @param  $name
	 * @return void
	 */
	protected static function clearCookie( $name ) {
		self::setCookie( $name, '', - 86400 );
	}

	/**
	 * Set a global cookie that auto-authenticates the user on other wikis.
	 * This also destroys and "pending_name"/"pending_guid" keys in the session,
	 * which exist when a partially authenticated stub session is created.
	 *
	 * Called on login.
	 *
	 * $refreshId can have three values:
	 *   - True   : refresh the SessionID when setting the cookie to a new random ID.
	 *   - String : refresh the SessionID when setting the cookie to the given ID.
	 *   - False  : use the SessionID of the client cookie (make a new one if there is none).
	 *
	 * @param $remember Bool|User
	 * @param $refreshId Bool|string
	 * @param bool $secure
	 *  true: Force setting the secure attribute when setting the cookie
	 *  false: Force NOT setting the secure attribute when setting the cookie
	 *  null (default): Use the default ($wgCookieSecure) to set the secure attribute
	 * @param array $sessionData Extra key-value pairs to include in the session
	 * @return string Session ID
	 */
	function setGlobalCookies(
		$remember = false, $refreshId = false, $secure = null, $sessionData = array()
	) {
		global $wgCookieSecure;

		if ( $remember instanceof User ) {
			// Older code passed a user object here. Be kind and do what they meant to do.
			$remember = $remember->getOption( 'rememberpassword' );
		}

		$session = array();
		$session['user'] = $this->mName;
		self::setCookie( 'User', $this->mName, -1, $secure );
		$session['token'] = $this->getAuthToken();
		$session['expiry'] = time() + 86400;
		$session['auto-create-blacklist'] = array();
		$session += $sessionData;

		if ( $remember ) {
			self::setCookie( 'Token', $this->getAuthToken(), -1, $secure );
		} else {
			self::clearCookie( 'Token' );
		}

		$id = self::setSession( $session, $refreshId, $secure );

		if ( $secure || ( $secure === null && $wgCookieSecure ) ) {
			$forceTime = ( $remember ? -1 : 0 );

			// Core set a forceHTTPS cookie with a different domain. Delete
			// that one, and set our own.
			RequestContext::getMain()->getRequest()->response()->setcookie(
				'forceHTTPS', '', -86400, array(
					'prefix' => '',
					'secure' => false,
				)
			);
			self::setCookie( 'forceHTTPS', '1', $forceTime, false, '' );
		} else {
			// Bug 54626: Explcitly clear forceHTTPS cookie when it's not wanted
			self::setCookie( 'forceHTTPS', '', -86400, false, '' );
		}

		return $id;
	}

	/**
	 * Delete global cookies which auto-authenticate the user on other wikis.
	 * Called on logout.
	 */
	static function deleteGlobalCookies() {
		self::clearCookie( 'User' );
		self::clearCookie( 'Token' );
		self::clearCookie( 'Session' );
		self::setCookie( 'forceHTTPS', '', -86400, false, '' );

		// Logged-out cookie -to fix caching.
		self::setCookie( 'LoggedOut', time() );

		self::deleteSession();
	}

	/**
	 * Get the domain parameter for setting a global cookie.
	 * This allows other extensions to easily set global cookies without directly relying on
	 * $wgCentralAuthCookieDomain (in case CentralAuth's implementation changes at some point).
	 *
	 * @return String
	 */
	static function getCookieDomain() {
		global $wgCentralAuthCookieDomain;
		return $wgCentralAuthCookieDomain;
	}

	/**
	 * Check a global auth token against the one we know of in the database.
	 *
	 * @param $token String
	 * @return Bool
	 */
	function validateAuthToken( $token ) {
		return ( $token == $this->getAuthToken() );
	}

	/**
	 * Generate a new random auth token, and store it in the database.
	 * Should be called as often as possible, to the extent that it will
	 * not randomly log users out (so on logout, as is done currently, is a good time).
	 */
	function resetAuthToken() {
		// Load state, since its hard to reset the token without it
		$this->loadState();

		// Generate a random token.
		$this->mAuthToken = MWCryptRand::generateHex( 32 );
		$this->mStateDirty = true;

		// Save it.
		$this->saveSettings();
	}

	function saveSettings() {
		if ( !$this->mStateDirty ) {
			return;
		}
		$this->mStateDirty = false;

		if ( wfReadOnly() ) {
			return;
		}

		$this->loadState();
		if ( !$this->mGlobalId ) {
			return;
		}

		$newCasToken = $this->mCasToken + 1;

		$dbw = self::getCentralDB();
		$dbw->update( 'globaluser',
			array( # SET
				'gu_password' => $this->mPassword,
				'gu_salt' => $this->mSalt,
				'gu_auth_token' => $this->mAuthToken,
				'gu_locked' => $this->mLocked,
				'gu_hidden' => $this->getHiddenLevel(),
				'gu_email' => $this->mEmail,
				'gu_email_authenticated' => $dbw->timestampOrNull( $this->mAuthenticationTimestamp ),
				'gu_home_db' => $this->getHomeWiki(),
				'gu_cas_token' => $newCasToken
			),
			array( # WHERE
				'gu_id' => $this->mGlobalId,
				'gu_cas_token' => $this->mCasToken
			),
			__METHOD__
		);

		if ( !$dbw->affectedRows() ) {
			// Maybe the problem was a missed cache update; clear it to be safe
			$this->invalidateCache();
			// User was changed in the meantime or loaded with stale data
			MWExceptionHandler::logException( new MWException(
				"CAS update failed on gu_cas_token for user ID '{$this->mGlobalId}';" .
				"the version of the user to be saved is older than the current version."
			) );
			return;
		}

		$this->mCasToken = $newCasToken;
		$this->invalidateCache();
	}

	/**
	 * @return
	 */
	function getGlobalGroups() {
		$this->loadGroups();

		return $this->mGroups;
	}

	/**
	 * @return array
	 */
	function getGlobalRights() {
		$this->loadGroups();

		$rights = array();
		$sets = array();
		foreach ( $this->mRights as $right ) {
			if ( $right['set'] ) {
				$setId = $right['set'];
				if ( isset( $sets[$setId] ) ) {
					$set = $sets[$setId];
				} else {
					$set = WikiSet::newFromID( $setId );
					$sets[$setId] = $set;
				}
				if ( $set->inSet() ) {
					$rights[] = $right['right'];
				}
			} else {
				$rights[] = $right['right'];
			}
		}
		return $rights;
	}

	/**
	 * @param  $groups
	 * @return void
	 */
	function removeFromGlobalGroups( $groups ) {
		$dbw = self::getCentralDB();

		# Delete from the DB
		$dbw->delete( 'global_user_groups',
			array( 'gug_user' => $this->getId(), 'gug_group' => $groups ),
			__METHOD__ );

		$this->invalidateCache();
	}

	/**
	 * @param  $groups
	 * @return void
	 */
	function addToGlobalGroups( $groups ) {
		$dbw = self::getCentralDB();

		if ( !is_array( $groups ) ) {
			$groups = array( $groups );
		}

		$insert_rows = array();
		foreach ( $groups as $group ) {
			$insert_rows[] = array( 'gug_user' => $this->getId(), 'gug_group' => $group );
		}

		# Replace into the DB
		$dbw->replace( 'global_user_groups',
			array( 'gug_user', 'gug_group' ),
			$insert_rows, __METHOD__ );

		$this->invalidateCache();
	}

	/**
	 * @static
	 * @return array
	 */
	static function availableGlobalGroups() {
		$dbr = self::getCentralSlaveDB();

		$res = $dbr->select( 'global_group_permissions', 'distinct ggp_group', array(), __METHOD__ );

		$groups = array();

		foreach ( $res as $row ) {
			/** @var $row object */
			$groups[] = $row->ggp_group;
		}

		return $groups;
	}

	/**
	 * @static
	 * @param  $group
	 * @return array
	 */
	static function globalGroupPermissions( $group ) {
		$dbr = self::getCentralSlaveDB();

		$res = $dbr->select( array( 'global_group_permissions' ),
			array( 'ggp_permission' ), array( 'ggp_group' => $group ), __METHOD__ );

		$rights = array();

		foreach ( $res as $row ) {
			/** @var $row object */
			$rights[] = $row->ggp_permission;
		}

		return $rights;
	}

	/**
	 * @param  $perm
	 * @return bool
	 */
	function hasGlobalPermission( $perm ) {
		$perms = $this->getGlobalRights();

		return in_array( $perm, $perms );
	}

	/**
	 * @return array
	 */
	static function getUsedRights() {
		$dbr = self::getCentralSlaveDB();

		$res = $dbr->select( 'global_group_permissions', 'distinct ggp_permission',
			array(), __METHOD__ );

		$rights = array();

		foreach ( $res as $row ) {
			/** @var $row object */
			$rights[] = $row->ggp_permission;
		}

		return $rights;
	}

	public function invalidateCache() {
		if ( !$this->mDelayInvalidation ) {
			wfDebugLog( 'CentralAuthVerbose', "Updating cache for global user {$this->mName}" );
			// Purge the cache
			$this->quickInvalidateCache();
			// Reload the state
			$this->loadStateNoCache();
		} else {
			wfDebugLog( 'CentralAuthVerbose', "Deferring cache invalidation because we're in a transaction" );
		}
	}

	/**
	 * For when speed is of the essence (e.g. when batch-purging users after rights changes)
	 */
	public function quickInvalidateCache() {
		wfDebugLog( 'CentralAuthVerbose', "Quick cache invalidation for global user {$this->mName}" );

		ObjectCache::getMainWANInstance()->delete( $this->getCacheKey() );
	}

	/**
	 * End a "transaction".
	 * A transaction delays cache invalidation until after
	 * some operation which would otherwise repeatedly do so.
	 * Intended to be used for things like migration.
	 */
	public function endTransaction() {
		wfDebugLog( 'CentralAuthVerbose', "Finishing CentralAuthUser cache-invalidating transaction" );
		$this->mDelayInvalidation = false;
		$this->invalidateCache();
	}

	/**
	 * Start a "transaction".
	 * A transaction delays cache invalidation until after
	 * some operation which would otherwise repeatedly do so.
	 * Intended to be used for things like migration.
	 */
	public function startTransaction() {
		wfDebugLog( 'CentralAuthVerbose', "Beginning CentralAuthUser cache-invalidating transaction" );
		// Delay cache invalidation
		$this->mDelayInvalidation = 1;
	}

	/**
	 * @static
	 * @return string
	 */
	static function memcKey( /*...*/ ) {
		global $wgCentralAuthDatabase;
		$args = func_get_args();
		return $wgCentralAuthDatabase . ':' . implode( ':', $args );
	}

	/**
	 * Get the central session data
	 *
	 * @return Array
	 */
	static function getSession() {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return array();
		}
		if ( !isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] ) ) {
			return array();
		}

		$id =  $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'];
		$key = self::memcKey( 'session', $id );

		$stime = microtime( true );
		$data = self::getSessionCache()->get( $key );
		$real = microtime( true ) - $stime;

		RequestContext::getMain()->getStats()->timing( "centralauth.session.read", $real );

		if ( $data === false || $data === null ) {
			return array();
		} else {
			return $data;
		}
	}

	/**
	 * Set the central session data
	 *
	 * $refreshId can have three values:
	 *   - True   : refresh the SessionID when setting the cookie to a new random ID.
	 *   - String : refresh the SessionID when setting the cookie to the given ID.
	 *   - False  : use the SessionID of the client cookie (make a new one if there is none).
	 *
	 * @param $data Array
	 * @param $refreshId Bool|String
	 * @param bool $secure
	 *  true: Force setting the secure attribute when setting the cookie
	 *  false: Force NOT setting the secure attribute when setting the cookie
	 *  null (default): Use the default ($wgCookieSecure) to set the secure attribute
	 * @return string Session ID
	 */
	static function setSession( $data, $refreshId = false, $secure = null ) {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return null;
		}

		if ( $refreshId || !isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] ) ) {
			$id = is_string( $refreshId ) ? $refreshId : MWCryptRand::generateHex( 32 );
			self::setCookie( 'Session', $id, 0, $secure );
		} else {
			$id =  $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'];
		}

		$data['sessionId'] = $id;
		$key = self::memcKey( 'session', $id );

		$stime = microtime( true );
		self::getSessionCache()->set( $key, $data, 86400 );
		$real = microtime( true ) - $stime;

		RequestContext::getMain()->getStats()->timing( "centralauth.session.write", $real );

		return $id;
	}

	/**
	 * Delete the central session data
	 */
	static function deleteSession() {
		global $wgCentralAuthCookies, $wgCentralAuthCookiePrefix;

		if ( !$wgCentralAuthCookies ) {
			return;
		}
		if ( !isset( $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'] ) ) {
			return;
		}

		$id =  $_COOKIE[$wgCentralAuthCookiePrefix . 'Session'];
		wfDebug( __METHOD__ . ": Deleting session $id\n" );
		$key = self::memcKey( 'session', $id );

		$stime = microtime( true );
		self::getSessionCache()->delete( $key );
		$real = microtime( true ) - $stime;

		RequestContext::getMain()->getStats()->timing( "centralauth.session.delete", $real );
	}

	/**
	 * @return BagOStuff
	 */
	public static function getSessionCache() {
		global $wgSessionsInObjectCache, $wgSessionCacheType;

		return $wgSessionsInObjectCache
			? ObjectCache::getInstance( $wgSessionCacheType )
			: ObjectCache::getMainStashInstance();
	}

	/**
	 * Check if the user is attached on a given wiki id.
	 *
	 * @param $wiki String
	 * @return Bool
	 */
	public function attachedOn( $wiki ) {
		return $this->exists() && in_array( $wiki, $this->mAttachedArray );
	}

	/**
	 * Get a hash representing the user/locked/hidden state of this user,
	 * used to check for edit conflicts
	 *
	 * @param $recache - force a reload of the user from the database
	 * @return String
	 */
	public function getStateHash( $recache = false ) {
		$this->loadState( $recache );
		return md5( $this->mGlobalId . ':' . $this->mName . ':' . $this->mHidden . ':' . (int) $this->mLocked );
	}

	/**
	 * Log an action for the current user
	 *
	 * @param $action
	 * @param $reason string
	 * @param $params array
	 * @param $suppressLog bool
	 */
	function logAction( $action, $reason = '', $params = array(), $suppressLog = false ) {
		// Not centralauth because of some weird length limitiations
		$logType = $suppressLog ? 'suppress' : 'globalauth';
		$log = new LogPage( $logType );
		$log->addEntry(
			$action,
			Title::newFromText(
				MWNamespace::getCanonicalName( NS_USER ) . ":{$this->mName}@global"
			),
			$reason,
			$params
		);
	}

	/**
	 * @param string $wiki
	 * @param integer $id
	 */
	private function clearLocalUserCache( $wiki, $id ) {
		// @TODO: this has poor separation of concerns :/
		ObjectCache::getMainWANInstance()->delete( "$wiki:user:id:$id" );
	}
}
