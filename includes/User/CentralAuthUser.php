<?php
/**
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

namespace MediaWiki\Extension\CentralAuth\User;

use BadMethodCallException;
use CentralAuthSessionProvider;
use DomainException;
use Exception;
use LogicException;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\CentralAuthReadOnlyError;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\RCFeed\CARCFeedFormatter;
use MediaWiki\Extension\CentralAuth\ScrambledPassword;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Password\AbstractPbkdf2Password;
use MediaWiki\Password\InvalidPassword;
use MediaWiki\Password\Password;
use MediaWiki\Password\PasswordError;
use MediaWiki\Password\PasswordFactory;
use MediaWiki\RCFeed\FormattedRCFeed;
use MediaWiki\RCFeed\RCFeed;
use MediaWiki\Session\SessionManager;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\ExternalUserNames;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MWCryptHash;
use MWCryptRand;
use RevisionDeleteUser;
use RuntimeException;
use stdClass;
use Wikimedia\AtEase\AtEase;
use Wikimedia\IPUtils;
use Wikimedia\NormalizedException\NormalizedException;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Stats\StatsFactory;

class CentralAuthUser implements IDBAccessObject {

	/**
	 * The username of the current user.
	 * @var string
	 */
	private $mName;
	/** @var bool */
	public $mStateDirty = false;
	/** @var int|false */
	private $mDelayInvalidation = 0;

	/** @var string[]|null */
	private $mAttachedArray;
	/** @var string */
	private $mEmail;
	/** @var bool */
	private $mEmailAuthenticated;
	/**
	 * @var string|null
	 * @internal
	 */
	public $mHomeWiki;
	/** @var int|null */
	private $mHiddenLevel;
	/** @var bool */
	private $mLocked;
	/**
	 * @var string|null As string, it is "\n"-imploded
	 */
	private $mAttachedList;
	/** @var bool|null Whether a local account of this name exists */
	private $mExistsLocally;
	/** @var string */
	private $mAuthenticationTimestamp;
	/**
	 * @var array|null
	 * @phan-var ?list<array{group:string,expiry:?string}>
	 */
	private $mGroups;
	/**
	 * @var array|null
	 * @phan-var ?list<array{right:string,set:?int}>
	 */
	private $mRights;
	/** @var string|null Null when uninitialized, or when the central user doesn't exist */
	private $mPassword;
	/** @var string|null */
	private $mAuthToken;
	/** @var int|null */
	private $mGlobalId;
	/** @var bool */
	private $mFromPrimary;
	/** @var bool */
	private $mIsAttached;
	/** @var string */
	private $mRegistration;
	/** @var int */
	private $mGlobalEditCount;
	/** @var string|null */
	private $mBeingRenamed;
	/** @var string[]|null */
	private $mBeingRenamedArray;
	/** @var array[]|null */
	private $mAttachedInfo;
	/** @var int */
	private $mCasToken = 0;
	/** @var \Psr\Log\LoggerInterface */
	private $logger;
	private StatsFactory $statsFactory;
	private WANObjectCache $wanCache;

	/** @var string[] */
	private static $mCacheVars = [
		'mGlobalId',
		'mPassword',
		'mAuthToken',
		'mLocked',
		'mHiddenLevel',
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

	private const VERSION = 13;

	public const HIDDEN_LEVEL_NONE = 0;
	public const HIDDEN_LEVEL_LISTS = 1;
	public const HIDDEN_LEVEL_SUPPRESSED = 2;

	/**
	 * The maximum number of edits a user can have and still be hidden
	 */
	private const HIDE_CONTRIBLIMIT = 1000;

	/**
	 * The possible responses from self::authenticate(),
	 * self::canAuthenticate() and self::authenticateWithToken().
	 *
	 * Constants are defined as lowercase strings for
	 * backwards compatibility.
	 */
	public const AUTHENTICATE_OK = "ok";
	public const AUTHENTICATE_NO_USER = "no user";
	public const AUTHENTICATE_LOCKED = "locked";
	public const AUTHENTICATE_BAD_PASSWORD = "bad password";
	public const AUTHENTICATE_BAD_TOKEN = "bad token";
	public const AUTHENTICATE_GOOD_PASSWORD = "good password";

	/**
	 * @note Don't call this directly. Use self::getInstanceByName() or
	 *  self::getPrimaryInstanceByName() instead.
	 * @param string $username
	 * @param int $flags Supports IDBAccessObject::READ_LATEST to use the primary DB
	 */
	public function __construct( $username, $flags = 0 ) {
		$this->mName = $username;
		$this->resetState();
		if ( DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST ) ) {
			$this->mFromPrimary = true;
		}
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );

		$mwServices = MediaWikiServices::getInstance();
		$this->statsFactory = $mwServices->getStatsFactory();
		$this->wanCache = $mwServices->getMainWANObjectCache();
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 */
	public static function getInstance( UserIdentity $user ): self {
		return self::getInstanceByName( $user->getName() );
	}

	/**
	 * @param string $username A valid username, or an IP address/range in a format understood
	 *   by IPUtils.
	 * @throws NormalizedException on invalid usernames.
	 */
	private static function normalizeUsername( string $username ): string {
		if ( IPUtils::isValid( $username ) ) {
			$canonUsername = IPUtils::sanitizeIP( $username );
		} elseif ( IPUtils::isValidRange( $username ) ) {
			$canonUsername = IPUtils::sanitizeRange( $username );
		} elseif ( ExternalUserNames::isExternal( $username ) ) {
			$canonUsername = $username;
		} else {
			$canonUsername = MediaWikiServices::getInstance()->getUserNameUtils()
				->getCanonical( $username );
		}

		if ( $canonUsername === false || $canonUsername === null ) {
			throw new NormalizedException( 'Invalid username: {username}', [ 'username' => $username ] );
		}
		return $canonUsername;
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied user.
	 *
	 * @param string $username A valid username. (Since 1.42 it does not have to be in the
	 *   canonical form anymore.) IP addresses/ranges and external usernames are also accepted
	 *   for B/C but discouraged; they will be handled like a non-registered username.
	 * @throws NormalizedException on invalid usernames.
	 */
	public static function getInstanceByName( $username ): self {
		$canonUsername = self::normalizeUsername( $username );
		$cache = CentralAuthServices::getUserCache();
		$ret = $cache->get( $canonUsername );
		if ( !$ret ) {
			$ret = new self( $canonUsername );
			$cache->set( $ret );
		}
		return $ret;
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 * This object will use DB_PRIMARY.
	 * @param UserIdentity $user
	 * @since 1.37
	 */
	public static function getPrimaryInstance( UserIdentity $user ): self {
		return self::getPrimaryInstanceByName( $user->getName() );
	}

	/**
	 * Create a (cached) CentralAuthUser object corresponding to the supplied User.
	 * This object will use DB_PRIMARY.
	 * @param string $username A valid username. (Since 1.44 it does not have to be in the
	 *   canonical form anymore.) IP addresses/ranges and external usernames are also accepted
	 *   for B/C but discouraged; they will be handled like a non-registered username.
	 * @since 1.37
	 * @throws NormalizedException on invalid usernames.
	 */
	public static function getPrimaryInstanceByName( $username ): self {
		$canonUsername = self::normalizeUsername( $username );
		$cache = CentralAuthServices::getUserCache();
		$ret = $cache->get( $canonUsername, true );
		if ( !$ret ) {
			$ret = new self( $canonUsername, IDBAccessObject::READ_LATEST );
			$cache->set( $ret );
		}
		return $ret;
	}

	/**
	 * Test if this is a write-mode instance, and log if not.
	 */
	private function checkWriteMode() {
		if ( !$this->mFromPrimary ) {
			$this->logger->warning(
				'Write mode called on replica-loaded object',
				[ 'exception' => new RuntimeException() ]
			);
		}
	}

	/**
	 * Whether the data was loaded from the primary database
	 *
	 * @return bool
	 */
	public function isFromPrimary() {
		return $this->mFromPrimary;
	}

	/**
	 * @return IReadableDatabase Primary database or replica based on shouldUsePrimaryDB()
	 * @throws CentralAuthReadOnlyError
	 */
	private function getSafeReadDB() {
		if ( $this->shouldUsePrimaryDB() ) {
			return CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		} else {
			return CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		}
	}

	/**
	 * Get (and init if needed) the value of mFromPrimary
	 *
	 * @return bool
	 */
	private function shouldUsePrimaryDB() {
		$dbManager = CentralAuthServices::getDatabaseManager();
		if ( $dbManager->isReadOnly() ) {
			return false;
		}
		if ( $this->mFromPrimary === null ) {
			$this->mFromPrimary = $dbManager->centralLBHasRecentPrimaryChanges();
		}

		return $this->mFromPrimary;
	}

	/**
	 * Return query data needed to properly use self::newFromRow
	 * @return array (
	 *   'tables' => array,
	 *   'fields' => array,
	 *   'conds' => array,
	 *   'options' => array,
	 *   'join_conds' => array,
	 *  )
	 */
	public static function selectQueryInfo() {
		$wikiId = WikiMap::getCurrentWikiId();
		return [
			'tables' => [ 'globaluser', 'localuser', 'localnames' ],
			'fields' => [
				'gu_id', 'gu_name', 'lu_wiki', 'ln_wiki', 'gu_password', 'gu_auth_token',
				'gu_locked', 'gu_hidden_level', 'gu_registration', 'gu_email',
				'gu_email_authenticated', 'gu_home_db', 'gu_cas_token'
			],
			'conds' => [],
			'options' => [],
			'join_conds' => [
				'localuser' => [ 'LEFT JOIN', [ 'gu_name=lu_name', 'lu_wiki' => $wikiId ] ],
				'localnames' => [ 'LEFT JOIN', [ 'gu_name=ln_name', 'ln_wiki' => $wikiId ] ],
			],
		];
	}

	/**
	 * Get a CentralAuthUser object from a user's id
	 *
	 * @param int $id
	 * @return self|false false if no user exists with that id
	 */
	public static function newFromId( $id ) {
		$name = CentralAuthServices::getDatabaseManager()
			->getCentralReplicaDB()
			->newSelectQueryBuilder()
			->select( 'gu_name' )
			->from( 'globaluser' )
			->where( [ 'gu_id' => $id ] )
			->caller( __METHOD__ )
			->fetchField();

		return $name === false ? false : self::getInstanceByName( $name );
	}

	/**
	 * Get a primary CentralAuthUser object from a user's id
	 *
	 * @param int $id
	 * @return self|false false if no user exists with that id
	 * @since 1.37
	 */
	public static function newPrimaryInstanceFromId( $id ) {
		$name = CentralAuthServices::getDatabaseManager()
			->getCentralPrimaryDB()
			->newSelectQueryBuilder()
			->select( 'gu_name' )
			->from( 'globaluser' )
			->where( [ 'gu_id' => $id ] )
			->caller( __METHOD__ )
			->fetchField();

		return $name === false ? false : self::getPrimaryInstanceByName( $name );
	}

	/**
	 * Create a CentralAuthUser object from a joined globaluser/localuser row
	 *
	 * @param stdClass $row
	 * @param string[] $renameUser Empty if no rename is going on, else (oldname, newname)
	 * @param bool $fromPrimary
	 */
	public static function newFromRow( $row, $renameUser, $fromPrimary = false ): self {
		$caUser = new self( $row->gu_name );
		$caUser->loadFromRow( $row, $renameUser, $fromPrimary );
		return $caUser;
	}

	/**
	 * Create a CentralAuthUser object for a user who is known to be unattached.
	 * @param string $name A valid username. (Since 1.44 it does not have to be in the
	 *   canonical form anymore).
	 * @param bool $fromPrimary
	 */
	public static function newUnattached( $name, $fromPrimary = false ): self {
		$canonicalName = self::normalizeUsername( $name );
		$caUser = new self( $canonicalName );
		$caUser->loadFromRow( false, [], $fromPrimary );
		return $caUser;
	}

	/**
	 * Clear state information cache
	 * Does not clear $this->mName, so the state information can be reloaded with loadState()
	 */
	private function resetState() {
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
		} elseif ( $this->mGlobalId !== null ) {
			// Already loaded
			return;
		}

		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$tempUserConfig = MediaWikiServices::getInstance()->getTempUserConfig();
		if ( $userNameUtils->isIP( $this->mName )
			|| ( $tempUserConfig->isEnabled() && $this->mName === $tempUserConfig->getPlaceholderName() )
		) {
			$this->loadFromRow( false, [], true );
			return;
		}

		// Check the cache (unless the primary database was requested via READ_LATEST)
		if ( !$recache && $this->mFromPrimary !== true ) {
			$this->loadFromCache();
		} else {
			$this->loadFromDatabase();
		}
	}

	/**
	 * Load user groups and rights from the database.
	 *
	 * @param bool $force Set to true to load even when already loaded.
	 */
	private function loadGroups( bool $force = false ) {
		if ( $this->mGroups !== null && !$force ) {
			// Already loaded
			return;
		}
		$this->logger->debug(
			'Loading groups for global user {user}',
			[ 'user' => $this->mName ]
		);

		// We need the user id from the database, but this should be checked by the getId accessor.
		$db = $this->getSafeReadDB();

		// Grab the user's rights/groups.
		$userAndExpiryConds = [
			'gug_user' => $this->getId(),
			$db->expr( 'gug_expiry', '=', null )->or( 'gug_expiry', '>=', $db->timestamp() ),
		];

		$resGroups = $db->newSelectQueryBuilder()
			->select( [ 'gug_group', 'gug_expiry', 'ggr_set' ] )
			->from( 'global_user_groups' )
			->leftJoin( 'global_group_restrictions', null, 'ggr_group=gug_group' )
			->where( $userAndExpiryConds )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->mGroups = [];
		foreach ( $resGroups as $row ) {
			$this->mGroups[] = [ 'group' => $row->gug_group, 'expiry' => $row->gug_expiry, 'set' => $row->ggr_set ];
		}

		$resRights = $db->newSelectQueryBuilder()
			->select( [ 'ggp_permission', 'ggr_set' ] )
			->from( 'global_group_permissions' )
			->join( 'global_user_groups', null, 'ggp_group=gug_group' )
			->leftJoin( 'global_group_restrictions', null, 'ggr_group=gug_group' )
			->where( $userAndExpiryConds )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->mRights = [];
		foreach ( $resRights as $row ) {
			// Only store the set id here, and don't compute effective rights, because
			// this is stored in a shared cache for all wikis (see loadFromCache()),
			// which also isn't invalidated if the set is changed.
			$this->mRights[] = [ 'right' => $row->ggp_permission, 'set' => $row->ggr_set ];
		}
	}

	/**
	 * @return int|null Time when a global user group membership for this user will expire
	 * the next time in UNIX time, or null if this user has no temporary global group memberships.
	 */
	private function getClosestGlobalUserGroupExpiry(): ?int {
		$this->loadGroups();

		$closestExpiry = null;

		foreach ( $this->mGroups as [ 'expiry' => $expiration ] ) {
			if ( !$expiration ) {
				continue;
			}

			$expiration = wfTimestamp( TS_UNIX, $expiration );

			if ( $closestExpiry ) {
				$closestExpiry = min( $closestExpiry, $expiration );
			} else {
				$closestExpiry = $expiration;
			}
		}

		return $closestExpiry;
	}

	private function loadFromDatabase() {
		$this->logger->debug(
			'Loading state for global user {user} from DB',
			[ 'user' => $this->mName ]
		);

		$fromPrimary = $this->shouldUsePrimaryDB();
		// matches $fromPrimary above
		$db = $this->getSafeReadDB();

		$row = $db->newSelectQueryBuilder()
			->queryInfo( self::selectQueryInfo() )
			->where( [ 'gu_name' => $this->mName ] )
			->caller( __METHOD__ )
			->fetchRow();

		$renameUser = CentralAuthServices::getGlobalRenameFactory()
			->newGlobalRenameUserStatus( $this->mName )
			->getNames(
				null,
				$fromPrimary ? IDBAccessObject::READ_LATEST : IDBAccessObject::READ_NORMAL
			);

		$this->loadFromRow( $row, $renameUser, $fromPrimary );
	}

	/**
	 * Load user state from a joined globaluser/localuser row
	 *
	 * @param stdClass|bool $row
	 * @param string[] $renameUser Empty if no rename is going on, else (oldname, newname)
	 * @param bool $fromPrimary
	 */
	private function loadFromRow( $row, $renameUser, $fromPrimary = false ) {
		if ( $row ) {
			$this->mGlobalId = intval( $row->gu_id );
			$this->mIsAttached = ( $row->lu_wiki !== null );
			$this->mExistsLocally =
				property_exists( $row, 'ln_wiki' ) ? ( $row->ln_wiki !== null ) : null;
			$this->mPassword = $row->gu_password;
			$this->mAuthToken = $row->gu_auth_token;
			$this->mLocked = $row->gu_locked;
			$this->mHiddenLevel = (int)$row->gu_hidden_level;
			$this->mRegistration = wfTimestamp( TS_MW, $row->gu_registration );
			$this->mEmail = $row->gu_email;
			$this->mAuthenticationTimestamp =
				wfTimestampOrNull( TS_MW, $row->gu_email_authenticated );
			$this->mHomeWiki = $row->gu_home_db;
			$this->mCasToken = $row->gu_cas_token;
		} else {
			$this->mGlobalId = 0;
			$this->mIsAttached = false;
			$this->mExistsLocally = null;
			$this->mLocked = false;
			$this->mHiddenLevel = self::HIDDEN_LEVEL_NONE;
			$this->mCasToken = 0;
		}

		$this->mFromPrimary = $fromPrimary;

		$this->mBeingRenamedArray = $renameUser ?? [];
		$this->mBeingRenamed = implode( '|', $this->mBeingRenamedArray );
	}

	/**
	 * Load data from memcached
	 *
	 * @return bool
	 */
	private function loadFromCache() {
		$data = $this->wanCache->getWithSetCallback(
			$this->getCacheKey( $this->wanCache ),
			$this->wanCache::TTL_DAY,
			function ( $oldValue, &$ttl, array &$setOpts ) {
				$dbr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
				$setOpts += Database::getCacheSetOptions( $dbr );

				$this->loadFromDatabase();
				$this->loadAttached();
				$this->loadGroups();

				// if this user has global user groups expiring in less than the default TTL (1 day),
				// max out the TTL so that then-expired user groups will not be loaded from cache
				$closestGugExpiry = $this->getClosestGlobalUserGroupExpiry();
				if ( $closestGugExpiry ) {
					$ttl = min( $closestGugExpiry - time(), $ttl );
				}

				$data = [];
				foreach ( self::$mCacheVars as $var ) {
					$data[$var] = $this->$var;
				}

				return $data;
			},
			[ 'pcTTL' => $this->wanCache::TTL_PROC_LONG, 'version' => self::VERSION ]
		);

		$this->loadFromCacheObject( $data );

		return true;
	}

	/**
	 * Load user state from a cached array.
	 */
	private function loadFromCacheObject( array $object ) {
		$this->logger->debug(
			'Loading CentralAuthUser for user {user} from cache object',
			[ 'user' => $this->mName ]
		);

		foreach ( self::$mCacheVars as $var ) {
			$this->$var = $object[$var];
		}

		$this->loadAttached();

		$this->mIsAttached = $this->exists() && in_array( WikiMap::getCurrentWikiId(), $this->mAttachedArray );
		$this->mFromPrimary = false;

		$closestUserGroupExpiration = $this->getClosestGlobalUserGroupExpiry();
		if ( $closestUserGroupExpiration !== null && $closestUserGroupExpiration < time() ) {
			$this->logger->warning(
				'Cached user {user} had a global group expiration in the past '
					. '({unixTimestamp}), this should not be possible',
				[
					'user' => $this->getName(),
					'unixTimestamp' => $closestUserGroupExpiration,
				]
			);

			// load accurate data for this request from the database
			$this->loadGroups( true );

			// kill the current cache entry so that next request can use the cached value
			$this->quickInvalidateCache();
		}
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
		$wikiList = CentralAuthServices::getWikiListService()->getWikiList();
		if ( !in_array( $wikiId, $wikiList ) ) {
			return null;
		}
		// Retrieve the local user ID from the specified database.
		$db = CentralAuthServices::getDatabaseManager()->getLocalDB( DB_PRIMARY, $wikiId );
		$id = $db->newSelectQueryBuilder()
			->select( 'user_id' )
			->from( 'user' )
			->where( [ 'user_name' => $this->mName ] )
			->caller( __METHOD__ )
			->fetchField();
		return $id ? (int)$id : null;
	}

	/**
	 * Generate a valid memcached key for caching the object's data.
	 * @return string
	 */
	private function getCacheKey( WANObjectCache $cache ) {
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
		return $this->mPassword === null
			? PasswordFactory::newInvalidPassword()
			: $this->getPasswordFromString( $this->getPassword(), '' );
	}

	/**
	 * Return the global-login token for this account.
	 * @return string
	 */
	public function getAuthToken() {
		global $wgAuthenticationTokenVersion;

		$this->loadState();

		if ( !$this->mAuthToken ) {
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
		return (bool)$this->getId();
	}

	/**
	 * Check if a user with this name exists locally, whether or not it is attached
	 * @return bool
	 */
	public function existsLocally() {
		if ( $this->mExistsLocally === null ) {
			if ( $this->isAttached() ) {
				$this->mExistsLocally = true;
			} else {
				$db = CentralAuthServices::getDatabaseManager()->getLocalDB(
					$this->mFromPrimary ? DB_PRIMARY : DB_REPLICA,
					WikiMap::getCurrentWikiId()
				);
				$this->mExistsLocally = (bool)$db->newSelectQueryBuilder()
					->select( 'user_id' )
					->from( 'user' )
					->where( [ 'user_name' => $this->getName() ] )
					->caller( __METHOD__ )
					->fetchField();
			}
		}
		return $this->mExistsLocally;
	}

	/**
	 * Determine whether local existence status is loaded
	 *
	 * @return bool
	 */
	public function isLocalExistenceLoaded() {
		return $this->mIsAttached === true || $this->mExistsLocally !== null;
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
		return $this->mHiddenLevel !== self::HIDDEN_LEVEL_NONE;
	}

	/**
	 * Returns whether user's name should
	 * be hidden from all public views because
	 * of privacy issues.
	 * @return bool
	 */
	public function isSuppressed() {
		$this->loadState();
		return $this->mHiddenLevel == self::HIDDEN_LEVEL_SUPPRESSED;
	}

	/**
	 * Actually run the queries for getBlocks()
	 *
	 * @param array[] $wikis
	 * @return array<array<DatabaseBlock>>
	 * @throws LocalUserNotFoundException
	 */
	private function queryForBlocks( $wikis ) {
		$mwServices = MediaWikiServices::getInstance();
		$dbm = CentralAuthServices::getDatabaseManager();
		$blocksByWikiId = [];

		foreach ( $wikis as $wikiId => $_ ) {
			$db = $dbm->getLocalDB( DB_REPLICA, $wikiId );
			$fields = [ 'user_id' ];
			$conds = [ 'user_name' => $this->mName ];

			$row = $db->newSelectQueryBuilder()
				->select( $fields )
				->from( 'user' )
				->where( $conds )
				->caller( __METHOD__ )
				->fetchRow();
			if ( !$row ) {
				# Row missing from replica, try the primary database instead
				$db = $dbm->getLocalDB( DB_PRIMARY, $wikiId );
				$row = $db->newSelectQueryBuilder()
					->select( $fields )
					->from( 'user' )
					->where( $conds )
					->caller( __METHOD__ )
					->fetchRow();
			}
			if ( !$row ) {
				$ex = new LocalUserNotFoundException(
					'Could not find local user data for {username}@{wikiId}',
					[ 'username' => $this->mName, 'wikiId' => $wikiId ]
				);
				$this->logger->warning(
					'Could not find local user data for {username}@{wikiId}',
					[
						'username' => $this->mName,
						'wikiId' => $wikiId,
						'exception' => $ex,
					]
				);
				throw $ex;
			}
			// if the current wiki is the local wiki, then the BlockStore expect "WikiAwareEntity::LOCAL" as value;
			if ( $wikiId === WikiMap::getCurrentWikiId() ) {
				$wikiId = WikiAwareEntity::LOCAL;
			}

			$blockStore = $mwServices
				->getDatabaseBlockStoreFactory()
				->getDatabaseBlockStore( $wikiId );
			$blocksByWikiId[$wikiId] = $blockStore->newListFromConds(
				[
					'bt_user' => $row->user_id,
					// SECURITY: The user might not have permissions to view suppress blocks
					// on the foreign wiki (T400892).
					'bl_deleted' => 0,
				]
			);
		}

		return $blocksByWikiId;
	}

	/**
	 * Returns an array of blocks per wiki the user is attached to.
	 *
	 * @param array|null $wikis Should conform to the schema of ::queryAttachedBasic() as necessary.
	 *                          This is useful for testing, where ::queryAttachedBasic() may not work as expected,
	 *                          and should only be used in those cases. Regular callers shouldn't manually set this.
	 * @return array<array<DatabaseBlock>>
	 * @throws LocalUserNotFoundException
	 */
	public function getBlocks( ?array $wikis = null ): array {
		$getBlocksTimingStart = microtime( true );
		if ( !$wikis ) {
			$wikis = $this->queryAttachedBasic();
		}

		// If the user doesn't have a central id, there won't ever be a cache entry
		// Run the blocks queries and return the result
		$centralId = $this->getId();
		if ( !$centralId ) {
			// Record that we don't expect to interact with the cache
			$this->statsFactory->withComponent( 'CentralAuth' )
				->getCounter( 'centralauthuser_getblocks_cache' )
				->setLabel( 'interaction', 'never' )
				->increment();

			return $this->queryForBlocks( $wikis );
		}

		$cacheKey = $this->wanCache->makeGlobalKey(
			'centralauthuser-getblocks',
			$centralId
		);
		$blocksByWikiId = $this->wanCache->getWithSetCallback(
			$cacheKey,
			$this->wanCache::TTL_MONTH,
			function ( $oldValue ) use ( $wikis ) {
				if ( is_array( $oldValue ) ) {
					$oldWikis = array_keys( $oldValue );

					// Match queryForBlocks() adjustment of local wiki's id before sorting
					$newWikis = array_keys( $wikis );
					$localWiki = array_search( WikiMap::getCurrentWikiId(), $newWikis );
					if ( $localWiki !== false ) {
						$newWikis[ $localWiki ] = (int)WikiAwareEntity::LOCAL;
					}

					// If all wikis exist in the cache, use that value with adjustment
					// to match what a fresh query would return and reset the expiry.
					if (
						!count( array_diff( $oldWikis, $newWikis ) ) &&
						!count( array_diff( $newWikis, $oldWikis ) )
					) {
						// There isn't a hook for blocks expiring. Cached values need to
						// manually filter out for expired blocks to match with the fresh
						// value from queryForBlocks()
						$filteredBlocks = [];
						foreach ( $oldValue as $wikiId => $blocksOnWiki ) {
							$filteredBlocks[ $wikiId ] = [];

							foreach ( $blocksOnWiki as $block ) {
								if ( !$block->isExpired() ) {
									$filteredBlocks[ $wikiId ][] = $block;
								}
							}
						}

						// Record a cache hit
						$this->statsFactory->withComponent( 'CentralAuth' )
							->getCounter( 'centralauthuser_getblocks_cache' )
							->setLabel( 'interaction', 'hit' )
							->increment();

						return $filteredBlocks;
					}
				}
				// Record a cache miss
				$this->statsFactory->withComponent( 'CentralAuth' )
					->getCounter( 'centralauthuser_getblocks_cache' )
					->setLabel( 'interaction', 'miss' )
					->increment();

				// Otherwise cached value doesn't exist or the wikis are mismatched, rerun
				return $this->queryForBlocks( $wikis );
			},
			// Always run the callback
			[ 'minAsOf' => INF ]
		);

		// Time how long it takes to run this function
		$this->statsFactory->withComponent( 'CentralAuth' )
			->getTiming( 'centralauthuser_getblocks_timing_seconds' )
			->observe( ( microtime( true ) - $getBlocksTimingStart ) * 1000 );

		// Track the scale of the problem (more wikis, longer run time)
		$this->statsFactory->withComponent( 'CentralAuth' )
			->getGauge( 'centralauthuser_getblocks_wikis_count' )
			->set( ( count( $wikis ) ) );

		return $blocksByWikiId;
	}

	/**
	 * Returns true if the account has any blocks on any wikis.
	 * @throws LocalUserNotFoundException
	 */
	public function isBlocked(): bool {
		$blocksByWikiId = $this->getBlocks();

		foreach ( $blocksByWikiId as $wikiId => $blocks ) {
			if ( count( $blocks ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true if the account has any public logs.
	 *
	 * By default this method ignores 'newusers' logs as almost all users will
	 * have a log record of that type.
	 *
	 * @param array $excludeTypes is an array of log types to ignore
	 */
	public function hasPublicLogs( $excludeTypes = [ 'newusers' ] ): bool {
		$services = MediaWikiServices::getInstance();
		$dbm = CentralAuthServices::getDatabaseManager();

		$wikis = $this->queryAttachedBasic();
		foreach ( $wikis as $wikiId => $_ ) {
			$dbr = $dbm->getLocalDB( DB_REPLICA, $wikiId );

			$actorStore = $services->getActorStoreFactory()->getActorStore( $wikiId );
			$user = $actorStore->getUserIdentityByName( $this->getName() );
			if ( !$user ) {
				continue;
			}
			$actorId = $actorStore->acquireActorId( $user, $dbr );

			$conds = [
				'log_actor' => $actorId,
				$dbr->expr( 'log_type', '!=', $excludeTypes ),
			];
			$row = $dbr->newSelectQueryBuilder()
				->select( [ 'log_id' ] )
				->from( 'logging' )
				->where( $conds )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $row ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the hidden level of the account.
	 * @throws Exception for now
	 * @return never
	 * @deprecated use getHiddenLevelInt() instead
	 */
	public function getHiddenLevel(): int {
		// Have it like this for one train, then rename getHiddenLevelInt to this
		throw new BadMethodCallException( 'Nothing should call this!' );
	}

	/**
	 * Temporary name, will be getHiddenLevel() when migration is complete
	 * @return int one of self::HIDDEN_LEVEL_* constants
	 */
	public function getHiddenLevelInt(): int {
		$this->loadState();
		return $this->mHiddenLevel;
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
			// Make sure we always have some value
			$this->mHomeWiki = key( $attached );
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
			$this->mGlobalEditCount = CentralAuthServices::getEditCounter()
				->getCount( $this );
		}
		return $this->mGlobalEditCount;
	}

	/**
	 * Register a new, not previously existing, central user account
	 * Remaining fields are expected to be filled out shortly...
	 * eeeyuck
	 *
	 * @param string|null $password
	 * @param string $email
	 * @return bool
	 */
	public function register( $password, $email ) {
		$this->checkWriteMode();
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$hash = $this->saltedPassword( $password );
		if ( !$this->mAuthToken ) {
			$this->mAuthToken = MWCryptRand::generateHex( 32 );
		}

		$data = [
			'gu_name'  => $this->mName,

			'gu_email' => $email,
			'gu_email_authenticated' => null,

			'gu_password' => $hash,

			'gu_auth_token' => $this->mAuthToken,

			'gu_locked' => 0,
			'gu_hidden_level' => self::HIDDEN_LEVEL_NONE,

			'gu_registration' => $dbw->timestamp(),
		];

		$dbw->newInsertQueryBuilder()
			->insertInto( 'globaluser' )
			->ignore()
			->row( $data )
			->caller( __METHOD__ )
			->execute();

		$ok = $dbw->affectedRows() === 1;
		$this->logger->info(
			$ok
				? 'registered global account "{user}"'
				: 'registration failed for global account "{user}"',
			[ 'user' => $this->mName ]
		);

		if ( $ok ) {
			// Avoid lazy initialisation of edit count
			$dbw->newInsertQueryBuilder()
				->insertInto( 'global_edit_count' )
				->row( [
					'gec_user' => $dbw->insertId(),
					'gec_count' => 0
				] )
				->caller( __METHOD__ )
				->execute();
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
		if ( !$users ) {
			return;
		}

		$globalTuples = [];
		$tuples = [];
		foreach ( $users as $name ) {
			$globalTuples[] = [ 'gn_name' => $name ];
			$tuples[] = [
				'ln_wiki' => $wiki,
				'ln_name' => $name
			];
		}

		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'globalnames' )
			->ignore()
			->rows( $globalTuples )
			->caller( __METHOD__ )
			->execute();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'localnames' )
			->ignore()
			->rows( $tuples )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Store global user data in the auth server's main table.
	 *
	 * @param string $hash
	 * @param string $email
	 * @param string $emailAuth timestamp
	 * @return bool Whether we were successful or not.
	 */
	private function storeGlobalData( $hash, $email, $emailAuth ) {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$data = [
			'gu_name' => $this->mName,
			'gu_password' => $hash,
			// So it doesn't have to be done later
			'gu_auth_token' => MWCryptRand::generateHex( 32 ),
			'gu_email' => $email,
			'gu_email_authenticated' => $dbw->timestampOrNull( $emailAuth ),
			'gu_registration' => $dbw->timestamp(),
			'gu_locked' => 0,
			'gu_hidden_level' => self::HIDDEN_LEVEL_NONE,
		];

		$dbw->newInsertQueryBuilder()
			->insertInto( 'globaluser' )
			->ignore()
			->row( $data )
			->caller( __METHOD__ )
			->execute();

		$this->resetState();
		return $dbw->affectedRows() != 0;
	}

	/**
	 * @param string[] $passwords
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
	 * Record the current username in the AntiSpoof system
	 */
	private function recordAntiSpoof() {
		$spoof = CentralAuthServices::getAntiSpoofManager()->getSpoofUser( $this->mName );
		$spoof->record();
	}

	/**
	 * Remove the current username from the AntiSpoof system
	 */
	public function removeAntiSpoof() {
		$spoof = CentralAuthServices::getAntiSpoofManager()->getSpoofUser( $this->mName );
		$spoof->remove();
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
	 * @return string|null
	 */
	public function chooseHomeWiki( $migrationSet ) {
		if ( !$migrationSet ) {
			throw new LogicException( 'Logic error -- empty migration set in chooseHomeWiki' );
		}

		// Sysops get priority
		$found = [];
		$priorityGroups = [ 'checkuser', 'suppress', 'bureaucrat', 'sysop' ];
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
				if ( !$homeWiki || $migrationSet[$wiki]['registration'] <
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
	 * @param array[] $migrationSet
	 * @param string[] $passwords Optional, pre-authenticated passwords.
	 *     Should match an account which is known to be attached.
	 * @return string[] Array of <wiki> => <authentication method>
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
						$this->getPasswordFromString( $local['password'], $local['id'] )
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
				( isset( $passwordConfirmed[$wiki] ) && $passwordConfirmed[$wiki] ) ||
				( !isset( $passwordConfirmed[$wiki] ) &&
					$this->matchHashes(
						$passwords,
						$this->getPasswordFromString( $local['password'], $local['id'] )
					) )
			) {
				// Matches the pre-authenticated password, yay!
				$method = 'password';
			} else {
				// Can't automatically resolve this account.
				// If the password matches, it will be automigrated
				// at next login. If no match, user will have to input
				// the conflicting password or deal with the conflict.
				$this->logger->info( 'unresolvable {user}', [ 'user' => $localName ] );
				continue;
			}
			$this->logger->info( '$method {user}', [ 'user' => $localName ] );
			$attach[$wiki] = $method;
		}

		return $attach;
	}

	/**
	 * Do a dry run -- pick a winning primary account and try to auto-merge
	 * as many as possible, but don't perform any actions yet.
	 *
	 * @param string[] $passwords
	 * @param string|false &$home set to false if no permission to do checks
	 * @param array &$attached on success, list of wikis which will be auto-attached
	 * @param array &$unattached on success, list of wikis which won't be auto-attached
	 * @param array &$methods on success, associative array of each wiki's attachment method
	 * @return Status
	 */
	public function migrationDryRun( $passwords, &$home, &$attached, &$unattached, &$methods ) {
		// Because it messes with $this->mEmail and so on
		$this->checkWriteMode();

		$home = false;
		$attached = [];
		$unattached = [];

		// First, make sure we were given the current wiki's password.
		$self = $this->localUserData( WikiMap::getCurrentWikiId() );
		$selfPassword = $this->getPasswordFromString( $self['password'], $self['id'] );
		if ( !$this->matchHashes( $passwords, $selfPassword ) ) {
			$this->logger->info( 'dry run: failed self-password check' );
			return Status::newFatal( 'wrongpassword' );
		}

		$migrationSet = $this->queryUnattached();
		if ( !$migrationSet ) {
			$this->logger->info( 'dry run: no accounts to merge, failed migration' );
			return Status::newFatal( 'centralauth-merge-no-accounts' );
		}
		$home = $this->chooseHomeWiki( $migrationSet );
		$local = $migrationSet[$home];

		// And we need to match the home wiki before proceeding...
		$localPassword = $this->getPasswordFromString( $local['password'], $local['id'] );
		if ( $this->matchHashes( $passwords, $localPassword ) ) {
			$this->logger->info(
				'dry run: passed password match to home {home}',
				[ 'home' => $home ]
			);
		} else {
			$this->logger->info(
				'dry run: failed password match to home {home}',
				[ 'home' => $home ]
			);
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
	 * Pick a winning primary account and try to auto-merge as many as possible.
	 * @fixme add some locking or something
	 *
	 * @param string[] $passwords
	 * @param bool $sendToRC
	 * @param bool $safe Only migrate if all accounts can be merged
	 * @param bool $checkHome Re-check the user's ownership of the home wiki
	 * @return bool Whether full automatic migration completed successfully.
	 */
	private function attemptAutoMigration(
		$passwords = [], $sendToRC = true, $safe = false, $checkHome = false
	) {
		$this->checkWriteMode();
		$migrationSet = $this->queryUnattached();
		$logger = $this->logger;
		if ( !$migrationSet ) {
			$logger->info( 'no accounts to merge, failed migration' );
			return false;
		}

		if ( $this->mHomeWiki !== null ) {
			if ( !array_key_exists( $this->mHomeWiki, $migrationSet ) ) {
				$logger->info(
					'Invalid home wiki specification \'{user}@{home}\'',
					[ 'user' => $this->mName, 'home' => $this->mHomeWiki ]
				);
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
			$logger->info(
				'auto migrate: failed password match to home {home}',
				[ 'home' => $this->mHomeWiki ]
			);
			return false;
		}

		$this->mEmail = $home['email'];
		$this->mEmailAuthenticated = $home['emailAuthenticated'];

		// Pick all the local accounts matching the "primary" home account
		$attach = $this->prepareMigration( $migrationSet, $passwords );

		if ( $safe && count( $attach ) !== count( $migrationSet ) ) {
			$logger->info(
				'Safe auto-migration for \'{user}\' failed',
				[ 'user' => $this->mName ]
			);
			return false;
		}

		$wikisToAttach = array_intersect_key( $migrationSet, $attach );

		// The home wiki might not have an email set, but maybe an other account has one?
		$this->chooseEmail( $wikisToAttach );

		// storeGlobalData clears $this->mHomeWiki
		$homeWiki = $this->mHomeWiki;
		// Actually do the migration
		$ok = $this->storeGlobalData(
			$home['password'],
			$this->mEmail,
			$this->mEmailAuthenticated
		);

		if ( !$ok ) {
			$logger->info(
				'attemptedAutoMigration for existing entry \'{user}\'',
				[ 'user' => $this->mName ]
			);
			return false;
		}

		if ( count( $attach ) < count( $migrationSet ) ) {
			$logger->info(
				'Incomplete migration for \'{user}\'',
				[ 'user' => $this->mName ]
			);
		} else {
			if ( count( $migrationSet ) == 1 ) {
				$logger->info(
					'Singleton migration for \'{user}\' on {home}',
					[ 'user' => $this->mName, 'home' => $homeWiki ]
				);
			} else {
				$logger->info(
					'Full automatic migration for \'{user}\'',
					[ 'user' => $this->mName ]
				);
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
	 * @param string[] &$migrated Array of wiki IDs for records which were
	 *                  successfully migrated by this operation
	 * @param string[] &$remaining Array of wiki IDs for records which are still
	 *                   unattached after the operation
	 * @return bool true if all accounts are migrated at the end
	 */
	public function attemptPasswordMigration( $password, &$migrated = [], &$remaining = [] ) {
		$rows = $this->queryUnattached();
		$logger = $this->logger;

		if ( count( $rows ) == 0 ) {
			$logger->info(
				'Already fully migrated user \'{user}\'',
				[ 'user' => $this->mName ]
			);
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
				$this->getPasswordFromString( $row['password'], $row['id'] ) )
			) {
				$logger->info(
					'Attaching \'{user}\' on {wiki} by password',
					[
						'user' => $this->mName,
						'wiki' => $wiki
					]
				);
				$this->attach( $wiki, 'password' );
				$migrated[] = $wiki;
			} else {
				$logger->info(
					'No password match for \'{user}\' on {wiki}',
					[
						'user' => $this->mName,
						'wiki' => $wiki
					]
				);
				$remaining[] = $wiki;
			}
		}

		$this->endTransaction();

		if ( count( $remaining ) == 0 ) {
			$logger->info(
				'Successful auto migration for \'{user}\'',
				[ 'user' => $this->mName ]
			);
			return true;
		}

		$logger->info(
			'Incomplete migration for \'{user}\'',
			[ 'user' => $this->mName ]
		);
		return false;
	}

	/**
	 * @param string[] $list
	 * @return array An associative array containing the valid and invalid entries.
	 */
	private static function validateList( $list ) {
		$unique = array_unique( $list );
		$wikiList = CentralAuthServices::getWikiListService()->getWikiList();
		$valid = array_intersect( $unique, $wikiList );
		$invalid = array_diff( $unique, $valid );

		// Return valid and invalid entries
		return [ 'valid' => $valid, 'invalid' => $invalid ];
	}

	/**
	 * Unattach a list of local accounts from the global account
	 *
	 * NOTE: Keep in sync with bulk unattach in GlobalRenameUserDatabaseUpdates.
	 *
	 * @param array $list List of wiki names
	 * @return Status
	 */
	public function adminUnattach( $list ) {
		$this->checkWriteMode();
		if ( !count( $list ) ) {
			return Status::newFatal( 'centralauth-admin-none-selected' );
		}

		$status = new Status;

		// Get valid and invalid wikis
		[ 'valid' => $valid, 'invalid' => $invalid ] = $this->validateList( $list );

		// Handle invalid wikis
		foreach ( $invalid as $wikiName ) {
			$status->error( 'centralauth-invalid-wiki', $wikiName );
			$status->failCount++;
		}

		// Proceed with valid wikis
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbcw = $databaseManager->getCentralPrimaryDB();
		$password = $this->getPassword();

		foreach ( $valid as $wikiName ) {
			// Delete the user from the central localuser table
			$dbcw->newDeleteQueryBuilder()
				->deleteFrom( 'localuser' )
				->where( [
					'lu_name' => $this->mName,
					'lu_wiki' => $wikiName
				] )
				->caller( __METHOD__ )
				->execute();

			if ( !$dbcw->affectedRows() ) {
				$wiki = WikiMap::getWiki( $wikiName );
				$status->error( 'centralauth-admin-already-unmerged', $wiki->getDisplayName() );
				$status->failCount++;
				continue;
			}

			// Touch the local user row, update the password
			$dblw = $databaseManager->getLocalDB( DB_PRIMARY, $wikiName );
			$dblw->newUpdateQueryBuilder()
				->update( 'user' )
				->set( [
					'user_touched' => $dblw->timestamp(),
					'user_password' => $password
				] )
				->where( [ 'user_name' => $this->mName ] )
				->caller( __METHOD__ )
				->execute();

			$userRow = $dblw->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_editcount' ] )
				->from( 'user' )
				->where( [ 'user_name' => $this->mName ] )
				->caller( __METHOD__ )
				->fetchRow();

			// Remove the edits from the global edit count
			// These may be added back to the same (or a different) global user
			// in ::attach() by ::addLocalEdits()
			$counter = CentralAuthServices::getEditCounter();
			$counter->increment( $this, -(int)$userRow->user_editcount );
			$this->clearLocalUserCache( $wikiName, $userRow->user_id );
			$status->successCount++;
		}

		if ( in_array( WikiMap::getCurrentWikiId(), $valid ) ) {
			$this->resetState();
		}

		$this->invalidateCache();

		// Clear cache for CentralAuthUser::getLocalGroups()
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $cache->makeGlobalKey( 'centralauthuser-getlocalgroups', $this->getId() ) );

		return $status;
	}

	/**
	 * Queue a job to unattach this user from a named wiki.
	 *
	 * @param string $wikiId
	 */
	private function queueAdminUnattachJob( $wikiId ) {
		$services = MediaWikiServices::getInstance();

		$job = $services->getJobFactory()->newJob(
			'CentralAuthUnattachUserJob',
			[
				'username' => $this->getName(),
				'wiki' => $wikiId,
			]
		);

		$services->getJobQueueGroupFactory()->makeJobQueueGroup( $wikiId )->lazyPush( $job );
	}

	/**
	 * Delete a global account and log what happened
	 *
	 * @param string $reason Reason for the deletion
	 * @param UserIdentity $deleter User doing the deletion
	 * @return Status
	 */
	public function adminDelete( $reason, UserIdentity $deleter ) {
		$this->checkWriteMode();

		$this->logger->info(
			'Deleting global account for user \'{user}\'',
			[ 'user' => $this->mName ]
		);
		$databaseManager = CentralAuthServices::getDatabaseManager();
		$centralDB = $databaseManager->getCentralPrimaryDB();

		# Synchronise passwords
		$password = $this->getPassword();
		$localUserRes = $centralDB->newSelectQueryBuilder()
			->select( 'lu_wiki' )
			->from( 'localuser' )
			->where( [ 'lu_name' => $this->mName ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		foreach ( $localUserRes as $wiki ) {
			$this->logger->debug( __METHOD__ . ": Fixing password on $wiki\n" );
			$localDB = $databaseManager->getLocalDB( DB_PRIMARY, $wiki );
			$localDB->newUpdateQueryBuilder()
				->update( 'user' )
				->set( [ 'user_password' => $password ] )
				->where( [ 'user_name' => $this->mName ] )
				->caller( __METHOD__ )
				->execute();

			$id = $localDB->newSelectQueryBuilder()
				->select( 'user_id' )
				->from( 'user' )
				->where( [ 'user_name' => $this->mName ] )
				->caller( __METHOD__ )
				->fetchField();

			$this->clearLocalUserCache( $wiki, $id );
		}
		$wasSuppressed = $this->isSuppressed();

		$centralDB->startAtomic( __METHOD__ );
		# Delete and lock the globaluser row
		$centralDB->newDeleteQueryBuilder()
			->deleteFrom( 'globaluser' )
			->where( [ 'gu_name' => $this->mName ] )
			->caller( __METHOD__ )
			->execute();
		if ( !$centralDB->affectedRows() ) {
			$centralDB->endAtomic( __METHOD__ );
			return Status::newFatal( 'centralauth-admin-delete-nonexistent', $this->mName );
		}
		# Delete all global user groups for the user
		$centralDB->newDeleteQueryBuilder()
			->deleteFrom( 'global_user_groups' )
			->where( [ 'gug_user' => $this->getId() ] )
			->caller( __METHOD__ )
			->execute();
		# Delete the localuser rows
		$centralDB->newDeleteQueryBuilder()
			->deleteFrom( 'localuser' )
			->where( [ 'lu_name' => $this->mName ] )
			->caller( __METHOD__ )
			->execute();
		$centralDB->endAtomic( __METHOD__ );

		if ( $wasSuppressed ) {
			// "suppress/delete" is taken by core, so use "cadelete"
			$this->logAction(
				'cadelete',
				$deleter,
				$reason,
				[],
				true
			);
		} else {
			$this->logAction(
				'delete',
				$deleter,
				$reason,
				[],
			);
		}
		$idBeforeDeletion = $this->mGlobalId;
		$nameBeforeDeletion = $this->mName;
		$this->invalidateCache();
		$hookRunner = new CentralAuthHookRunner( MediaWikiServices::getInstance()->getHookContainer() );
		$hookRunner->onCentralAuthAccountDeleted( $idBeforeDeletion, $nameBeforeDeletion );

		return Status::newGood();
	}

	/**
	 * Lock a global account
	 *
	 * @return Status
	 */
	public function adminLock() {
		$this->checkWriteMode();
		$this->loadState();

		$services = MediaWikiServices::getInstance();
		if ( $services->getTempUserConfig()->isTempName( $this->mName ) ) {
			return Status::newFatal( 'centralauth-admin-cannot-lock-temporary-account' );
		}

		$newCasToken = $this->mCasToken + 1;

		$dbw = CentralAuthServices::getDatabaseManager( $services )->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [ 'gu_locked' => 1, 'gu_cas_token' => $newCasToken ] )
			->where( [
				'gu_name' => $this->mName,
				// Necessary so that the call to IDatabase::affectedRows will return 0 if no update was made.
				'gu_locked' => 0,
				'gu_cas_token' => $this->mCasToken,
			] )
			->caller( __METHOD__ )
			->execute();
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-state-mismatch' );
		}
		$this->mCasToken = $newCasToken;

		$this->invalidateCache();
		$user = User::newFromName( $this->mName );
		SessionManager::singleton()->invalidateSessionsForUser( $user );

		// T375870: Track rate of locks for monitoring of anti-abuse tool usage.
		$services->getStatsFactory()->withComponent( 'CentralAuth' )
			->getCounter( 'account_lock' )
			->increment();

		return Status::newGood();
	}

	/**
	 * Unlock a global account
	 *
	 * @return Status
	 */
	public function adminUnlock() {
		$this->checkWriteMode();
		$this->loadState();
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$newCasToken = $this->mCasToken + 1;
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [ 'gu_locked' => 0, 'gu_cas_token' => $newCasToken ] )
			->where( [
				'gu_name' => $this->mName,
				// Necessary so that the call to IDatabase::affectedRows will return 0 if no update was made.
				'gu_locked' => 1,
				'gu_cas_token' => $this->mCasToken,
			] )
			->caller( __METHOD__ )
			->execute();
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-state-mismatch' );
		}
		$this->mCasToken = $newCasToken;

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * Change account hiding level.
	 *
	 * @param int $level CentralAuthUser::HIDDEN_LEVEL_* class constant
	 * @return Status
	 */
	public function adminSetHidden( int $level ) {
		$this->checkWriteMode();
		$this->loadState();
		$newCasToken = $this->mCasToken + 1;

		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [ 'gu_hidden_level' => $level,
					 'gu_cas_token' => $newCasToken ] )
			->where( [
				'gu_name' => $this->mName,
				'gu_cas_token' => $this->mCasToken,
				// Necessary so that the call to IDatabase::affectedRows will return 0 if no update was made.
				$dbw->expr( 'gu_hidden_level', '!=', $level ),
			] )
			->caller( __METHOD__ )
			->execute();
		if ( !$dbw->affectedRows() ) {
			return Status::newFatal( 'centralauth-state-mismatch' );
		}
		$this->mCasToken = $newCasToken;

		$this->invalidateCache();
		$hookRunner = new CentralAuthHookRunner( MediaWikiServices::getInstance()->getHookContainer() );
		$hookRunner->onCentralAuthUserVisibilityChanged( $this, $level );

		return Status::newGood();
	}

	/**
	 * Set locking and hiding settings for a Global User and log the changes made.
	 *
	 * @param bool|null $setLocked
	 *  true = lock
	 *  false = unlock
	 *  null = don't change
	 * @param int|null $setHidden
	 *  hidden level, one of the HIDDEN_ constants
	 *  null = don't change
	 * @param string $reason reason for hiding
	 * @param IContextSource $context
	 * @param bool $markAsBot Whether to mark the log entry in RC with the bot flag
	 * @return Status
	 */
	public function adminLockHide(
		?bool $setLocked, ?int $setHidden, string $reason, IContextSource $context, bool $markAsBot = false
	) {
		$isLocked = $this->isLocked();
		$oldHiddenLevel = $this->getHiddenLevelInt();
		$lockStatus = null;
		$added = [];
		$removed = [];
		$user = $context->getUser();

		if ( $setLocked === null ) {
			$setLocked = $isLocked;
		} elseif ( !$context->getAuthority()->isAllowed( 'centralauth-lock' ) ) {
			return Status::newFatal( 'centralauth-admin-not-authorized' );
		}

		if ( $setHidden === null ) {
			$setHidden = $oldHiddenLevel;
		} elseif (
			$setHidden !== self::HIDDEN_LEVEL_NONE
			|| $oldHiddenLevel !== self::HIDDEN_LEVEL_NONE
		) {
			if ( !$context->getAuthority()->isAllowed( 'centralauth-suppress' ) ) {
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
			self::HIDDEN_LEVEL_NONE,
			self::HIDDEN_LEVEL_LISTS,
			self::HIDDEN_LEVEL_SUPPRESSED,
		];

		// if not a known value, default to none
		if ( !in_array( $setHidden, $hiddenLevels ) ) {
			$setHidden = self::HIDDEN_LEVEL_NONE;
		}

		if ( !$isLocked && $setLocked ) {
			$lockStatus = $this->adminLock();
			$added[] = 'locked';
		} elseif ( $isLocked && !$setLocked ) {
			$lockStatus = $this->adminUnlock();
			$removed[] = 'locked';
		}

		// Return early if the locked state of the user was requested to be changed, but this operation failed as we
		// do not want to perform any status change for the global account if any of the changes fail (T380421).
		if ( $lockStatus && !$lockStatus->isGood() ) {
			return $lockStatus;
		}

		if ( $oldHiddenLevel != $setHidden ) {
			$hideStatus = $this->adminSetHidden( $setHidden );

			// Return early if the locked state of the user was requested to be changed, but this operation failed
			// as we do not want to perform any status change for the global account if any of the changes fail
			// (T380421).
			if ( !$hideStatus->isGood() ) {
				// Undo any changes to the locked status of the global account if the suppression failed as we won't
				// be creating a log entry for the change.
				if ( !$isLocked && $setLocked ) {
					$this->adminUnlock();
				} elseif ( $isLocked && !$setLocked ) {
					$this->adminLock();
				}

				return $hideStatus;
			}

			switch ( $setHidden ) {
				case self::HIDDEN_LEVEL_NONE:
					$removed[] = $oldHiddenLevel === self::HIDDEN_LEVEL_SUPPRESSED ?
						'oversighted' :
						'hidden';
					break;
				case self::HIDDEN_LEVEL_LISTS:
					$added[] = 'hidden';
					if ( $oldHiddenLevel === self::HIDDEN_LEVEL_SUPPRESSED ) {
						$removed[] = 'oversighted';
					}
					break;
				case self::HIDDEN_LEVEL_SUPPRESSED:
					$added[] = 'oversighted';
					if ( $oldHiddenLevel === self::HIDDEN_LEVEL_LISTS ) {
						$removed[] = 'hidden';
					}
					break;
			}

			$userName = $user->getName();
			if ( $setHidden === self::HIDDEN_LEVEL_SUPPRESSED ) {
				$this->suppress( $userName, $reason );
			} elseif ( $oldHiddenLevel === self::HIDDEN_LEVEL_SUPPRESSED ) {
				$this->unsuppress( $userName, $reason );
			}
		}

		// Setup Status object to return all of the information for logging
		if ( count( $added ) || count( $removed ) ) {
			$returnStatus->successCount = count( $added ) + count( $removed );
			$this->logAction(
				'setstatus',
				$context->getUser(),
				$reason,
				[ 'added' => $added, 'removed' => $removed ],
				$setHidden !== self::HIDDEN_LEVEL_NONE,
				$markAsBot
			);
		}

		return $returnStatus;
	}

	/**
	 * Suppresses all user accounts in all wikis.
	 * @param string $name
	 * @param string $reason
	 */
	public function suppress( $name, $reason ) {
		$this->doCrosswikiSuppression( true, $name, $reason );
	}

	/**
	 * Unsuppresses all user accounts in all wikis.
	 * @param string $name
	 * @param string $reason
	 */
	public function unsuppress( $name, $reason ) {
		$this->doCrosswikiSuppression( false, $name, $reason );
	}

	/**
	 * @param bool $suppress
	 * @param string $by
	 * @param string $reason
	 */
	private function doCrosswikiSuppression( $suppress, $by, $reason ) {
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

			$services = MediaWikiServices::getInstance();
			$jobFactory = $services->getJobFactory();

			$chunks = array_chunk( $this->mAttachedArray, $wgCentralAuthWikisPerSuppressJob );
			foreach ( $chunks as $wikis ) {
				$jobParams['wikis'] = $wikis;
				$jobs[] = $jobFactory->newJob(
					'crosswikiSuppressUser',
					$jobParams
				);
			}

			// Push the jobs right before COMMIT (which is likely to succeed).
			// If the job push fails, then the transaction will roll back.
			$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
			$dbw->onTransactionPreCommitOrIdle( static function () use ( $services, $jobs ) {
				$services->getJobQueueGroup()->push( $jobs );
			}, __METHOD__ );
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

		$databaseManager = CentralAuthServices::getDatabaseManager();
		$dbw = $databaseManager->getLocalDB( DB_PRIMARY, $wiki );
		$data = $this->localUserData( $wiki );

		$wikiId = $wiki === WikiMap::getCurrentWikiId() ? WikiAwareEntity::LOCAL : $wiki;

		$blockStore = MediaWikiServices::getInstance()
			->getDatabaseBlockStoreFactory()
			->getDatabaseBlockStore( $wikiId );

		if ( $suppress ) {
			[ , $lang ] = $wgConf->siteFromDB( $wiki );
			if ( !MediaWikiServices::getInstance()->getLanguageNameUtils()->isSupportedLanguage( $lang ?? '' ) ) {
				$lang = 'en';
			}
			$blockReason = wfMessage( 'centralauth-admin-suppressreason', $by, $reason )
				->inLanguage( $lang )->text();

			$block = $blockStore->newUnsaved( [
				'targetUser' => UserIdentityValue::newRegistered( $data['id'], $this->mName, $wikiId ),
				'wiki' => $wikiId,
				'reason' => $blockReason,
				'timestamp' => wfTimestampNow(),
				'expiry' => $dbw->getInfinity(),
				'createAccount' => true,
				'enableAutoblock' => true,
				'hideName' => true,
				'blockEmail' => true,
				'by' => UserIdentityValue::newExternal(
					$wgCentralAuthGlobalBlockInterwikiPrefix, $by, $wikiId
				)
			] );

			# On normal block, BlockIp hook would be run here, but doing
			# that from CentralAuth doesn't seem a good idea...

			if ( !$blockStore->insertBlock( $block ) ) {
				return [ 'ipb_already_blocked' ];
			}
			# Ditto for BlockIpComplete hook.

			RevisionDeleteUser::suppressUserName( $this->mName, $data['id'], $dbw );

			# Locally log to suppress ?
		} else {
			$affected = $blockStore->deleteBlocksMatchingConds( [
				'bt_user' => $data['id'],
				// Our blocks don't have a user associated
				'bl_by' => null,
				'bl_deleted' => true,
			] );

			// Unsuppress only if unblocked
			if ( $affected ) {
				RevisionDeleteUser::unsuppressUserName( $this->mName, $data['id'], $dbw );
			}
		}
		return null;
	}

	/**
	 * Add a local account record for the given wiki to the central database.
	 * @param string $wikiID
	 * @param string $method Migration method. Will be displayed on Special:CentralAuth. One of:
	 *   - primary: This account existed before central accounts were introduced, and got
	 *     picked as the central account owner. (See {@link CentralAuthUser::chooseHomeWiki()}.)
	 *   - new: Created during signup (ie. the user registered when central accounts were already
	 *     enabled, and this is their first account).
	 *   - empty: Not used anymore - probably accounts with zero edits that got merged into the
	 *     global account by the same name, regardless of email etc?
	 *   - mail: Merged because it had the same email as the central account, or a connected
	 *     local account.
	 *   - password: Merged (on login or via Special:MergeAccount) because it had the same
	 *     password as the central account. (Or in some historical edge cases because it had no
	 *     password - T63876.)
	 *   - admin: Merged by an admin.
	 *   - login: Autocreated when the central user visited a wiki with no local account.
	 *   See also {@link \MediaWiki\Extension\CentralAuth\Special\SpecialCentralAuth::formatMergeMethod()}.
	 * @param bool $sendToRC Whether to send to the IRC recent changes feed
	 * @param string|int $ts MediaWiki timestamp or 0 for current time
	 *
	 * Prerequisites:
	 * - completed migration state
	 */
	public function attach( $wikiID, $method = 'new', $sendToRC = true, $ts = 0 ) {
		global $wgCentralAuthRC;

		$this->checkWriteMode();

		$dbcw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbcw->newInsertQueryBuilder()
			->insertInto( 'localuser' )
			->ignore()
			->row( [
				'lu_wiki'               => $wikiID,
				'lu_name'               => $this->mName,
				'lu_attached_timestamp' => $dbcw->timestamp( $ts ),
				'lu_attached_method'    => $method,
				'lu_local_id'           => $this->getLocalId( $wikiID ),
				'lu_global_id'          => $this->getId()
			] )
			->caller( __METHOD__ )
			->execute();
		$success = $dbcw->affectedRows() === 1;

		if ( $wikiID === WikiMap::getCurrentWikiId() ) {
			$this->resetState();
		}

		$this->invalidateCache();

		// Clear cache for CentralAuthUser::getLocalGroups()
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $cache->makeGlobalKey( 'centralauthuser-getlocalgroups', $this->getId() ) );

		if ( !$success ) {
			$this->logger->info(
				'Race condition? Already attached {user}@{wiki}, just tried by \'{method}\'',
				[ 'user' => $this->mName, 'wiki' => $wikiID, 'method' => $method ]
			);
			return;
		}

		$this->logger->info(
			'Attaching local user {user}@{wiki} by \'{method}\'',
			[ 'user' => $this->mName, 'wiki' => $wikiID, 'method' => $method ]
		);

		$this->addLocalEdits( $wikiID );

		if ( $sendToRC ) {
			$userpage = Title::makeTitleSafe( NS_USER, $this->mName );

			foreach ( $wgCentralAuthRC as $rc ) {
				$engine = RCFeed::factory( $rc );
				if ( !( $engine instanceof FormattedRCFeed ) ) {
					throw new RuntimeException(
						'wgCentralAuthRC only supports feeds that use FormattedRCFeed, got '
						. get_class( $engine ) . ' instead'
					);
				}

				/** @var CARCFeedFormatter $formatter */
				$formatter = new $rc['formatter']();

				$engine->send( $rc, $formatter->getLine( $userpage, $wikiID ) );
			}
		}
	}

	/**
	 * Add edits from a wiki to the global edit count
	 *
	 * @param string $wikiID
	 */
	protected function addLocalEdits( $wikiID ) {
		$dblw = CentralAuthServices::getDatabaseManager()->getLocalDB( DB_PRIMARY, $wikiID );
		$editCount = $dblw->newSelectQueryBuilder()
			->select( 'user_editcount' )
			->from( 'user' )
			->where( [ 'user_name' => $this->mName ] )
			->caller( __METHOD__ )
			->fetchField();
		$counter = CentralAuthServices::getEditCounter();
		$counter->increment( $this, $editCount );
	}

	/**
	 * If the user provides the correct password, would we let them log in?
	 * This encompasses checks on missing and locked accounts, at present.
	 * @return bool|string true if login available, or const authenticate status
	 */
	public function canAuthenticate() {
		if ( !$this->getId() ) {
			$this->logger->info(
				"authentication for '{user}' failed due to missing account",
				[ 'user' => $this->mName ]
			);
			return self::AUTHENTICATE_NO_USER;
		}

		// If the global account has been locked, we don't want to spam
		// other wikis with local account creations.
		if ( $this->isLocked() ) {
			return self::AUTHENTICATE_LOCKED;
		}

		// Don't allow users to autocreate if they are oversighted.
		// If they do, their name will appear on local user list
		// (and since it contains private info, its unacceptable).
		if ( $this->isSuppressed() ) {
			// Avoid unnecessary database connections by only loading the user
			// details if the account is suppressed, since that's a very small minority
			// of login attempts for non-locked users.
			$userIdentity = MediaWikiServices::getInstance()->getUserIdentityLookup()
				->getUserIdentityByName( $this->getName() );
			if ( !$userIdentity || !$userIdentity->isRegistered() ) {
				return self::AUTHENTICATE_LOCKED;
			}
		}

		return true;
	}

	/**
	 * Attempt to authenticate the global user account with the given password
	 * @param string $password
	 * @return string[] status represented by const(s) AUTHENTICATE_LOCKED,
	 *  AUTHENTICATE_NO_USER, AUTHENTICATE_BAD_PASSWORD
	 *  and AUTHENTICATE_OK
	 */
	public function authenticate( $password ) {
		$canAuthenticate = $this->canAuthenticate();
		if (
			$canAuthenticate !== true &&
			$canAuthenticate !== self::AUTHENTICATE_LOCKED
		) {
			return [ $canAuthenticate ];
		}

		$passwordObject = $this->getPasswordObject();
		$passwordMatches = $this->matchHash( $password, $passwordObject );

		// Log a few bits of the password to differentiate between broken bot sand brute-force attacks.
		$siteSecret = RequestContext::getMain()->getConfig()->get( 'SecretKey' );
		$passwordHashKey = 'random long arbitrary unique prefix' . $siteSecret . $this->mName . $this->mGlobalId;
		$passwordHashFragment = substr( MWCryptHash::hmac( $password, $passwordHashKey, false ), 0, 1 );
		$request = RequestContext::getMain()->getRequest();
		$logData = [
			'user' => $this->mName,
			'clientip' => $request->getIP(),
			'ua' => $request->getHeader( 'User-Agent' ),
			'passwordHashFragment' => $passwordHashFragment,
		];

		if ( $canAuthenticate === true ) {
			if ( $passwordMatches ) {
				$this->logger->info( "authentication for '{user}' succeeded", $logData );

				$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
				if ( $passwordFactory->needsUpdate( $passwordObject ) ) {
					DeferredUpdates::addCallableUpdate( function () use ( $password, $passwordObject ) {
						if ( CentralAuthServices::getDatabaseManager()->isReadOnly() ) {
							return;
						}

						$centralUser = self::newPrimaryInstanceFromId( $this->getId() );
						if ( $centralUser ) {
							$scope = LoggerFactory::getContext()->addScoped( [
								'context.passwordHashUpgrade_from' => $passwordObject->getType(),
							] );
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

				return [ self::AUTHENTICATE_OK ];
			} else {
				$this->logger->info( "authentication for '{user}' failed, bad pass", $logData );
				return [ self::AUTHENTICATE_BAD_PASSWORD ];
			}
		} else {
			if ( $passwordMatches ) {
				$this->logger->info( "authentication for '{user}' failed, correct pass but locked", $logData );
				return [ self::AUTHENTICATE_LOCKED ];
			} else {
				$this->logger->info( "authentication for '{user}' failed, locked with wrong password", $logData );
				return [ self::AUTHENTICATE_BAD_PASSWORD, self::AUTHENTICATE_LOCKED ];
			}
		}
	}

	/**
	 * Attempt to authenticate the global user account with the given global authtoken
	 * @param string $token
	 * @return string status, one of: AUTHENTICATE_LOCKED,
	 *  AUTHENTICATE_NO_USER, AUTHENTICATE_BAD_TOKEN
	 *  and AUTHENTICATE_OK
	 */
	public function authenticateWithToken( $token ) {
		$canAuthenticate = $this->canAuthenticate();
		if ( $canAuthenticate !== true ) {
			return $canAuthenticate;
		}

		return $this->validateAuthToken( $token ) ? self::AUTHENTICATE_OK : self::AUTHENTICATE_BAD_TOKEN;
	}

	/**
	 * @param string $plaintext User-provided password plaintext.
	 * @param Password $password Password to check against
	 */
	private function matchHash( $plaintext, Password $password ): bool {
		if ( $password->verify( $plaintext ) ) {
			return true;
		} elseif ( !( $password instanceof AbstractPbkdf2Password ) && function_exists( 'iconv' ) ) {
			// Some wikis were converted from ISO 8859-1 to UTF-8;
			// retained hashes may contain non-latin chars.
			AtEase::suppressWarnings();
			$latin1 = iconv( 'UTF-8', 'WINDOWS-1252//TRANSLIT', $plaintext );
			AtEase::restoreWarnings();
			if ( $latin1 !== false && $password->verify( $latin1 ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $passwords
	 * @param Password $password Password to check against
	 *
	 * @return bool
	 */
	private function matchHashes( array $passwords, Password $password ) {
		return array_any(
			$passwords,
			fn ( $plaintext ) => $this->matchHash( $plaintext, $password )
		);
	}

	/**
	 * @param string $encrypted Fully salted and hashed database crypto text from db.
	 * @param string $salt The hash "salt", eg a local id for migrated passwords. Not used
	 *   outside of migrations since T364435.
	 *
	 * @return Password
	 * @throws PasswordError
	 */
	private function getPasswordFromString( $encrypted, $salt ) {
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();

		if ( preg_match( '/^[0-9a-f]{32}$/', $encrypted ) ) {
			$encrypted = ":B:{$salt}:{$encrypted}";
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
		if ( IPUtils::isIPAddress( $this->mName ) ) {
			// don't bother with primary database queries
			return [];
		}

		return $this->doListUnattached();
	}

	/**
	 * @return string[]
	 */
	private function doListUnattached() {
		$databaseManager = CentralAuthServices::getDatabaseManager();
		// Make sure lazy-loading in listUnattached() works, as we
		// may need to *switch* to using the primary DB for this query
		$db = $databaseManager->centralLBHasRecentPrimaryChanges()
			? $databaseManager->getCentralPrimaryDB()
			: $this->getSafeReadDB();

		$result = $db->newSelectQueryBuilder()
			->select( 'ln_wiki' )
			->from( 'localnames' )
			->leftJoin( 'localuser', null, [ 'ln_wiki=lu_wiki', 'ln_name=lu_name' ] )
			->where( [ 'ln_name' => $this->mName, 'lu_name' => null ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$wikis = [];
		foreach ( $result as $wiki ) {
			if ( !WikiMap::getWiki( $wiki ) ) {
				$this->logger->warning( __METHOD__ . ': invalid wiki in localnames: ' . $wiki );
				continue;
			}

			$wikis[] = $wiki;
		}

		return $wikis;
	}

	/**
	 * @param string $wikiID
	 */
	public function addLocalName( $wikiID ) {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'localnames' )
			->ignore()
			->row( [
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $wikiID
	 */
	public function removeLocalName( $wikiID ) {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'localnames' )
			->where( [
				'ln_wiki' => $wikiID,
				'ln_name' => $this->mName
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Updates the localname table after a rename
	 * @param string $wikiID
	 * @param string $newname
	 */
	public function updateLocalName( $wikiID, $newname ) {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'localnames' )
			->set( [ 'ln_name' => $newname ] )
			->where( [ 'ln_wiki' => $wikiID, 'ln_name' => $this->mName ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Load the list of databases where this account has been successfully
	 * attached
	 */
	public function loadAttached() {
		if ( $this->mAttachedArray !== null ) {
			// Already loaded
			return;
		}

		if ( $this->mAttachedList !== null && $this->mAttachedList !== '' ) {
			// We have a list already, probably from the cache.
			$this->mAttachedArray = explode( "\n", $this->mAttachedList );
			return;
		}

		$this->logger->debug(
			"Loading attached wiki list for global user {user} from DB",
			[ 'user' => $this->mName ]
		);

		$db = $this->getSafeReadDB();

		$wikis = $db->newSelectQueryBuilder()
			->select( 'lu_wiki' )
			->from( 'localuser' )
			->where( [ 'lu_name' => $this->mName ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

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
	 * @see self::renameInProgress
	 * @param string $wiki
	 * @param int $recency Bitfield of IDBAccessObject::READ_* constants
	 * @return string[]|false
	 */
	public function renameInProgressOn( string $wiki, int $recency = IDBAccessObject::READ_NORMAL ) {
		return CentralAuthServices::getGlobalRenameFactory()
			->newGlobalRenameUserStatus( $this->mName )
			->getNames(
				$wiki,
				$recency
			) ?: false;
	}

	/**
	 * Check if a rename from the old name is in progress
	 * @return string[] (oldname, newname) if being renamed, or empty if not
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
	 * @return string[] List of group names where the user is a member on at least one wiki
	 */
	public function getLocalGroups() {
		// Cache is invalidated in onUserGroupsChanged() hook handler, so we can use long TTL
		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeGlobalKey( 'centralauthuser-getlocalgroups', $this->getId() ),
			$this->wanCache::TTL_MONTH,
			function () {
				$localgroups = [];
				foreach ( $this->queryAttached() as $local ) {
					$localgroups = array_unique( array_merge(
						$localgroups, array_keys( $local['groupMemberships'] )
					) );
				}
				return $localgroups;
			}
		);
	}

	/**
	 * Get information about each local user attached to this account
	 *
	 * @return array[] Map of database name to property table with members:
	 *    wiki                  The wiki ID (database name)
	 *    attachedTimestamp     The MW timestamp when the account was attached
	 *    attachedMethod        Attach method: see {@link CentralAuthUser::attach()}.
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
			} catch ( LocalUserNotFoundException ) {
				// T119736: localuser table told us that the user was attached
				// from $wikiId but there is no data in the primary database or replicas
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
	 * @return array[]
	 */
	public function queryAttachedBasic() {
		if ( $this->mAttachedInfo !== null ) {
			return $this->mAttachedInfo;
		}

		$db = $this->getSafeReadDB();

		$result = $db->newSelectQueryBuilder()
			->select( [
				'lu_wiki',
				'lu_attached_timestamp',
				'lu_attached_method',
			] )
			->from( 'localuser' )
			->where( [ 'lu_name' => $this->mName, ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$wikis = [];
		foreach ( $result as $row ) {
			/** @var stdClass $row */

			if ( !WikiMap::getWiki( $row->lu_wiki ) ) {
				$this->logger->warning( __METHOD__ . ': invalid wiki in localuser: ' . $row->lu_wiki );
				continue;
			}

			$wikis[$row->lu_wiki] = [
				'wiki' => $row->lu_wiki,
				'attachedTimestamp' => wfTimestampOrNull( TS_MW, $row->lu_attached_timestamp ),
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
	 * @return array[]
	 */
	public function queryUnattached() {
		$wikiIDs = $this->listUnattached();

		$items = [];
		foreach ( $wikiIDs as $wikiID ) {
			try {
				$items[$wikiID] = $this->localUserData( $wikiID );
			} catch ( LocalUserNotFoundException ) {
				// T119736: localnames table told us that the name was
				// unattached on $wikiId but there is no data in the primary database
				// or replicas that corroborates that.
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
	private function localUserData( $wikiID ) {
		$mwServices = MediaWikiServices::getInstance();
		$databaseManager = CentralAuthServices::getDatabaseManager();

		$knownDBs = $mwServices->getMainConfig()->get( 'LocalDatabases' );
		// (T391511) Don't explode if the wiki isn't found, but treat it as "user not found" and warn for clean-up
		if ( !WikiMap::isCurrentWikiDbDomain( $wikiID ) && !in_array( $wikiID, $knownDBs ) ) {
			$this->logger->warning(
				"Requested to load user data for unknown wiki {wikiId}. Was it deleted without data migration?",
				[
					'exception' => new RuntimeException(),
					'wikiId' => $wikiID
				]
			);
			throw new LocalUserNotFoundException( 'Could not find {wikiId}', [ 'wikiId' => $wikiID ] );
		}

		$db = $databaseManager->getLocalDB( DB_REPLICA, $wikiID );
		$fields = [
			'user_id',
			'user_email',
			'user_name',
			'user_email_authenticated',
			'user_password',
			'user_editcount',
			'user_registration',
		];
		$conds = [ 'user_name' => $this->mName ];
		$row = $db->newSelectQueryBuilder()
			->select( $fields )
			->from( 'user' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			# Row missing from replica, try the primary database instead
			$db = $databaseManager->getLocalDB( DB_PRIMARY, $wikiID );
			$row = $db->newSelectQueryBuilder()
				->select( $fields )
				->from( 'user' )
				->where( $conds )
				->caller( __METHOD__ )
				->fetchRow();
		}
		if ( !$row ) {
			// temporary hack for T385310: break through repeatable read snapshots
			$seenWithLock = (bool)$db->newSelectQueryBuilder()
				->select( $fields )
				->from( 'user' )
				->where( $conds )
				->lockInShareMode()
				->caller( __METHOD__ )
				->fetchRow();
			$ex = new LocalUserNotFoundException(
				'Could not find local user data for {username}@{wikiId}',
				[ 'username' => $this->mName, 'wikiId' => $wikiID ]
			);
			$this->logger->warning(
				'Could not find local user data for {username}@{wikiId}',
				[
					'username' => $this->mName,
					'wikiId' => $wikiID,
					'exception' => $ex,
					'T385310_uses_primary_ca' => $this->shouldUsePrimaryDB(),
					'T385310_seenWithLock' => $seenWithLock,
				]
			);
			throw $ex;
		}

		$data = [
			'wiki' => $wikiID,
			'id' => $row->user_id,
			'name' => $row->user_name,
			'email' => $row->user_email,
			'emailAuthenticated' => wfTimestampOrNull( TS_MW, $row->user_email_authenticated ),
			'registration' => wfTimestampOrNull( TS_MW, $row->user_registration ),
			'password' => $row->user_password,
			'editCount' => $row->user_editcount,
			// array of (group name => UserGroupMembership object)
			'groupMemberships' => [],
			'blocked' => false,
		];

		// Edit count field may not be initialized...
		if ( $row->user_editcount === null ) {
			$data['editCount'] = $db->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'revision' )
				->where( [ 'actor_user' => $data['id'] ] )
				->join( 'actor', null, 'actor_id = rev_actor' )
				->caller( __METHOD__ )
				->fetchField();
		}

		// And we have to fetch groups separately, sigh...
		$data['groupMemberships'] = $mwServices
			->getUserGroupManagerFactory()
			->getUserGroupManager( $wikiID )
			->getUserGroupMemberships(
				new UserIdentityValue(
					(int)$data['id'],
					$data['name'],
					$wikiID === WikiMap::getCurrentWikiId() ? UserIdentity::LOCAL : $wikiID
				)
			);

		// And while we're in here, look for user blocks :D
		$blockStore = $mwServices
			->getDatabaseBlockStoreFactory()
			->getDatabaseBlockStore( $wikiID );
		$blocks = $blockStore->newListFromConds( [ 'bt_user' => $data['id'] ] );
		foreach ( $blocks as $block ) {
			$data['block-expiry'] = $block->getExpiry();
			$data['block-reason'] = $block->getReasonComment()->text;
			$data['block-anononly'] = !$block->isHardblock();
			$data['block-nocreate'] = $block->isCreateAccountBlocked();
			$data['block-noautoblock'] = !$block->isAutoblocking();
			// Poorly named database column
			$data['block-nousertalk'] = !$block->isUsertalkEditAllowed();
			$data['block-noemail'] = $block->isEmailBlocked();
			$data['block-sitewide'] = $block->isSitewide();
			$data['block-restrictions'] = $block->getRestrictions();
			$data['blocked'] = true;
		}

		return $data;
	}

	public function getEmail(): string {
		$this->loadState();
		return $this->mEmail ?? '';
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
	 * @param string|null $ts
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
	 * @param string|null $password plaintext
	 * @return string Password in the form :<TYPE>:<PARAM1>:<PARAM2>:...:<HASH>
	 */
	private function saltedPassword( $password ) {
		$passwordFactory = MediaWikiServices::getInstance()->getPasswordFactory();
		return $passwordFactory->newFromPlaintext( $password )->toString();
	}

	/**
	 * Set the account's password
	 * @param string|null $password plaintext
	 * @param bool $resetAuthToken if we should reset the login token
	 * @return bool true
	 * @throws NormalizedException
	 */
	public function setPassword( $password, $resetAuthToken = true ) {
		$this->checkWriteMode();

		// Make sure state is loaded before updating ->mPassword
		$this->loadState();

		$hash = $this->saltedPassword( $password );

		$this->mPassword = $hash;

		if ( $this->getId() ) {
			$newCasToken = $this->mCasToken + 1;
			$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
			$dbw->newUpdateQueryBuilder()
				->update( 'globaluser' )
				->set( [ 'gu_password' => $hash, 'gu_cas_token' => $newCasToken ] )
				->where( [ 'gu_id' => $this->getId(), 'gu_cas_token' => $this->mCasToken ] )
				->caller( __METHOD__ )
				->execute();
			$success = ( $dbw->affectedRows() > 0 );

			if ( $success ) {
				$this->logger->info( "Set global password for {user}", [ 'user' => $this->mName ] );
				$this->mCasToken = $newCasToken;
			} else {
				throw new NormalizedException( "Attempted to set global password for {user} but" .
					" the expected CAS token doesn't match. Possible race condition!", [ 'user' => $this->mName ] );
			}
		} else {
			$this->logger->warning( "Tried changing password for user that doesn't exist {user}",
				[ 'user' => $this->mName ] );
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
		return $this->mPassword ?? '';
	}

	private static function getSessionProvider(): CentralAuthSessionProvider {
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return SessionManager::singleton()->getProvider( CentralAuthSessionProvider::class );
	}

	/**
	 * Get the domain parameter for setting a global cookie.
	 * This allows other extensions to easily set global cookies without directly relying on
	 * $wgCentralAuthCookieDomain (in case CentralAuth's implementation changes at some point).
	 *
	 * @return string
	 */
	public static function getCookieDomain() {
		$provider = self::getSessionProvider();
		return $provider->getCentralCookieDomain();
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

		$databaseManager = CentralAuthServices::getDatabaseManager();
		if ( $databaseManager->isReadOnly() ) {
			return;
		}

		$this->loadState();
		if ( !$this->mGlobalId ) {
			return;
		}

		$newCasToken = $this->mCasToken + 1;

		$dbw = $databaseManager->getCentralPrimaryDB();

		$toSet = [
			'gu_password' => $this->mPassword,
			'gu_auth_token' => $this->mAuthToken,
			'gu_locked' => $this->mLocked,
			'gu_hidden_level' => $this->getHiddenLevelInt(),
			'gu_email' => $this->mEmail,
			'gu_email_authenticated' =>
				$dbw->timestampOrNull( $this->mAuthenticationTimestamp ),
			'gu_home_db' => $this->getHomeWiki(),
			'gu_cas_token' => $newCasToken
		];

		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( $toSet )
			->where( [
				'gu_id' => $this->mGlobalId,
				'gu_cas_token' => $this->mCasToken
			] )
			->caller( __METHOD__ )
			->execute();

		if ( !$dbw->affectedRows() ) {
			// Maybe the problem was a missed cache update; clear it to be safe
			$this->invalidateCache();
			// User was changed in the meantime or loaded with stale data
			$from = ( $this->mFromPrimary ) ? 'primary' : 'replica';
			$this->logger->warning(
				"CAS update failed on gu_cas_token for user ID '{globalId}' " .
				"(read from {from}); the version of the user to be saved is older than " .
				"the current version.",
				[
					'globalId' => $this->mGlobalId,
					'from' => $from,
					'exception' => new RuntimeException,
				]
			);
			return;
		}

		$this->mCasToken = $newCasToken;
		$this->invalidateCache();
	}

	/**
	 * @return string[]
	 */
	public function getGlobalGroups() {
		return array_keys( $this->getGlobalGroupsWithExpiration() );
	}

	/**
	 * @return array<string,?string> of [group name => expiration timestamp or null if permanent]
	 */
	public function getGlobalGroupsWithExpiration() {
		$this->loadGroups();

		$groupExpirations = [];
		foreach ( $this->mGroups as [ 'group' => $group, 'expiry' => $expiration ] ) {
			$groupExpirations[ $group ] = $expiration;
		}
		ksort( $groupExpirations );

		return $groupExpirations;
	}

	/**
	 * Return the user's groups that are active on the current wiki (according to WikiSet settings).
	 *
	 * @return string[]
	 */
	public function getActiveGlobalGroups() {
		$this->loadGroups();

		$groups = [];
		$sets = [];
		foreach ( $this->mGroups as [ 'group' => $group, 'set' => $setId ] ) {
			if ( $setId ) {
				$sets[$setId] ??= WikiSet::newFromID( $setId );
				if ( !$sets[$setId]->inSet() ) {
					continue;
				}
			}
			$groups[] = $group;
		}
		sort( $groups );
		return $groups;
	}

	/**
	 * @return string[]
	 */
	public function getGlobalRights() {
		$this->loadGroups();

		$rights = [];
		$sets = [];
		foreach ( $this->mRights as [ 'right' => $right, 'set' => $setId ] ) {
			if ( $setId ) {
				$sets[$setId] ??= WikiSet::newFromID( $setId );
				if ( !$sets[$setId]->inSet() ) {
					continue;
				}
			}
			$rights[] = $right;
		}
		return $rights;
	}

	/**
	 * Removes global group(s) from a CentralAuth user. Does not cause a log entry.
	 *
	 * @param string|string[] $groups The global group or group(s)
	 * @return void
	 */
	public function removeFromGlobalGroups( $groups ) {
		$this->checkWriteMode();
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		# Delete from the DB
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'global_user_groups' )
			->where( [ 'gug_user' => $this->getId(), 'gug_group' => $groups ] )
			->caller( __METHOD__ )
			->execute();

		$this->invalidateCache();
	}

	/**
	 * Adds a CentralAuth user to a given global group. Does not cause a log entry.
	 *
	 * @param string $group The global group
	 * @param string|null $expiry Timestamp of membership expiry in TS_MW format, or null if no expiry
	 */
	public function addToGlobalGroup( string $group, ?string $expiry = null ): Status {
		$this->checkWriteMode();
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();

		$services = MediaWikiServices::getInstance();
		if ( $services->getTempUserConfig()->isTempName( $this->mName ) ) {
			return Status::newFatal( 'centralauth-admin-cannot-lock-temporary-account' );
		}

		// Replace into the DB so any updates to the membership expiry take effect.
		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'global_user_groups' )
			->uniqueIndexFields( [ 'gug_user', 'gug_group' ] )
			->row( [
				'gug_user' => $this->getId(),
				'gug_group' => $group,
				'gug_expiry' => $dbw->timestampOrNull( $expiry )
			] )
			->caller( __METHOD__ )
			->execute();

		$this->invalidateCache();

		return Status::newGood();
	}

	/**
	 * @param string $perm
	 * @return bool
	 */
	public function hasGlobalPermission( $perm ) {
		return in_array( $perm, $this->getGlobalRights() );
	}

	public function invalidateCache() {
		if ( !$this->mDelayInvalidation ) {
			$this->logger->debug(
				"Updating cache for global user {user}",
				[ 'user' => $this->mName ]
			);
			// Purge the cache
			$this->quickInvalidateCache();
			// Reload the state
			$this->loadStateNoCache();
		} else {
			$this->logger->debug( "Deferring cache invalidation because we're in a transaction" );
		}
	}

	/**
	 * For when speed is of the essence (e.g. when batch-purging users after rights changes)
	 */
	public function quickInvalidateCache() {
		$this->logger->debug(
			"Quick cache invalidation for global user {user}",
			[ 'user' => $this->mName ]
		);

		CentralAuthServices::getDatabaseManager()
			->getCentralPrimaryDB()
			->onTransactionPreCommitOrIdle( function () {
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$cache->delete( $this->getCacheKey( $cache ) );
			}, __METHOD__ );
	}

	/**
	 * End a "transaction".
	 * A transaction delays cache invalidation until after
	 * some operation which would otherwise repeatedly do so.
	 * Intended to be used for things like migration.
	 */
	public function endTransaction() {
		$this->logger->debug( 'End CentralAuthUser cache-invalidating transaction' );
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
		$this->logger->debug( 'Start CentralAuthUser cache-invalidating transaction' );
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
	public function getStateHash( bool $recache = false ) {
		$this->loadState( $recache );

		return md5( $this->mGlobalId . ':' . $this->mName . ':' . $this->mHiddenLevel . ':' .
			( $this->mLocked ? '1' : '0' ) );
	}

	/**
	 * Log an action for the current user
	 *
	 * @param string $action
	 * @param UserIdentity $user
	 * @param string $reason
	 * @param array $params
	 * @param bool $suppressLog
	 * @param bool $markAsBot If true, log entry is marked as made by a bot. If false, default
	 * behavior is observed.
	 */
	public function logAction(
		$action,
		UserIdentity $user,
		$reason = '',
		$params = [],
		bool $suppressLog = false,
		bool $markAsBot = false
	) {
		$nsUser = MediaWikiServices::getInstance()
			->getNamespaceInfo()
			->getCanonicalName( NS_USER );
		// Not centralauth because of some weird length limitations
		$logType = $suppressLog ? 'suppress' : 'globalauth';
		// The following messages are generated here:
		// * logentry-globalauth-cadelete
		// * logentry-globalauth-delete
		// * logentry-globalauth-setstatus
		// * logentry-suppress-cadelete
		// * logentry-suppress-delete
		// * logentry-suppress-setstatus
		// * log-action-filter-globalauth-cadelete
		// * log-action-filter-globalauth-delete
		// * log-action-filter-globalauth-setstatus
		// * log-action-filter-suppress-cadelete
		// * log-action-filter-suppress-delete
		// * log-action-filter-suppress-setstatus
		$entry = new ManualLogEntry( $logType, $action );
		$entry->setTarget( Title::newFromText( "$nsUser:{$this->mName}@global" ) );
		$entry->setPerformer( $user );
		$entry->setComment( $reason );
		$entry->setParameters( $params );
		if ( $markAsBot ) {
			// NOTE: This is intentionally called conditionally, to respect default behavior when
			// $markAsBot is set to false.
			$entry->setForceBotFlag( $markAsBot );
		}
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * @param string $wikiId
	 * @param int $userId
	 */
	private function clearLocalUserCache( $wikiId, $userId ) {
		User::purge( $wikiId, $userId );
	}

	/**
	 * True if the user's password has been scrambled with scramblePassword().
	 */
	public function hasScrambledPassword(): bool {
		return $this->getPasswordObject() instanceof ScrambledPassword;
	}

	/**
	 * Returns the reason given as the --task parameter of ScramblePassword.php.
	 * Returns null if the user doesn't have a scrambled password, or it's corrupted somehow.
	 */
	public function getScrambledPasswordReason(): ?string {
		return $this->hasScrambledPassword() ? ( explode( ':', $this->getPassword() )[2] ?? null ) : null;
	}

	/**
	 * For a password scrambled with scramblePassword(), returns the Password object from before
	 * the scrambling. Returns an InvalidPassword if something goes wrong (e.g. the password is
	 * not actually scrambled).
	 */
	public function getScrambledPasswordOriginalPasswordObject(): Password {
		if ( !$this->hasScrambledPassword() ) {
			return PasswordFactory::newInvalidPassword();
		}

		// hash format is ':scrambled:<reason>:<original-hash>'
		$originalPasswordString = explode( ':', $this->getPassword(), 4 )[3] ?? null;
		if ( $originalPasswordString !== null ) {
			$originalPasswordString = ':' . $originalPasswordString;
		}
		return MediaWikiServices::getInstance()->getPasswordFactory()->newFromCiphertext( $originalPasswordString );
	}

	/**
	 * Replace the user's password with an invalid one in a reversible manner.
	 * It is the caller's responsibility to invalidate sessions.
	 * @param string $reason The reason for the scrambling, typically a Phabricator task ID.
	 *   Must be a single ASCII alphanumeric word.
	 * @return bool Success flag. False could mean the account does not exist or the password
	 *   has already been scrambled.
	 * @throws NormalizedException
	 */
	public function scramblePassword( string $reason ): bool {
		if ( !preg_match( '/^[a-zA-Z0-9]+$/', $reason ) ) {
			throw new DomainException( '$reason must be ASCII alphanumeric, got: ' . $reason );
		} elseif (
			$this->getPasswordObject() instanceof InvalidPassword
			|| $this->getPasswordObject() instanceof ScrambledPassword
		) {
			return false;
		}

		// Make sure old-style password hashes get converted, we'll rely on the hash starting with ':'.
		$this->checkWriteMode();
		$newCasToken = $this->mCasToken + 1;
		$originalPasswordHash = $this->getPasswordObject()->toString();
		$scrambledPasswordHash = ":scrambled:$reason$originalPasswordHash";

		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [ 'gu_password' => $scrambledPasswordHash, 'gu_cas_token' => $newCasToken ] )
			->where( [ 'gu_id' => $this->getId(), 'gu_cas_token' => $this->mCasToken ] )
			->caller( __METHOD__ )
			->execute();
		$success = ( $dbw->affectedRows() > 0 );
		if ( $success ) {
			$this->mCasToken = $newCasToken;
		} else {
			throw new NormalizedException( "User password for {user} was NOT scrambled," .
				 " possibly due to a mismatch in the CAS token", [ 'user' => $this->mName ] );
		}

		$this->invalidateCache();
		return $success;
	}

	/**
	 * Undo an earlier scramblePassword() call.
	 * @param string $reason Must be exactly the same as the reason given for scramblePassword().
	 * @return bool Success flag. False could mean the account does not exist or the password
	 *   wasn't scrambled or was scrambled with a different reason.
	 */
	public function unscramblePassword( string $reason ): bool {
		$this->checkWriteMode();
		$scrambledPasswordHash = $this->getPassword();
		if ( !str_starts_with( $scrambledPasswordHash, ":scrambled:$reason:" ) ) {
			return false;
		}
		$originalPasswordHash = substr( $scrambledPasswordHash, strlen( ":scrambled:$reason" ) );

		$newCasToken = $this->mCasToken + 1;
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		$dbw->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [ 'gu_password' => $originalPasswordHash, 'gu_cas_token' => $newCasToken ] )
			->where( [ 'gu_id' => $this->getId(), 'gu_cas_token' => $this->mCasToken ] )
			->caller( __METHOD__ )
			->execute();
		$success = ( $dbw->affectedRows() > 0 );
		if ( $success ) {
			$this->mCasToken = $newCasToken;
		}

		$this->invalidateCache();
		return $success;
	}

}
