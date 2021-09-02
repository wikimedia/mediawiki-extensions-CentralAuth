<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;

class WikiSet {
	public const OPTIN = 'optin';
	public const OPTOUT = 'optout';

	private const VERSION = 1;

	/** @var int ID of the group */
	private $mId;
	/** @var string Display name of the group */
	private $mName;
	/** @var string Opt-in based or opt-out based */
	private $mType;
	/** @var string[] List of wikis */
	private $mWikis;

	/** @var string[] */
	private static $mCacheVars = [
		'mId',
		'mName',
		'mType',
		'mWikis',
	];

	/**
	 * @param string $name
	 * @param string $type
	 * @param string[] $wikis
	 * @param int $id
	 */
	public function __construct( $name = '', $type = self::OPTIN, $wikis = [], $id = 0 ) {
		$this->mId = $id;
		$this->mName = $name;
		$this->mType = $type;
		$this->mWikis = $wikis;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->mId;
	}

	/**
	 * @return bool
	 */
	public function exists() {
		return (bool)$this->getID();
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->mName;
	}

	/**
	 * @param string $name
	 */
	public function setName( $name ) {
		$this->mName = $name;
	}

	/**
	 * @return string[]
	 */
	public function getWikisRaw() {
		return $this->mWikis;
	}

	/**
	 * @param string[] $wikis
	 */
	public function setWikisRaw( $wikis ) {
		$this->mWikis = $wikis;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->mType;
	}

	/**
	 * @param string $type
	 */
	public function setType( $type ) {
		if ( !in_array( $type, [ self::OPTIN, self::OPTOUT ] ) ) {
			return;
		}
		$this->mType = $type;
	}

	/**
	 * @param stdClass|bool $row
	 * @return null|WikiSet
	 */
	public static function newFromRow( $row ) {
		if ( !$row ) {
			return null;
		}
		return new WikiSet(
			$row->ws_name,
			$row->ws_type,
			explode( ',', $row->ws_wikis ),
			$row->ws_id
		);
	}

	/**
	 * @param string $name
	 * @return null|WikiSet
	 */
	public static function newFromName( $name ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		$data = $cache->getWithSetCallback(
			self::getPerNameCacheKey( $cache, $name ),
			$cache::TTL_INDEFINITE,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $name, $fname ) {
				$dbr = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				$row = $dbr->selectRow( 'wikiset', '*', [ 'ws_name' => $name ], $fname );

				$wikiSet = self::newFromRow( $row );
				if ( $wikiSet ) {
					$value = $wikiSet->getDataForCache();
				} else {
					$ttl = WANObjectCache::TTL_MINUTE; // cache negatives
					$value = null;
				}

				return $value;
			},
			[ 'version' => self::VERSION ]
		);

		if ( !$data ) {
			return null;
		}

		$wikiSet = new WikiSet();
		$wikiSet->loadFromCachedData( $data );

