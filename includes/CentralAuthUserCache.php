<?php

namespace MediaWiki\Extension\CentralAuth;

use MapCacheLRU;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * An in-process cache for CentralAuthUser objects
 *
 * This cache has get/set semantics, it doesn't read through, and it requires
 * normalized usernames, so it's mostly internal. Long term, I imagine
 * CentralAuthUser::getInstance() would be moved to a factory, and the factory
 * would have a reference to this cache.
 */
class CentralAuthUserCache {

	private MapCacheLRU $map;

	public function __construct() {
		$this->map = new MapCacheLRU( 100 );
	}

	/**
	 * Get a user from the cache by name. If there is no stored user, or if the
	 * user's data is not sufficiently recent, return null.
	 *
	 * @param string $name The normalized username
	 * @param bool $fromPrimary Whether the data is required to be from the primary.
	 */
	public function get( string $name, $fromPrimary = false ): ?CentralAuthUser {
		/** @var CentralAuthUser|null $centralUser */
		$centralUser = $this->map->get( $name );
		if ( $fromPrimary ) {
			if ( $centralUser && $centralUser->isFromPrimary() ) {
				return $centralUser;
			}
			return null;
		}
		return $centralUser;
	}

	/**
	 * Store a user to the cache
	 */
	public function set( CentralAuthUser $centralUser ) {
		$this->map->set( $centralUser->getName(), $centralUser );
	}

	/**
	 * Delete all entries
	 */
	public function clear() {
		$this->map->clear();
	}

	/**
	 * Delete all entries with the specified username
	 */
	public function delete( string $name ) {
		$this->map->clear( [ $name ] );
	}
}
