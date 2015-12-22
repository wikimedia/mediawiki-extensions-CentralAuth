<?php

class WikiSet {
	const OPTIN = 'optin';
	const OPTOUT = 'optout';
	const VERSION = 1;

	private $mId;	// ID of the group
	private $mName;	// Display name of the group
	private $mType;	// Opt-in based or opt-out based
	private $mWikis;	// List of wikis
	// This property is used, don't remove it
	// (That means you Reedy & Siebrand)
	private $mVersion = self::VERSION;      // Caching purposes

	static $mCacheVars = array(
		'mId',
		'mName',
		'mType',
		'mWikis',
		'mVersion',
	);

	/**
	 * @param $name string
	 * @param $type string
	 * @param $wikis array
	 * @param $id int
	 */
	public function __construct( $name = '', $type = self::OPTIN, $wikis = array(), $id = 0 ) {
		$this->mId = $id;
		$this->mName = $name;
		$this->mType = $type;
		$this->mWikis = $wikis;
	}

	/**
	 * @param $k string
	 * @return string
	 */
	protected static function memcKey( $k ) { return "wikiset:{$k}"; }

	/**
	 * @return int
	 */
	public function getId() { return $this->mId; }

	/**
	 * @return bool
	 */
	public function exists() { return (bool)$this->getID(); }

	/**
	 * @return string
	 */
	public function getName() { return $this->mName; }

	/**
	 * @param $n
	 */
	public function setName( $n ) { $this->setDbField( 'ws_name', $n ); }

	/**
	 * @return array
	 */
	public function getWikisRaw() { return $this->mWikis; }

	/**
	 * @param $w
	 */
	public function setWikisRaw( $w ) { $this->setDbField( 'ws_wikis', $w ); }

	/**
	 * @return string
	 */
	public function getType() { return $this->mType; }

	/**
	 * @param $t
	 * @return bool
	 */
	public function setType( $t ) {
		if ( !in_array( $t, array( self::OPTIN, self::OPTOUT ) ) ) {
			return;
		}
		$this->setDbField( 'ws_type', $t );
	}

	/**
	 * @param $field
	 * @param $value
	 */
	protected function setDbField( $field, $value ) {
		$map = array( 'ws_name' => 'mName', 'ws_type' => 'mType', 'ws_wikis' => 'mWikis' );
		$mname = $map[$field];
		$this->$mname = $value;
	}

	/**
	 * @param $row
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
	 * @param $name
	 * @param $useCache bool
	 * @return null|WikiSet
	 */
	public static function newFromName( $name, $useCache = true ) {
		if ( $useCache ) {
			$cache = ObjectCache::getMainWANInstance();
			$data = $cache->get( self::memcKey( "name:" . md5( $name ) ) );
			if ( $data ) {
				if ( $data['mVersion'] == self::VERSION ) {
					$ws = new WikiSet( null, null );
					foreach ( $data as $key => $val ) {
						$ws->$key = $val;
					}
					return $ws;
				}
			}
		}
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$row = $dbr->selectRow(
			'wikiset', '*', array( 'ws_name' => $name ), __METHOD__
		);
		if ( !$row ) {
			return null;
		}
		$ws = self::newFromRow( $row );
		$ws->saveToCache();
		return $ws;
	}

	/**
	 * @param $id string|int
	 * @param $useCache bool
	 * @return null|WikiSet
	 */
	public static function newFromID( $id, $useCache = true ) {
		if ( $useCache ) {
			$cache = ObjectCache::getMainWANInstance();
			$data = $cache->get( self::memcKey( $id ) );
			if ( $data ) {
				if ( $data['mVersion'] == self::VERSION ) {
					$ws = new WikiSet( null, null );
					foreach ( $data as $name => $val ) {
						$ws->$name = $val;
					}
					return $ws;
				}
			}
		}
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$row = $dbr->selectRow(
			'wikiset', '*', array( 'ws_id' => $id ), __METHOD__
		);
		if ( !$row ) {
			return null;
		}
		$ws = self::newFromRow( $row );
		$ws->saveToCache();
		return $ws;
	}

	/**
	 * @return bool
	 */
	public function saveToDB() {
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->startAtomic( __METHOD__ );
		$dbw->replace( 'wikiset', array( 'ws_id' ),
			array(
				'ws_id' => $this->mId,
				'ws_name' => $this->mName,
				'ws_type' => $this->mType,
				'ws_wikis' => implode( ',', $this->mWikis ),
			), __METHOD__
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
		$dbw = CentralAuthUtils::getCentralDB();
		$dbw->delete( 'wikiset', array( 'ws_id' => $this->mId ), __METHOD__ );
		$this->purge();
		return (bool)$dbw->affectedRows();
	}

	public function purge() {
		$cache = ObjectCache::getMainWANInstance();
		$cache->delete( self::memcKey( $this->mId ) );
		$cache->delete( self::memcKey( "name:" . md5( $this->mName ) ) );
	}

	public function saveToCache() {
		$cache = ObjectCache::getMainWANInstance();
		$data = array();
		foreach ( self::$mCacheVars as $var ) {
			if ( isset( $this->$var ) ) {
				$data[$var] = $this->$var;
			}
		}
		$cache->set( self::memcKey( $this->mId ), $data );
	}

	/**
	 * @return array
	 */
	public function getWikis() {
		if ( $this->mType == self::OPTIN ) {
			return $this->mWikis;
		} else {
			return array_diff( CentralAuthUser::getWikiList(), $this->mWikis );
		}
	}

	/**
	 * @param $wiki string
	 * @return bool
	 */
	public function inSet( $wiki = '' ) {
		if ( !$wiki ) {
			$wiki = wfWikiID();
		}
		return in_array( $wiki, $this->getWikis() );
	}

	/**
	 * @return array
	 */
	public function getRestrictedGroups() {
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$r = $dbr->select(
			'global_group_restrictions', '*', array( 'ggr_set' => $this->mId ), __METHOD__
		);
		$result = array();
		foreach ( $r as $row ) {
			$result[] = $row->ggr_group;
		}
		return $result;
	}

	/**
	 * @param $from string The wiki set name to start from (result is ordered by name)
	 * @param $limit integer Limit for the selection (0 or null = no limit)
	 * @param $orderByName boolean Order the result by name?
	 * @return array
	 */
	public static function getAllWikiSets( $from = null, $limit = null, $orderByName = false ) {
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$where = array();
		$options = array();

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
		$result = array();
		foreach ( $res as $row ) {
			$result[] = self::newFromRow( $row );
		}
		return $result;
	}

	/**
	 * @param $group
	 * @return int
	 */
	public static function getWikiSetForGroup( $group ) {
		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$res = $dbr->selectRow( 'global_group_restrictions', '*', array( 'ggr_group' => $group ), __METHOD__ );
		return $res ? $res->ggr_set : 0;
	}

	/**
	 * @static
	 * @param $type
	 * @return string
	 */
	public static function formatType( $type ) {
		// Give grep a chance to find the usages:
		// centralauth-rightslog-set-optin, centralauth-rightslog-set-optout
		return wfMessage( "centralauth-rightslog-set-{$type}" )->escaped();
	}
}