		return $wikiSet;
	}

	/**
	 * @param string|int $id
	 * @return null|WikiSet
	 */
	public static function newFromID( $id ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		$data = $cache->getWithSetCallback(
			self::getPerIdCacheKey( $cache, $id ),
			$cache::TTL_INDEFINITE,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $id, $fname ) {
				$dbr = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				$row = $dbr->selectRow( 'wikiset', '*', [ 'ws_id' => $id ], $fname );

				$wikiSet = self::newFromRow( $row );
				if ( $wikiSet ) {
					$value = $wikiSet->getDataForCache();
				} else {
					$ttl = WANObjectCache::TTL_MINUTE; // cache negatives
					$value = null;
				}

				return $value;
			},
			[ 'version' => self::VERSION ]
		);

		if ( !$data ) {
			return null;
		}

		$wikiSet = new WikiSet();
		$wikiSet->loadFromCachedData( $data );

		return $wikiSet;
	}

	/**
	 * @return array
	 */
	private function getDataForCache() {
		$data = [];
		foreach ( self::$mCacheVars as $var ) {
			if ( isset( $this->$var ) ) {
				$data[$var] = $this->$var;
			}
		}

		return $data;
	}

	/**
	 * @param array $data
	 */
	private function loadFromCachedData( array $data ) {
		foreach ( $data as $key => $val ) {
			$this->$key = $val;
		}
	}

	/**
	 * @return bool
	 */
	public function saveToDB() {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->replace(
			'wikiset',
			'ws_id',
			[
				'ws_id' => $this->mId,
				'ws_name' => $this->mName,
				'ws_type' => $this->mType,
				'ws_wikis' => implode( ',', $this->mWikis ),
			],
			__METHOD__
		);
		if ( !$this->mId ) {
			$this->mId = $dbw->insertId();
		}
		$dbw->endAtomic( __METHOD__ );
		$this->purge();
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @return bool
	 */
	public function delete() {
		$dbw = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_PRIMARY );
		$dbw->delete( 'wikiset', [ 'ws_id' => $this->mId ], __METHOD__ );
		$this->purge();
		return (bool)$dbw->affectedRows();
	}

	public function purge() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( self::getPerIdCacheKey( $cache, $this->mId ) );
		$cache->delete( self::getPerNameCacheKey( $cache, $this->mName ) );
	}

	/**
	 * @param WANObjectCache $cache
	 * @param string|int $id
	 * @return string
	 */
	private static function getPerIdCacheKey( WANObjectCache $cache, $id ) {
		return $cache->makeGlobalKey( __CLASS__, 'id', $id );
	}

	/**
	 * @param WANObjectCache $cache
	 * @param string $name
	 * @return string
	 */
	private static function getPerNameCacheKey( WANObjectCache $cache, $name ) {
		return $cache->makeGlobalKey( __CLASS__, 'name', md5( $name ) );
	}

	/**
	 * @return string[]
	 */
	public function getWikis() {
		if ( $this->mType == self::OPTIN ) {
			return $this->mWikis;
		} else {
			$wikiList = CentralAuthServices::getWikiListService()->getWikiList();
			return array_diff( $wikiList, $this->mWikis );
		}
	}

	/**
	 * @param string $wiki
	 * @return bool
	 */
	public function inSet( $wiki = '' ) {
		if ( !$wiki ) {
			$wiki = WikiMap::getCurrentWikiId();
		}
		return in_array( $wiki, $this->getWikis() );
	}

	/**
	 * @return array
	 */
	public function getRestrictedGroups() {
		$dbr = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
		return $dbr->selectFieldValues(
			'global_group_restrictions',
			'ggr_group',
			[ 'ggr_set' => $this->mId ],
			__METHOD__
		);
	}

	/**
	 * @param string|null $from The wiki set name to start from (result is ordered by name)
	 * @param int|null $limit Limit for the selection (0 or null = no limit)
	 * @param bool $orderByName Order the result by name?
	 * @return array
	 */
	public static function getAllWikiSets( $from = null, $limit = null, $orderByName = false ) {
		$dbr = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
		$where = [];
		$options = [];

		if ( $from != null ) {
			$where[] = 'ws_name >= ' . $dbr->addQuotes( $from );
			$orderByName = true;
		}

		if ( $limit ) {
			$options['LIMIT'] = intval( $limit );
		}

		if ( $orderByName ) {
			$options['ORDER BY'] = 'ws_name';
		}

		$res = $dbr->select( 'wikiset', '*', $where, __METHOD__, $options );
		$result = [];
		foreach ( $res as $row ) {
			$result[] = self::newFromRow( $row );
		}
		return $result;
	}

	/**
	 * @param string $group
	 * @return int
	 */
	public static function getWikiSetForGroup( $group ) {
		$dbr = CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
		return (int)$dbr->selectField(
			'global_group_restrictions',
			'ggr_set',
			[ 'ggr_group' => $group ],
			__METHOD__
		);
	}

}
