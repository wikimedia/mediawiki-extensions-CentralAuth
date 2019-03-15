<?php
/*

likely construction types...

- give me the global account for this local user id
- none? give me the global account for this name

- create me a global account for this name

*/

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;

class CentralAuthUser implements IDBAccessObject {
	/** @var MapCacheLRU Cache of loaded CentralAuthUsers */
	private static $loadedUsers = null;

	/**
	 * The username of the current user.
	 * @var string
	 */
	private $mName;
	/** @var bool */
	public $mStateDirty = false;
	/** @var int */
	private $mDelayInvalidation = 0;

	/** @var string[]|null */
	private $mAttachedArray;
	/** @var string */
	private $mEmail;
	/** @var bool */
	private $mEmailAuthenticated;
	/** @var string|null */
	private $mHomeWiki;
	/** @var bool */
	private $mHidden;
	/** @var bool */
	private $mLocked;
	/** @var string[]|null */
	private $mAttachedList;
	/** @var string */
	private $mAuthenticationTimestamp;
	/** @var string[]|null */
	private $mGroups;
	/** @var string[] */
	private $mRights;
	/** @var string */
	private $mPassword;
	/** @var string */
	private $mAuthToken;
	/** @var string */
	private $mSalt;
	/** @var int|null */
	private $mGlobalId;
	/** @var bool */
	private $mFromMaster;
	/** @var bool */
	private $mIsAttached;
	/** @var string */
	private $mRegistration;
	/** @var int */
	private $mGlobalEditCount;
	/** @var string */
	private $mBeingRenamed;
	/** @var string[] */
	private $mBeingRenamedArray;
	/** @var array|null */
	protected $mAttachedInfo;
	/** @var int */
	protected $mCasToken = 0;

	/** @var string[] */
	private static $mCacheVars = [
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

		'mCasToken'
	];

	const VERSION = 8;

	const HIDDEN_NONE = '';
	const HIDDEN_LISTS = 'lists';
	const HIDDEN_OVERSIGHT = 'suppressed';

	// The maximum number of edits a user can have and still be hidden
	const HIDE_CONTRIBLIMIT = 1000;

	/**
	 * @note Don't call this directly. Use self::getInstanceByName() or
	 *  self::getMasterInstanceByName() instead.
	 * @param string $username
	 * @param int $flags Supports CentralAuthUser::READ_LATEST to use the master DB
	 */
	public function __construct( $username, $flags = 0 ) {
		$this->mName = $username;
		$this->resetState();
		if ( ( $flags & self::READ_LATEST ) == self::READ_LATEST ) {
			$this->mFromMaster = true;
		}
	}

	/**
	 * Fetch the cache
	 * @return MapCacheLRU
	 */
	private static function getUserCache() {
		if ( self::$loadedUsers === null ) {
			// Limit of 20 is arbitrary
			self::$loadedUsers = new MapCacheLRU( 20 );
		}
		return self::$loadedUsers;
	}

	/**
	 * Explicitly set the (cached) CentralAuthUser object corresponding to the supplied User.
	 * @param User $user
	 * @param CentralAuthUser $caUser
	 */
	public static function setInstance( User $user, CentralAuthUser $caUser ) {
		self::setInstanceByName( $user->getName(), $caUser );
	}

	/**
	 * Explicitly set the (cached) CentralAuthUser object corresponding to the supplied User.
	 * @param string $username Must be validated and canonicalized by the caller
	 * @param CentralAuthUser $caUser
	 */
	public static function setInstanceByName( $username, CentralAuthUser $caUser ) {
		self::getUserCache()->set( $username, $caUser );
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 * @param User $user
	 * @return CentralAuthUser
	 */
	public static function getInstance( User $user ) {
		return self::getInstanceByName( $user->getName() );
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied user.
	 * @param string $username Must be validated and canonicalized by the caller
	 * @return CentralAuthUser
	 */
	public static function getInstanceByName( $username ) {
		$cache = self::getUserCache();
		$ret = $cache->get( $username );
		if ( !$ret ) {
			$ret = new self( $username );
			$cache->set( $username, $ret );
		}
		return $ret;
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 * This object will use DB_MASTER.
	 * @param User $user
	 * @return CentralAuthUser
	 * @since 1.27
	 */
	public static function getMasterInstance( User $user ) {
		return self::getMasterInstanceByName( $user->getName() );
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 * This object will use DB_MASTER.
	 * @param string $username Must be validated and canonicalized by the caller
	 * @return CentralAuthUser
	 * @since 1.27
	 */
	public static function getMasterInstanceByName( $username ) {
		$cache = self::getUserCache();
		$ret = $cache->get( $username );
		if ( !$ret || !$ret->mFromMaster ) {
			$ret = new self( $username, self::READ_LATEST );
			$cache->set( $username, $ret );
		}
		return $ret;
	}

	/**
	 * @deprecated use CentralAuthUtils instead
	 */
	public static function getCentralDB() {
		return CentralAuthUtils::getCentralDB();
	}

	/**
	 * @deprecated use CentralAuthUtils instead
	 */
	public static function getCentralSlaveDB() {
		return CentralAuthUtils::getCentralReplicaDB();
	}

	/**
	 * Check hasOrMadeRecentMasterChanges() on the CentralAuth load balancer
	 *
	 * @return bool
	 */
	public static function centralLBHasRecentMasterChanges() {
		global $wgCentralAuthDatabase;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		return $lbFactory->getMainLB( $wgCentralAuthDatabase )->hasOrMadeRecentMasterChanges();
	}

	/**
	 * @deprecated use CentralAuthUtils instead
	 */
	public static function waitForSlaves() {
		CentralAuthUtils::waitForSlaves();
	}

	/**
	 * @param string $wikiID
	 * @return IDatabase
	 */
	public static function getLocalDB( $wikiID ) {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		return $lbFactory->getMainLB( $wikiID )->getConnectionRef( DB_MASTER, [], $wikiID );
	}

	/**
	 * Test if this is a write-mode instance, and log if not.
	 */
	private function checkWriteMode() {
		if ( !$this->mFromMaster ) {
			wfDebugLog( 'CentralAuth',
				"Setter called on a slave instance: " . wfGetAllCallers( 10 ) );
		}
	}

	/**
	 * @return IDatabase Master or slave based on shouldUseMasterDB()
	 * @throws CentralAuthReadOnlyError
	 */
	protected function getSafeReadDB() {
		return $this->shouldUseMasterDB()
			? CentralAuthUtils::getCentralDB()
			: CentralAuthUtils::getCentralReplicaDB();
	}

	/**
	 * Get (and init if needed) the value of mFromMaster
	 *
	 * @return bool
	 */
	protected function shouldUseMasterDB() {
		global $wgCentralAuthUseSlaves;

		if ( !$wgCentralAuthUseSlaves ) {
			return true;
		}

		if ( $this->mFromMaster === null ) {
			$this->mFromMaster = self::centralLBHasRecentMasterChanges();
		}

		return $this->mFromMaster;
	}

	/**
	 * Return query data needed to properly use self::newFromRow
	 * @return array (
	 *   'tables' => array,
	 *   'fields' => array,
	 *   'where' => array,
	 *   'options' => array,
	 *   'joinConds' => array,
	 *  )
	 */
	public static function selectQueryInfo() {
		return [
			'tables' => [ 'globaluser', 'localuser' ],
			'fields' => [
				'gu_id', 'gu_name', 'lu_wiki', 'gu_salt', 'gu_password', 'gu_auth_token',
				'gu_locked', 'gu_hidden', 'gu_registration', 'gu_email',
				'gu_email_authenticated', 'gu_home_db', 'gu_cas_token'
			],
			'where' => [],
			'options' => [],
			'joinConds' => [
				'localuser' => [ 'LEFT OUTER JOIN', [ 'gu_name=lu_name', 'lu_wiki' => wfWikiID() ] ]
			],
		];
	}

	/**
	 * Get a CentralAuthUser object from a user's id
	 *
	 * @param int $id
	 * @return CentralAuthUser|bool false if no user exists with that id
	 */
	public static function newFromId( $id ) {
		$name = CentralAuthUtils::getCentralReplicaDB()->selectField(
			'globaluser',
			'gu_name',
			[ 'gu_id' => $id ],
			__METHOD__
		);

		if ( $name !== false ) {
			return self::getInstanceByName( $name );
		} else {
			return false;
		}
	}

	/**
	 * Get a master CentralAuthUser object from a user's id
	 *
	 * @param int $id
	 * @return CentralAuthUser|bool false if no user exists with that id
	 */
	public static function newMasterInstanceFromId( $id ) {
		$name = CentralAuthUtils::getCentralDB()->selectField(
			'globaluser',
			'gu_name',
			[ 'gu_id' => $id ],
			__METHOD__
		);

		if ( $name !== false ) {
			return self::getMasterInstanceByName( $name );
		} else {
			return false;
		}
	}

	/**
	 * Create a CentralAuthUser object from a joined globaluser/localuser row
	 *
	 * @param stdClass $row
	 * @param array $renameUser Empty if no rename is going on, else (oldname, newname)
	 * @param bool $fromMaster
	 * @return CentralAuthUser
	 */
	public static function newFromRow( $row, $renameUser, $fromMaster = false ) {
		$caUser = new self( $row->gu_name );
		$caUser->loadFromRow( $row, $renameUser, $fromMaster );
		return $caUser;
	}

	/**
	 * Create a CentralAuthUser object for a user who is known to be unattached.
	 * @param string $name The user name
	 * @param bool $fromMaster
	 * @return CentralAuthUser
	 */
	public static function newUnattached( $name, $fromMaster = false ) {
		$caUser = new self( $name );
		$caUser->loadFromRow( false, [], $fromMaster );
		return $caUser;
	}

	/**
	 * Clear state information cache
	 * Does not clear $this->mName, so the state information can be reloaded with loadState()
	 */
	protected function resetState() {
		$this->mGlobalId = null;
		$this->mGroups = null;
		$this->mAttachedArray = null;
		$this->mAttachedList = null;
		$this->mHomeWiki = null;
	}

	/**
	 * Load up state information, but don't use the cache
	 */
	public function loadStateNoCache() {
		$this->loadState( true );
	}

	/**
	 * Lazy-load up the most commonly required state information
	 * @param bool $recache Force a load from the database then save back to the cache
	 */
	protected function loadState( $recache = false ) {
		if ( $recache ) {
			$this->resetState();
		} elseif ( isset( $this->mGlobalId ) ) {
			// Already loaded
			return;
		}

		// Check the cache (unless the master was requested via READ_LATEST)
		if ( !$recache && $this->mFromMaster !== true ) {
			$this->loadFromCache();
		} else {
			$this->loadFromDatabase();
		}
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

		$db = $this->getSafeReadDB();

		$res = $db->select(
			[ 'global_group_permissions', 'global_user_groups' ],
			[ 'ggp_permission', 'ggp_group' ],
			[ 'ggp_group=gug_group', 'gug_user' => $this->getId() ],
			__METHOD__
		);

		$resSets = $db->select(
			[ 'global_user_groups', 'global_group_restrictions', 'wikiset' ],
			[ 'ggr_group', 'ws_id', 'ws_name', 'ws_type', 'ws_wikis' ],
			[ 'ggr_group=gug_group', 'ggr_set=ws_id', 'gug_user' => $this->getId() ],
			__METHOD__
		);

		$sets = [];
		foreach ( $resSets as $row ) {
			/* @var $row object */
			$sets[$row->ggr_group] = WikiSet::newFromRow( $row );
		}

		// Grab the user's rights/groups.
		$rights = [];
		$groups = [];

		foreach ( $res as $row ) {
			/** @var $set User|bool */
			$set = $sets[$row->ggp_group] ?? '';
			$rights[] = [ 'right' => $row->ggp_permission, 'set' => $set ? $set->getID() : false ];
			$groups[$row->ggp_group] = 1;
		}

		$this->mRights = $rights;
		$this->mGroups = array_keys( $groups );
	}

	protected function loadFromDatabase() {
		wfDebugLog( 'CentralAuthVerbose', "Loading state for global user {$this->mName} from DB" );

		$fromMaster = $this->shouldUseMasterDB();
		$db = $this->getSafeReadDB(); // matches $fromMaster above

		$queryInfo = self::selectQueryInfo();

		$row = $db->selectRow(
			$queryInfo['tables'],
			$queryInfo['fields'],
			[ 'gu_name' => $this->mName ] + $queryInfo['where'],
			__METHOD__,
			$queryInfo['options'],
			$queryInfo['joinConds']
		);

		$renameUserStatus = new GlobalRenameUserStatus( $this->mName );
		$renameUser = $renameUserStatus->getNames( null, $fromMaster ? 'master' : 'slave' );

		$this->loadFromRow( $row, $renameUser, $fromMaster );
	}

	/**
	 * Load user state from a joined globaluser/localuser row
	 *
	 * @param stdClass|bool $row
	 * @param array $renameUser Empty if no rename is going on, else (oldname, newname)
	 * @param bool $fromMaster
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
			$this->mHomeWiki = $row->gu_home_db;
			$this->mCasToken = $row->gu_cas_token;
		} else {
			$this->mGlobalId = 0;
			$this->mIsAttached = false;
			$this->mLocked = false;
			$this->mHidden = '';
			$this->mCasToken = 0;
		}

		$this->mFromMaster = $fromMaster;

		if ( $renameUser ) {
			$this->mBeingRenamedArray = $renameUser;
			$this->mBeingRenamed = implode( '|', $this->mBeingRenamedArray );
		} else {
			$this->mBeingRenamedArray = [];
			$this->mBeingRenamed = '';
		}
	}

	/**
	 * Load data from memcached
	 *
	 * @return bool
	 */
	protected function loadFromCache() {
		$cache = ObjectCache::getMainWANInstance();
		$data = $cache->getWithSetCallback(
			$this->getCacheKey( $cache ),
			$cache::TTL_DAY,
			function ( $oldValue, &$ttl, array &$setOpts ) {
				$dbr = CentralAuthUtils::getCentralReplicaDB();
				$setOpts += Database::getCacheSetOptions( $dbr );

				$this->loadFromDatabase();
				$this->loadAttached();
				$this->loadGroups();

				$data = [];
				foreach ( self::$mCacheVars as $var ) {
					$data[$var] = $this->$var;
				}

				return $data;
			},
			[ 'pcTTL' => $cache::TTL_PROC_LONG, 'version' => self::VERSION ]
		);

		$this->loadFromCacheObject( $data );

		return true;
	}

	/**
	 * Load user state from a cached array.
	 *
	 * @param array $object
	 */
	protected function loadFromCacheObject( array $object ) {
		wfDebugLog( 'CentralAuthVerbose',
			"Loading CentralAuthUser for user {$this->mName} from cache object" );
		foreach ( self::$mCacheVars as $var ) {
			$this->$var = $object[$var];
		}

		$this->loadAttached();

		$this->mIsAttached = $this->exists() && in_array( wfWikiID(), $this->mAttachedArray );
		$this->mFromMaster = false;
	}

	/**
	 * Return the global account ID number for this account, if it exists.
	 * @return int
	 */
	public function getId() {
		$this->loadState();
		return $this->mGlobalId;
	}

	/**
	 * Return the local user account ID of the user with the same name on given wiki,
	 * irrespective of whether it is attached or not
	 * @param string $wikiId ID for the local database to connect to
	 * @return int|null Local user ID for given $wikiID. Null if $wikiID is invalid or local user
	 *  doesn't exist
	 */
	public function getLocalId( $wikiId ) {
		// Make sure the wiki ID is valid. (This prevents DBConnectionError in unit tests)
		$validWikis = self::getWikiList();
		if ( !in_array( $wikiId, $validWikis ) ) {
			return null;
		}
		// Retrieve the local user ID from the specified database.
		$db = $this->getLocalDB( $wikiId );
		$id = $db->selectField( 'user', 'user_id', [ 'user_name' => $this->mName ], __METHOD__ );
		// If user doesn't exist, return null instead of false
		if ( $id === false ) {
			return null;
		}
		return $id;
	}

	/**
	 * Generate a valid memcached key for caching the object's data.
	 * @param WANObjectCache $cache
	 * @return string
	 */
	protected function getCacheKey( WANObjectCache $cache ) {
		return $cache->makeGlobalKey( 'centralauth-user', md5( $this->mName ) );
	}

	/**
	 * Return the global account's name, whether it exists or not.
	 * @return string
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
	public function getPasswordObject() {
		$this->loadState();
		return $this->getPasswordFromString( $this->mPassword, $this->mSalt );
	}

	/**
	 * Return the global-login token for this account.
	 * @return string
	 */
	public function getAuthToken() {
		global $wgAuthenticationTokenVersion;

		$this->loadState();

		if ( !isset( $this->mAuthToken ) || !$this->mAuthToken ) {
			$this->resetAuthToken();
		}

		if ( $wgAuthenticationTokenVersion === null ) {
			return $this->mAuthToken;
		} else {
			$ret = MWCryptHash::hmac( $wgAuthenticationTokenVersion, $this->mAuthToken, false );

			// The raw hash can be overly long. Shorten it up.
			if ( strlen( $ret ) < 32 ) {
				// Should never happen, even md5 is 128 bits
				throw new \UnexpectedValueException( 'Hmac returned less than 128 bits' );
			}
			return substr( $ret, -32 );
		}
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
	 * @return string
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

		$attached = $this->queryAttachedBasic();

		if ( !count( $attached ) ) {
			return null;
		}

		foreach ( $attached as $wiki => $acc ) {
			if ( $acc['attachedMethod'] == 'primary' || $acc['attachedMethod'] == 'new' ) {
				$this->mHomeWiki = $wiki;
				break;
			}
		}

		if ( $this->mHomeWiki === null || $this->mHomeWiki === '' ) {
			// Still null... try harder.
			$attached = $this->queryAttached();
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
	 * @return int total number of edits for all wikis
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
	 * @param string $password
	 * @param string $email
	 * @return bool
	 */
	public function register( $password, $email ) {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();
		list( $salt, $hash ) = $this->saltedPassword( $password );
		if ( !$this->mAuthToken ) {
			$this->mAuthToken = MWCryptRand::generateHex( 32 );
		}
		$dbw->insert(
			'globaluser',
			[
				'gu_name'  => $this->mName,

				'gu_email' => $email,
				'gu_email_authenticated' => null,

				'gu_salt'     => $salt,
				'gu_password' => $hash,

				'gu_auth_token' => $this->mAuthToken,

				'gu_locked' => 0,
				'gu_hidden' => '',

				'gu_registration' => $dbw->timestamp(),
			],
			__METHOD__,
			[ 'IGNORE' ]
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
	public static function storeMigrationData( $wiki, $users ) {
		if ( $users ) {
			$dbw = CentralAuthUtils::getCentralDB();
			$globalTuples = [];
			$tuples = [];
			foreach ( $users as $name ) {
				$globalTuples[] = [ 'gn_name' => $name ];
				$tuples[] = [
					'ln_wiki' => $wiki,
					'ln_name' => $name
				];
			}
			$dbw->insert(
				'globalnames',
				$globalTuples,
				__METHOD__,
				[ 'IGNORE' ] );
			$dbw->insert(
				'localnames',
				$tuples,
				__METHOD__,
				[ 'IGNORE' ] );
		}
	}

	/**
	 * Store global user data in the auth server's main table.
	 *
	 * @param string $salt
	 * @param string $hash
	 * @param string $email
	 * @param string $emailAuth timestamp
	 * @return bool Whether we were successful or not.
	 */
	protected function storeGlobalData( $salt, $hash, $email, $emailAuth ) {
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->insert( 'globaluser',
			[
				'gu_name' => $this->mName,
				'gu_salt' => $salt,
				'gu_password' => $hash,
				'gu_auth_token' => MWCryptRand::generateHex( 32 ), // So it doesn't have to be done later
				'gu_email' => $email,
				'gu_email_authenticated' => $dbw->timestampOrNull( $emailAuth ),
				'gu_registration' => $dbw->timestamp(), // hmmmm
				'gu_locked' => 0,
				'gu_hidden' => '',
			],
			__METHOD__,
			[ 'IGNORE' ] );

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
	public function storeAndMigrate(
		$passwords = [], $sendToRC = true, $safe = false, $checkHome = false
	) {
		$ret = $this->attemptAutoMigration( $passwords, $sendToRC, $safe, $checkHome );
		if ( $ret === true ) {
			$this->recordAntiSpoof();
		}

		return $ret;
	}

	/**
	 * Record the current username in the central AntiSpoof system
	 * if that feature is enabled
	 */
	protected function recordAntiSpoof() {
		if ( class_exists( CentralAuthSpoofUser::class ) ) {
			$spoof = new CentralAuthSpoofUser( $this->mName );
			$spoof->record();
		}
	}

	/**
	 * Remove the current username from the central AntiSpoof system
	 * if that feature is enabled
	 */
	public function removeAntiSpoof() {
		if ( class_exists( CentralAuthSpoofUser::class ) ) {
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
	public function chooseHomeWiki( $migrationSet ) {
		if ( empty( $migrationSet ) ) {
			throw new Exception( 'Logic error -- empty migration set in chooseHomeWiki' );
		}

		// Sysops get priority
		$found = [];
		$priorityGroups = [ 'checkuser', 'oversight', 'bureaucrat', 'sysop' ];
		foreach ( $priorityGroups as $group ) {
			foreach ( $migrationSet as $wiki => $local ) {
				if ( isset( $local['groupMemberships'][$group] ) ) {
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
				if ( $migrationSet[$wiki]['registration'] <
					$migrationSet[$homeWiki]['registration']
				) {
					$homeWiki = $wiki;
				} elseif ( $migrationSet[$wiki]['registration'] ===
					$migrationSet[$homeWiki]['registration']
				) {
					// Another tie? Screw it, pick one randomly.
					$wikis = [ $wiki, $homeWiki ];
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
	 * @param array $migrationSet
	 * @param array $passwords Optional, pre-authenticated passwords.
	 *     Should match an account which is known to be attached.
	 * @return array Array of <wiki> => <authentication method>
	 */
	public function prepareMigration( $migrationSet, $passwords = [] ) {
		// If the primary account has an email address set,
		// we can use it to match other accounts. If it doesn't,
		// we can't be sure that the other accounts with no mail
		// are the same person, so err on the side of caution.
		// For additional safety, we'll only let the mail check
		// propagate from a confirmed account
		$passingMail = [];
		if ( $this->mEmail != '' && $this->mEmailAuthenticated ) {
			$passingMail[$this->mEmail] = true;
		}

		$passwordConfirmed = [];
		// If we've got an authenticated password to work with, we can
		// also assume their email addresses are useful for this purpose...
		if ( $passwords ) {
			foreach ( $migrationSet as $wiki => $local ) {
				if ( $local['email'] && $local['emailAuthenticated'] &&
					!isset( $passingMail[$local['email']] )
				) {
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

		$attach = [];
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
					$this->matchHashes(
						$passwords,
						$this->getPasswordFromString( $local['password'], $local['id'] )
					)
			) {
				// Matches the pre-authenticated password, yay!
				$method = 'password';
			} else {
				// Can't automatically resolve this account.
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
	 * @param array $passwords
	 * @param string &$home set to false if no permission to do checks
	 * @param array &$attached on success, list of wikis which will be auto-attached
	 * @param array &$unattached on success, list of wikis which won't be auto-attached
	 * @param array &$methods on success, associative array of each wiki's attachment method	 *
	 * @return Status object
	 */
	public function migrationDryRun( $passwords, &$home, &$attached, &$unattached, &$methods ) {
		$this->checkWriteMode(); // Because it messes with $this->mEmail and so on

		$home = false;
		$attached = [];
		$unattached = [];

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
		$this->checkWriteMode();

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
	 * @param array $passwords
	 * @param bool $sendToRC
	 * @param bool $safe Only migrate if all accounts can be merged
	 * @param bool $checkHome Re-check the user's ownership of the home wiki
	 * @return bool Whether full automatic migration completed successfully.
	 */
	protected function attemptAutoMigration(
		$passwords = [], $sendToRC = true, $safe = false, $checkHome = false
	) {
		$this->checkWriteMode();
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
	 * @param string $password plaintext password to try matching
	 * @param string[]|null &$migrated Array of wiki IDs for records which were
	 *                  successfully migrated by this operation
	 * @param string[]|null &$remaining Array of wiki IDs for records which are still
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

		$migrated = [];
		$remaining = [];

		// Don't invalidate the cache 50 times
		$this->startTransaction();

		// Look for accounts we can match by password
		foreach ( $rows as $row ) {
			$wiki = $row['wiki'];
			if ( $this->matchHash( $password,
				$this->getPasswordFromString( $row['password'], $row['id'] ) )->isGood()
			) {
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
	 * @throws Exception
	 * @param string[] $list
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
	 * @return string[]
	 */
	public static function getWikiList() {
		global $wgLocalDatabases;
		static $wikiList;
		if ( is_null( $wikiList ) ) {
			Hooks::run( 'CentralAuthWikiList', [ &$wikiList ] );
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
		$this->checkWriteMode();

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

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$dbcw = CentralAuthUtils::getCentralDB();
		$password = $this->getPassword();

		foreach ( $valid as $wikiName ) {
			# Delete the user from the central localuser table
			$dbcw->delete( 'localuser',
				[
					'lu_name'   => $this->mName,
					'lu_wiki' => $wikiName ],
				__METHOD__ );
			if ( !$dbcw->affectedRows() ) {
				$wiki = WikiMap::getWiki( $wikiName );
				$status->error( 'centralauth-admin-already-unmerged', $wiki->getDisplayName() );
				$status->failCount++;
				continue;
			}

			# Touch the local user row, update the password
			$lb = $lbFactory->getMainLB( $wikiName );
			$dblw = $lb->getConnection( DB_MASTER, [], $wikiName );
			$dblw->update( 'user',
				[
					'user_touched' => wfTimestampNow(),
					'user_password' => $password
				], [ 'user_name' => $this->mName ], __METHOD__
			);

			$id = $dblw->selectField(
				'user',
				'user_id',
				[ 'user_name' => $this->mName ],
				__METHOD__
			);
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
	 * Queue a job to unattach this user from a named wiki.
	 *
	 * @param string $wikiId
	 */
	protected function queueAdminUnattachJob( $wikiId ) {
		$job = Job::factory(
			'CentralAuthUnattachUserJob',
			Title::makeTitleSafe( NS_USER, $this->getName() ),
			[
				'username' => $this->getName(),
				'wiki' => $wikiId,
			]
		);
		JobQueueGroup::singleton( $wikiId )->lazyPush( $job );
	}

	/**
	 * Delete a global account and log what happened
	 *
	 * @param string $reason Reason for the deletion
	 * @return Status
	 */
	public function adminDelete( $reason ) {
		$this->checkWriteMode();
		wfDebugLog( 'CentralAuth', "Deleting global account for user {$this->mName}" );
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$centralDB = CentralAuthUtils::getCentralDB();

		# Synchronise passwords
		$password = $this->getPassword();
		$localUserRes = $centralDB->select( 'localuser', '*',
			[ 'lu_name' => $this->mName ], __METHOD__ );
		$name = $this->getName();
		foreach ( $localUserRes as $localUserRow ) {
			/** @var $localUserRow object */
			$wiki = $localUserRow->lu_wiki;
			wfDebug( __METHOD__ . ": Fixing password on $wiki\n" );
			$lb = $lbFactory->getMainLB( $wiki );
			$localDB = $lb->getConnection( DB_MASTER, [], $wiki );
			$localDB->update( 'user',
				[ 'user_password' => $password ],
				[ 'user_name' => $name ],
				__METHOD__
			);

			$id = $localDB->selectField( 'user', 'user_id',
				[ 'user_name' => $this->mName ], __METHOD__ );
			$this->clearLocalUserCache( $wiki, $id );

			$lb->reuseConnection( $localDB );
		}
		$wasSuppressed = $this->isOversighted();

		$centralDB->startAtomic( __METHOD__ );
		# Delete and lock the globaluser row
		$centralDB->delete( 'globaluser', [ 'gu_name' => $this->mName ], __METHOD__ );
		if ( !$centralDB->affectedRows() ) {
			$centralDB->endAtomic( __METHOD__ );
			return Status::newFatal( 'centralauth-admin-delete-nonexistent', $this->mName );
		}
		# Delete all global user groups for the user
		$centralDB->delete( 'global_user_groups', [ 'gug_user' => $this->getId() ], __METHOD__ );
		# Delete the localuser rows
		$centralDB->delete( 'localuser', [ 'lu_name' => $this->mName ], __METHOD__ );
		$centralDB->endAtomic( __METHOD__ );

		if ( $wasSuppressed ) {
			// "suppress/delete" is taken by core, so use "cadelete"
			$this->logAction( 'cadelete', $reason, [], /* $suppressLog = */ true );
		} else {
			$this->logAction( 'delete', $reason, [], /* $suppressLog = */ false );
		}
		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Lock a global account
	 *
	 * @return Status
	 */
	public function adminLock() {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->update( 'globaluser', [ 'gu_locked' => 1 ],
			[ 'gu_name' => $this->mName ], __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-state-mismatch' );
		}

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Unlock a global account
	 *
	 * @return Status
	 */
	public function adminUnlock() {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->update( 'globaluser', [ 'gu_locked' => 0 ],
			[ 'gu_name' => $this->mName ], __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-state-mismatch' );
		}

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Change account hiding level.
	 *
	 * @param string $level CentralAuthUser::HIDDEN_ class constant
	 * @return Status
	 */
	public function adminSetHidden( $level ) {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->update( 'globaluser', [ 'gu_hidden' => $level ],
			[ 'gu_name' => $this->mName ], __METHOD__ );
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-admin-unhide-nonexistent', $this->mName );
		}

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Set locking and hiding settings for a Global User and log the changes made.
	 *
	 * @param bool|null $setLocked
	 *  true = lock
	 *  false = unlock
	 *  null = don't change
	 * @param string|null $setHidden
	 *  hidden level, one of the HIDDEN_ constants
	 *  null = don't change
	 * @param string $reason reason for hiding
	 * @param IContextSource $context
	 * @return Status
	 */
	public function adminLockHide( $setLocked, $setHidden, $reason, IContextSource $context ) {
		$isLocked = $this->isLocked();
		$oldHiddenLevel = $this->getHiddenLevel();
		$lockStatus = $hideStatus = null;
		$added = [];
		$removed = [];

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
				return Status::newFatal(
					$context->msg( 'centralauth-admin-too-many-edits', $this->mName )
						->numParams( self::HIDE_CONTRIBLIMIT )
				);
			}
		}

		$returnStatus = Status::newGood();

		$hiddenLevels = [
			self::HIDDEN_NONE,
			self::HIDDEN_LISTS,
			self::HIDDEN_OVERSIGHT
		];

		if ( !in_array( $setHidden, $hiddenLevels ) ) {
			$setHidden = self::HIDDEN_NONE;
		}

		if ( !$isLocked && $setLocked ) {
			$lockStatus = $this->adminLock();
			$added[] =
				$context->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		} elseif ( $isLocked && !$setLocked ) {
			$lockStatus = $this->adminUnlock();
			$removed[] =
				$context->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		}

		if ( $oldHiddenLevel != $setHidden ) {
			$hideStatus = $this->adminSetHidden( $setHidden );
			switch ( $setHidden ) {
				case self::HIDDEN_NONE:
					if ( $oldHiddenLevel == self::HIDDEN_OVERSIGHT ) {
						$removed[] = $context->msg( 'centralauth-log-status-oversighted' )
							->inContentLanguage()->text();
					} else {
						$removed[] = $context->msg( 'centralauth-log-status-hidden' )
							->inContentLanguage()->text();
					}
					break;
				case self::HIDDEN_LISTS:
					$added[] = $context->msg( 'centralauth-log-status-hidden' )
						->inContentLanguage()->text();
					if ( $oldHiddenLevel == self::HIDDEN_OVERSIGHT ) {
						$removed[] = $context->msg( 'centralauth-log-status-oversighted' )
							->inContentLanguage()->text();
					}
					break;
				case self::HIDDEN_OVERSIGHT:
					$added[] = $context->msg( 'centralauth-log-status-oversighted' )
						->inContentLanguage()->text();
					if ( $oldHiddenLevel == self::HIDDEN_LISTS ) {
						$removed[] = $context->msg( 'centralauth-log-status-hidden' )
							->inContentLanguage()->text();
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
			$addedMsg = count( $added ) ?
				implode( ', ', $added ) : $context->msg( 'centralauth-log-status-none' )
					->inContentLanguage()->text();
			$removedMsg = count( $removed ) ?
				implode( ', ', $removed ) : $context->msg( 'centralauth-log-status-none' )
					->inContentLanguage()->text();

			$returnStatus->successCount = count( $added ) + count( $removed );
			$returnStatus->success['added'] = $addedMsg;
			$returnStatus->success['removed'] = $removedMsg;

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
	 * @param string $reason
	 */
	public function suppress( $reason ) {
		global $wgUser;
		$this->doCrosswikiSuppression( true, $wgUser->getName(), $reason );
	}

	/**
	 * Unsuppresses all user accounts in all wikis.
	 *
	 * @param string $reason
	 */
	public function unsuppress( $reason ) {
		global $wgUser;
		$this->doCrosswikiSuppression( false, $wgUser->getName(), $reason );
	}

	/**
	 * @param bool $suppress
	 * @param string $by
	 * @param string $reason
	 */
	protected function doCrosswikiSuppression( $suppress, $by, $reason ) {
		global $wgCentralAuthWikisPerSuppressJob;
		$this->loadAttached();
		if ( count( $this->mAttachedArray ) <= $wgCentralAuthWikisPerSuppressJob ) {
			foreach ( $this->mAttachedArray as $wiki ) {
				$this->doLocalSuppression( $suppress, $wiki, $by, $reason );
			}
		} else {
			$jobParams = [
				'username' => $this->getName(),
				'suppress' => $suppress,
				'by' => $by,
				'reason' => $reason,
			];
			$jobs = [];
			$chunks = array_chunk( $this->mAttachedArray, $wgCentralAuthWikisPerSuppressJob );
			foreach ( $chunks as $wikis ) {
				$jobParams['wikis'] = $wikis;
				$jobs[] = Job::factory(
					'crosswikiSuppressUser',
					Title::makeTitleSafe( NS_USER, $this->getName() ),
					$jobParams );
			}
			// Push the jobs right before COMMIT (which is likely to succeed).
			// If the job push fails, then the transaction will roll back.
			$dbw = self::getCentralDB();
			$dbw->onTransactionPreCommitOrIdle( function () use ( $jobs ) {
				JobQueueGroup::singleton()->push( $jobs );
			} );
		}
	}

	/**
	 * Suppresses a local account of a user.
	 *
	 * @param bool $suppress
	 * @param string $wiki
	 * @param string $by
	 * @param string $reason
	 * @return array|null Error array on failure
	 */
	public function doLocalSuppression( $suppress, $wiki, $by, $reason ) {
		global $wgConf, $wgCentralAuthGlobalBlockInterwikiPrefix;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB( $wiki );
		$dbw = $lb->getConnectionRef( DB_MASTER, [], $wiki );
		$data = $this->localUserData( $wiki );

		if ( $suppress ) {
			list( , $lang ) = $wgConf->siteFromDB( $wiki );
			$langNames = Language::fetchLanguageNames();
			$lang = isset( $langNames[$lang] ) ? $lang : 'en';
			$blockReason = wfMessage( 'centralauth-admin-suppressreason', $by, $reason )
				->inLanguage( $lang )->text();

			$block = new Block( [
				'address' => $this->mName,
				'user' => $data['id'],
				'reason' => $blockReason,
				'timestamp' => wfTimestampNow(),
				'expiry' => $dbw->getInfinity(),
				'createAccount' => true,
				'enableAutoblock' => true,
				'hideName' => true,
				'blockEmail' => true,
				'byText' => $wgCentralAuthGlobalBlockInterwikiPrefix . '>' . $by
			] );

			# On normal block, BlockIp hook would be run here, but doing
			# that from CentralAuth doesn't seem a good idea...

			if ( !$block->insert( $dbw ) ) {
				return [ 'ipb_already_blocked' ];
			}
			# Ditto for BlockIpComplete hook.

			RevisionDeleteUser::suppressUserName( $this->mName, $data['id'], $dbw );

			# Locally log to suppress ?
		} else {
			$blockQuery = Block::getQueryInfo();
			$ids = $dbw->selectFieldValues(
				$blockQuery['tables'],
				'ipb_id',
				[
					'ipb_user' => $data['id'],
					$blockQuery['fields']['ipb_by'] . ' = 0', // Our blocks will have ipb_by = 0
					'ipb_deleted' => true,
				],
				__METHOD__,
				[],
				$blockQuery['joins']
			);
			if ( $ids ) {
				$dbw->delete( 'ipblocks', [ 'ipb_id' => $ids ], __METHOD__ );
			}

			// Unsuppress only if unblocked
			if ( $dbw->affectedRows() ) {
				RevisionDeleteUser::unsuppressUserName( $this->mName, $data['id'], $dbw );
			}
		}
		return null;
	}

	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param string $wikiID
	 * @param string $method
	 * @param bool $sendToRC
	 * @param string|int $ts MediaWiki timestamp or 0 for current time
	 *
	 * Prerequisites:
	 * - completed migration state
	 */
	public function attach( $wikiID, $method = 'new', $sendToRC = true, $ts = 0 ) {
		$this->checkWriteMode();

		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->insert( 'localuser',
			[
				'lu_wiki'               => $wikiID,
				'lu_name'               => $this->mName,
				'lu_attached_timestamp' => $dbw->timestamp( $ts ),
				'lu_attached_method'    => $method,
				'lu_local_id'           => $this->getLocalId( $wikiID ),
				'lu_global_id'          => $this->getId() ],
			__METHOD__,
			[ 'IGNORE' ]
		);
		$success = $dbw->affectedRows() === 1;

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
	 * @return mixed true if login available, or string status, one of: "no user", "locked"
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
		if ( !User::idFromName( $this->getName() ) && $this->isOversighted() ) {
			return "locked";
		}

		return true;
	}

	/**
	 * Attempt to authenticate the global user account with the given password
	 * @param string $password
	 * @return string status, one of: "ok", "no user", "locked", or "bad password".
	 * @todo Currently only the "ok" result is used (i.e. either use, or return a bool).
	 */
	public function authenticate( $password ) {
		$ret = $this->canAuthenticate();
		if ( $ret !== true ) {
			return $ret;
		}

		$status = $this->matchHash( $password, $this->getPasswordObject() );
		if ( $status->isGood() ) {
			wfDebugLog( 'CentralAuth',
				"authentication for '$this->mName' succeeded" );

			$passwordFactory = new PasswordFactory();
			$passwordFactory->init( RequestContext::getMain()->getConfig() );
			if ( $passwordFactory->needsUpdate( $status->getValue() ) ) {
				DeferredUpdates::addCallableUpdate( function () use ( $password ) {
					if ( CentralAuthUtils::getCentralDB()->isReadOnly() ) {
						return;
					}

					$centralUser = CentralAuthUser::newMasterInstanceFromId( $this->getId() );
					if ( $centralUser ) {
						// Don't bother resetting the auth token for a hash
						// upgrade. It's not really a password *change*, and
						// since this is being done post-send it'll cause the
						// user to be logged out when they just tried to log in
						// since it can't update the just-sent session cookies.
						$centralUser->setPassword( $password, false );
						$centralUser->saveSettings();
					}
				} );
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
		$ret = $this->canAuthenticate();
		if ( $ret !== true ) {
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

		if ( $password->verify( $plaintext ) ) {
			$matched = true;
		} elseif ( !( $password instanceof Pbkdf2Password ) && function_exists( 'iconv' ) ) {
			// Some wikis were converted from ISO 8859-1 to UTF-8;
			// retained hashes may contain non-latin chars.
			Wikimedia\suppressWarnings();
			$latin1 = iconv( 'UTF-8', 'WINDOWS-1252//TRANSLIT', $plaintext );
			Wikimedia\restoreWarnings();
			if ( $latin1 !== false && $password->verify( $latin1 ) ) {
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

		$passwordFactory = new PasswordFactory();
		$passwordFactory->init( RequestContext::getMain()->getConfig() );

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
	 * @return string[] of database name strings
	 */
	public function listUnattached() {
		if ( IP::isIPAddress( $this->mName ) ) {
			return []; // don't bother with master queries
		}

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
		// Make sure lazy-loading in listUnattached() works, as we
		// may need to *switch* to using the DB master for this query
		$db = self::centralLBHasRecentMasterChanges()
			? CentralAuthUtils::getCentralDB()
			: $this->getSafeReadDB();

		$result = $db->select(
			[ 'localnames', 'localuser' ],
			[ 'ln_wiki' ],
			[ 'ln_name' => $this->mName, 'lu_name IS NULL' ],
			__METHOD__,
			[],
			[ 'localuser' => [ 'LEFT OUTER JOIN',
				[ 'ln_wiki=lu_wiki', 'ln_name=lu_name' ] ] ]
		);

		$dbs = [];
		foreach ( $result as $row ) {
			if ( !WikiMap::getWiki( $row->ln_wiki ) ) {
				LoggerFactory::getInstance( 'CentralAuth' )->warning(
					__METHOD__ . ': invalid wiki in localnames: ' . $row->ln_wiki );
				continue;
			}

			/** @var $row object */
			$dbs[] = $row->ln_wiki;
		}

		return $dbs;
	}

	/**
	 * @param string $wikiID
	 */
	public function addLocalName( $wikiID ) {
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->insert( 'localnames',
			[
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName ],
			__METHOD__,
			[ 'IGNORE' ] );
	}

	/**
	 * @param string $wikiID
	 */
	public function removeLocalName( $wikiID ) {
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->delete( 'localnames',
			[
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName ],
			__METHOD__ );
	}

	/**
	 * Updates the localname table after a rename
	 * @param string $wikiID
	 * @param string $newname
	 */
	public function updateLocalName( $wikiID, $newname ) {
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->update(
			'localnames',
			[ 'ln_name' => $newname ],
			[ 'ln_wiki' => $wikiID, 'ln_name' => $this->mName ],
			__METHOD__
		);
	}

	/**
	 * @return bool
	 */
	public function lazyImportLocalNames() {
		$known = (bool)CentralAuthUtils::getCentralReplicaDB()->selectField(
			'globalnames', '1', [ 'gn_name' => $this->mName ], __METHOD__
		);
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
	 * @return bool whether any results were found
	 */
	protected function importLocalNames() {
		$rows = [];
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		foreach ( self::getWikiList() as $wikiID ) {
			$dbr = $lbFactory->getMainLB( $wikiID )->getConnectionRef( DB_REPLICA, [], $wikiID );
			$id = $dbr->selectField(
				'user',
				'user_id',
				[ 'user_name' => $this->mName ],
				__METHOD__
			);
			if ( $id ) {
				$rows[] = [ 'ln_wiki' => $wikiID, 'ln_name' => $this->mName ];
			}
		}

		if ( $rows || $this->exists() ) {
			$dbw = CentralAuthUtils::getCentralDB();
			$dbw->startAtomic( __METHOD__ );
			$dbw->insert(
				'globalnames',
				[ 'gn_name' => $this->mName ],
				__METHOD__,
				[ 'IGNORE' ]
			);
			if ( $rows ) {
				$dbw->insert(
					'localnames',
					$rows,
					__METHOD__,
					[ 'IGNORE' ]
				);
			}
			$dbw->endAtomic( __METHOD__ );
		}

		return (bool)$rows;
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

		wfDebugLog( 'CentralAuthVerbose',
			"Loading attached wiki list for global user {$this->mName} from DB"
		);

		$db = $this->getSafeReadDB();

		$result = $db->select( 'localuser',
			[ 'lu_wiki' ],
			[ 'lu_name' => $this->mName ],
			__METHOD__ );

		$wikis = [];
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
	 * @return string[] Database name strings
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
	 * @param int $flags Bitfield of CentralAuthUser::READ_* constants
	 * @return array|bool
	 */
	public function renameInProgressOn( $wiki, $flags = 0 ) {
		$renameState = new GlobalRenameUserStatus( $this->mName );

		// Use master as this is being used for various critical things
		$names = $renameState->getNames(
			$wiki,
			( $flags & self::READ_LATEST ) == self::READ_LATEST ? 'master' : 'slave'
		);

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
				? [] : explode( '|', $this->mBeingRenamed );
		}

		return $this->mBeingRenamedArray;
	}

	/**
	 * Returns a list of all groups where the user is a member of the group on at
	 * least one wiki where their account is attached.
	 * @return array of group names where the user is a member on at least one wiki
	 */
	public function getLocalGroups() {
		$localgroups = [];
		array_map(
			function ( $local ) use ( &$localgroups ) {
				$localgroups = array_unique( array_merge(
					$localgroups, array_keys( $local['groupMemberships'] )
				) );
			},
			$this->queryAttached()
		);
		return $localgroups;
	}

	/**
	 * Get information about each local user attached to this account
	 *
	 * @return array Map of database name to property table with members:
	 *    wiki                  The wiki ID (database name)
	 *    attachedTimestamp     The MW timestamp when the account was attached
	 *    attachedMethod        Attach method: password, mail or primary
	 *    ...                   All information returned by localUserData()
	 */
	public function queryAttached() {
		// Cache $wikis to avoid expensive query whenever possible
		// mAttachedInfo is shared with queryAttachedBasic(); check whether it contains partial data
		if (
			$this->mAttachedInfo !== null
			&& ( !$this->mAttachedInfo || array_key_exists( 'id', reset( $this->mAttachedInfo ) ) )
		) {
			return $this->mAttachedInfo;
		}

		$wikis = $this->queryAttachedBasic();

		foreach ( $wikis as $wikiId => $_ ) {
			try {
				$localUser = $this->localUserData( $wikiId );
				$wikis[$wikiId] = array_merge( $wikis[$wikiId], $localUser );
			} catch ( LocalUserNotFoundException $e ) {
				// T119736: localuser table told us that the user was attached
				// from $wikiId but there is no data in the master or slaves
				// that corroborates that.
				unset( $wikis[$wikiId] );
				// Queue a job to delete the bogus attachment record.
				$this->queueAdminUnattachJob( $wikiId );
			}
		}

		$this->mAttachedInfo = $wikis;

		return $wikis;
	}

	/**
	 * Helper method for queryAttached().
	 *
	 * Does the cheap part of the lookup by checking the cross-wiki localuser table,
	 * and returns attach time and method.
	 *
	 * @return array
	 */
	protected function queryAttachedBasic() {
		if ( $this->mAttachedInfo !== null ) {
			return $this->mAttachedInfo;
		}

		$db = $this->getSafeReadDB();

		$result = $db->select(
			'localuser',
			[
				'lu_wiki',
				'lu_attached_timestamp',
				'lu_attached_method' ],
			[
				'lu_name' => $this->mName ],
			__METHOD__ );

		$wikis = [];
		foreach ( $result as $row ) {
			if ( !WikiMap::getWiki( $row->lu_wiki ) ) {
				LoggerFactory::getInstance( 'CentralAuth' )->warning(
					__METHOD__ . ': invalid wiki in localuser: ' . $row->lu_wiki );
				continue;
			}

			/** @var $row object */
			$wikis[$row->lu_wiki] = [
				'wiki' => $row->lu_wiki,
				'attachedTimestamp' => wfTimestampOrNull( TS_MW,
					$row->lu_attached_timestamp ),
				'attachedMethod' => $row->lu_attached_method,
			];
		}

		$this->mAttachedInfo = $wikis;

		return $wikis;
	}

	/**
	 * Find any remaining migration records for this username which haven't gotten attached to
	 * some global account.
	 * Formatted as associative array with some data.
	 *
	 * @throws Exception
	 * @return array
	 */
	public function queryUnattached() {
		$wikiIDs = $this->listUnattached();

		$items = [];
		foreach ( $wikiIDs as $wikiID ) {
			try {
				$data = $this->localUserData( $wikiID );
				$items[$wikiID] = $data;
			} catch ( LocalUserNotFoundException $e ) {
				// T119736: localnames table told us that the name was
				// unattached on $wikiId but there is no data in the master
				// or slaves that corroborates that.
				// Queue a job to delete the bogus record.
				$this->queueAdminUnattachJob( $wikiID );
			}
		}

		return $items;
	}

	/**
	 * Fetch a row of user data needed for migration.
	 *
	 * Returns most data in the user and ipblocks tables, user groups, and editcount.
	 *
	 * @param string $wikiID
	 * @throws LocalUserNotFoundException if local user not found
	 * @return array
	 */
	protected function localUserData( $wikiID ) {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB( $wikiID );
		$db = $lb->getConnection( DB_REPLICA, [], $wikiID );
		$fields = [
				'user_id',
				'user_email',
				'user_email_authenticated',
				'user_password',
				'user_editcount',
				'user_registration',
			];
		$conds = [ 'user_name' => $this->mName ];
		$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		if ( !$row ) {
			# Row missing from slave, try the master instead
			$lb->reuseConnection( $db );
			$db = $lb->getConnection( DB_MASTER, [], $wikiID );
			$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		}
		if ( !$row ) {
			$lb->reuseConnection( $db );
			$ex = new LocalUserNotFoundException(
				"Could not find local user data for {$this->mName}@{$wikiID}" );
			LoggerFactory::getInstance( 'CentralAuth' )->warning(
				'Could not find local user data for {username}@{wikiId}',
				[
					'username' => $this->mName,
					'wikiId' => $wikiID,
					'exception' => $ex,
				]
			);
			throw $ex;
		}

		$data = [
			'wiki' => $wikiID,
			'id' => $row->user_id,
			'email' => $row->user_email,
			'emailAuthenticated' =>
				wfTimestampOrNull( TS_MW, $row->user_email_authenticated ),
			'registration' =>
				wfTimestampOrNull( TS_MW, $row->user_registration ),
			'password' => $row->user_password,
			'editCount' => $row->user_editcount,
			'groupMemberships' => [], // array of (group name => UserGroupMembership object)
			'blocked' => false ];

		// Edit count field may not be initialized...
		if ( is_null( $row->user_editcount ) ) {
			$actorWhere = ActorMigration::newMigration()
				->getWhere( $db, 'rev_user', User::newFromId( $data['id'] ) );
			$data['editCount'] = 0;
			foreach ( $actorWhere['orconds'] as $cond ) {
				$data['editCount'] += $db->selectField(
					[ 'revision' ] + $actorWhere['tables'],
					'COUNT(*)',
					$cond,
					__METHOD__,
					[],
					$actorWhere['joins']
				);
			}
		}

		// And we have to fetch groups separately, sigh...
		$data['groupMemberships'] =
			UserGroupMembership::getMembershipsForUser( $data['id'], $db );

		// And while we're in here, look for user blocks :D
		$commentStore = CommentStore::getStore();
		$commentQuery = $commentStore->getJoin( 'ipb_reason' );
		$result = $db->select(
			[ 'ipblocks' ] + $commentQuery['tables'],
			[
				'ipb_expiry', 'ipb_block_email',
				'ipb_anon_only', 'ipb_create_account',
				'ipb_enable_autoblock', 'ipb_allow_usertalk',
			] + $commentQuery['fields'],
			[ 'ipb_user' => $data['id'] ],
			__METHOD__,
			[],
			$commentQuery['joins']
		);
		global $wgLang;
		foreach ( $result as $row ) {
			if ( $wgLang->formatExpiry( $row->ipb_expiry, TS_MW ) > wfTimestampNow() ) {
				$data['block-expiry'] = $row->ipb_expiry;
				$data['block-reason'] = $commentStore->getComment( 'ipb_reason', $row )->text;
				$data['block-anononly'] = (bool)$row->ipb_anon_only;
				$data['block-nocreate'] = (bool)$row->ipb_create_account;
				$data['block-noautoblock'] = !( (bool)$row->ipb_enable_autoblock );
				// Poorly named database column
				$data['block-nousertalk'] = !( (bool)$row->ipb_allow_usertalk );
				$data['block-noemail'] = (bool)$row->ipb_block_email;
				$data['blocked'] = true;
			}
		}
		$result->free();
		$lb->reuseConnection( $db );

		return $data;
	}

	/**
	 * @return string
	 */
	public function getEmail() {
		$this->loadState();
		return $this->mEmail;
	}

	/**
	 * @return string
	 */
	public function getEmailAuthenticationTimestamp() {
		$this->loadState();
		return $this->mAuthenticationTimestamp;
	}

	/**
	 * @param string $email
	 * @return void
	 */
	public function setEmail( $email ) {
		$this->checkWriteMode();
		$this->loadState();
		if ( $this->mEmail !== $email ) {
			$this->mEmail = $email;
			$this->mStateDirty = true;
		}
	}

	/**
	 * @param string $ts
	 * @return void
	 */
	public function setEmailAuthenticationTimestamp( $ts ) {
		$this->checkWriteMode();
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
		$passwordFactory = new PasswordFactory();
		$passwordFactory->init( RequestContext::getMain()->getConfig() );
		return [
			'',
			$passwordFactory->newFromPlaintext( $password )->toString()
		];
	}

	/**
	 * Set the account's password
	 * @param string $password plaintext
	 * @param bool $resetAuthToken if we should reset the login token
	 * @return bool true
	 */
	public function setPassword( $password, $resetAuthToken = true ) {
		$this->checkWriteMode();

		// Make sure state is loaded before updating ->mPassword
		$this->loadState();

		list( $salt, $hash ) = $this->saltedPassword( $password );

		$this->mPassword = $hash;
		$this->mSalt = $salt;

		if ( $this->getId() ) {
			$dbw = CentralAuthUtils::getCentralDB();
			$dbw->update( 'globaluser',
				[
					'gu_salt'     => $salt,
					'gu_password' => $hash,
				],
				[
					'gu_id' => $this->getId(),
				],
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
	 * @return string
	 */
	public function getPassword() {
		$this->loadState();
		if ( substr( $this->mPassword, 0, 1 ) != ':' ) {
			$this->mPassword = ':B:' . $this->mSalt . ':' . $this->mPassword;
		}
		return $this->mPassword;
	}

	/**
	 * Get the domain parameter for setting a global cookie.
	 * This allows other extensions to easily set global cookies without directly relying on
	 * $wgCentralAuthCookieDomain (in case CentralAuth's implementation changes at some point).
	 *
	 * @return string
	 */
	public static function getCookieDomain() {
		global $wgCentralAuthCookieDomain;

		/** @var CentralAuthSessionProvider $provider */
		$provider = MediaWiki\Session\SessionManager::singleton()
			->getProvider( 'CentralAuthSessionProvider' );
		if ( $provider ) {
			return $provider->getCentralCookieDomain();
		}

		return $wgCentralAuthCookieDomain;
	}

	/**
	 * Check a global auth token against the one we know of in the database.
	 *
	 * @param string $token
	 * @return bool
	 */
	public function validateAuthToken( $token ) {
		return hash_equals( $this->getAuthToken(), $token );
	}

	/**
	 * Generate a new random auth token, and store it in the database.
	 * Should be called as often as possible, to the extent that it will
	 * not randomly log users out (so on logout, as is done currently, is a good time).
	 */
	public function resetAuthToken() {
		$this->checkWriteMode();

		// Load state, since its hard to reset the token without it
		$this->loadState();

		// Generate a random token.
		$this->mAuthToken = MWCryptRand::generateHex( 32 );
		$this->mStateDirty = true;

		// Save it.
		$this->saveSettings();
	}

	public function saveSettings() {
		$this->checkWriteMode();

		if ( !$this->mStateDirty ) {
			return;
		}
		$this->mStateDirty = false;

		if ( CentralAuthUtils::isReadOnly() ) {
			return;
		}

		$this->loadState();
		if ( !$this->mGlobalId ) {
			return;
		}

		$newCasToken = $this->mCasToken + 1;

		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->update( 'globaluser',
			[ # SET
				'gu_password' => $this->mPassword,
				'gu_salt' => $this->mSalt,
				'gu_auth_token' => $this->mAuthToken,
				'gu_locked' => $this->mLocked,
				'gu_hidden' => $this->getHiddenLevel(),
				'gu_email' => $this->mEmail,
				'gu_email_authenticated' =>
					$dbw->timestampOrNull( $this->mAuthenticationTimestamp ),
				'gu_home_db' => $this->getHomeWiki(),
				'gu_cas_token' => $newCasToken
			],
			[ # WHERE
				'gu_id' => $this->mGlobalId,
				'gu_cas_token' => $this->mCasToken
			],
			__METHOD__
		);

		if ( !$dbw->affectedRows() ) {
			// Maybe the problem was a missed cache update; clear it to be safe
			$this->invalidateCache();
			// User was changed in the meantime or loaded with stale data
			$from = ( $this->mFromMaster ) ? 'master' : 'slave';
			LoggerFactory::getInstance( 'CentralAuth' )->warning(
				"CAS update failed on gu_cas_token for user ID '{globalId}' " .
				"(read from {from}); the version of the user to be saved is older than " .
				"the current version.",
				[
					'globalId' => $this->mGlobalId,
					'from' => $from,
					'exception' => new Exception( 'CentralAuth gu_cas_token conflict' ),
				] );
			return;
		}

		$this->mCasToken = $newCasToken;
		$this->invalidateCache();
	}

	/**
	 * @return array
	 */
	public function getGlobalGroups() {
		$this->loadGroups();

		return $this->mGroups;
	}

	/**
	 * @return array
	 */
	public function getGlobalRights() {
		$this->loadGroups();

		$rights = [];
		$sets = [];
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
	 * @param string $groups
	 * @return void
	 */
	public function removeFromGlobalGroups( $groups ) {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();

		# Delete from the DB
		$dbw->delete( 'global_user_groups',
			[ 'gug_user' => $this->getId(), 'gug_group' => $groups ],
			__METHOD__ );

		$this->invalidateCache();
	}

	/**
	 * @param string[]|string $groups
	 * @return void
	 */
	public function addToGlobalGroups( $groups ) {
		$this->checkWriteMode();
		$dbw = CentralAuthUtils::getCentralDB();

		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}

		$insert_rows = [];
		foreach ( $groups as $group ) {
			$insert_rows[] = [ 'gug_user' => $this->getId(), 'gug_group' => $group ];
		}

		# Replace into the DB
		$dbw->replace( 'global_user_groups',
			[ [ 'gug_user', 'gug_group' ] ],
			$insert_rows, __METHOD__ );

		$this->invalidateCache();
	}

	/**
	 * @return array
	 */
	public static function availableGlobalGroups() {
		$dbr = CentralAuthUtils::getCentralReplicaDB();

		$res = $dbr->select( 'global_group_permissions', 'distinct ggp_group', [], __METHOD__ );

		$groups = [];

		foreach ( $res as $row ) {
			/** @var $row object */
			$groups[] = $row->ggp_group;
		}

		return $groups;
	}

	/**
	 * @param string $group
	 * @return array
	 */
	public static function globalGroupPermissions( $group ) {
		$dbr = CentralAuthUtils::getCentralReplicaDB();

		$res = $dbr->select( [ 'global_group_permissions' ],
			[ 'ggp_permission' ], [ 'ggp_group' => $group ], __METHOD__ );

		$rights = [];

		foreach ( $res as $row ) {
			/** @var $row object */
			$rights[] = $row->ggp_permission;
		}

		return $rights;
	}

	/**
	 * @param string $perm
	 * @return bool
	 */
	public function hasGlobalPermission( $perm ) {
		$perms = $this->getGlobalRights();

		return in_array( $perm, $perms );
	}

	/**
	 * @return array
	 */
	public static function getUsedRights() {
		$dbr = CentralAuthUtils::getCentralReplicaDB();

		$res = $dbr->select( 'global_group_permissions', 'distinct ggp_permission',
			[], __METHOD__ );

		$rights = [];

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
			wfDebugLog( 'CentralAuthVerbose',
				"Deferring cache invalidation because we're in a transaction" );
		}
	}

	/**
	 * For when speed is of the essence (e.g. when batch-purging users after rights changes)
	 */
	public function quickInvalidateCache() {
		wfDebugLog( 'CentralAuthVerbose',
			"Quick cache invalidation for global user {$this->mName}" );

		CentralAuthUtils::getCentralDB()->onTransactionPreCommitOrIdle( function () {
			$cache = ObjectCache::getMainWANInstance();
			$cache->delete( $this->getCacheKey( $cache ) );
		} );
	}

	/**
	 * End a "transaction".
	 * A transaction delays cache invalidation until after
	 * some operation which would otherwise repeatedly do so.
	 * Intended to be used for things like migration.
	 */
	public function endTransaction() {
		wfDebugLog( 'CentralAuthVerbose',
			"Finishing CentralAuthUser cache-invalidating transaction" );
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
		wfDebugLog( 'CentralAuthVerbose',
			"Beginning CentralAuthUser cache-invalidating transaction" );
		// Delay cache invalidation
		$this->mDelayInvalidation = 1;
	}

	/**
	 * Check if the user is attached on a given wiki id.
	 *
	 * @param string $wiki
	 * @return bool
	 */
	public function attachedOn( $wiki ) {
		$this->loadAttached();
		return $this->exists() && in_array( $wiki, $this->mAttachedArray );
	}

	/**
	 * Get a hash representing the user/locked/hidden state of this user,
	 * used to check for edit conflicts
	 *
	 * @param bool $recache Force a reload of the user from the database
	 * @return string
	 */
	public function getStateHash( $recache = false ) {
		$this->loadState( $recache );
		return md5( $this->mGlobalId . ':' . $this->mName . ':' . $this->mHidden . ':' .
			(int)$this->mLocked );
	}

	/**
	 * Log an action for the current user
	 *
	 * @param string $action
	 * @param string $reason
	 * @param array $params
	 * @param bool $suppressLog
	 */
	public function logAction( $action, $reason = '', $params = [], $suppressLog = false ) {
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
	 * @param string $wikiId
	 * @param int $userId
	 */
	private function clearLocalUserCache( $wikiId, $userId ) {
		User::purge( $wikiId, $userId );
	}
}
