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

namespace MediaWiki\Extension\CentralAuth;

use BagOStuff;
use CachedBagOStuff;
use IBufferingStatsdDataFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MWCryptRand;
use ObjectCache;

class CentralAuthSessionManager {
	/**
	 * @internal Only public for service wiring use
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CentralAuthDatabase',
		'CentralAuthSessionCacheType',
		'CentralAuthTokenCacheType',
		'SessionCacheType',
	];

	/** @var BagOStuff|null Session cache */
	private $sessionStore = null;

	/** @var BagOStuff|null Token cache */
	private $tokenStore = null;

	/** @var ServiceOptions */
	private $options;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/**
	 * @param ServiceOptions $options
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 */
	public function __construct(
		ServiceOptions $options,
		IBufferingStatsdDataFactory $statsdDataFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	/**
	 * @param string ...$args
	 * @return string
	 */
	public function memcKey( ...$args ): string {
		$database = $this->options->get( 'CentralAuthDatabase' );
		return $database . ':' . implode( ':', $args );
	}

	/**
	 * Get a cache for storage of central sessions
	 * @return BagOStuff
	 */
	public function getSessionStore(): BagOStuff {
		if ( !$this->sessionStore ) {
			$sessionCacheType = $this->options->get( 'CentralAuthSessionCacheType' )
				?? $this->options->get( 'SessionCacheType' );
			$cache = ObjectCache::getInstance( $sessionCacheType );
			$this->sessionStore = $cache instanceof CachedBagOStuff
				? $cache : new CachedBagOStuff( $cache );
		}

		return $this->sessionStore;
	}

	/**
	 * Get a cache for storage of temporary cross-site tokens
	 * @return BagOStuff
	 */
	public function getTokenStore(): BagOStuff {
		if ( !$this->tokenStore ) {
			$cacheType = $this->options->get( 'CentralAuthTokenCacheType' )
				?? $this->options->get( 'CentralAuthSessionCacheType' )
				?? $this->options->get( 'SessionCacheType' );
			$this->tokenStore = ObjectCache::getInstance( $cacheType );
		}
		return $this->tokenStore;
	}

	/**
	 * Get the central session data
	 * @param Session|null $session
	 * @return array
	 */
	public function getCentralSession( $session = null ) {
		if ( !$session ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $id !== null ) {
			return $this->getCentralSessionById( $id );
		} else {
			return [];
		}
	}

	/**
	 * Get the central session data
	 * @param string $id
	 * @return array
	 */
	public function getCentralSessionById( $id ) {
		$sessionStore = $this->getSessionStore();
		$key = $this->memcKey( 'session', $id );

		$stime = microtime( true );
		$data = $sessionStore->get( $key ) ?: [];
		$real = microtime( true ) - $stime;

		$this->statsdDataFactory->timing( 'centralauth.session.read', $real );

		return $data;
	}

	/**
	 * Set data in the central session
	 * @param array $data
	 * @param bool|string $reset Reset the session ID. If a string, this is the new ID.
	 * @param Session|null $session
	 * @return string|null Session ID
	 */
	public function setCentralSession( array $data, $reset = false, $session = null ) {
		$keepKeys = [ 'user' => true, 'token' => true, 'expiry' => true ];

		if ( $session === null ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $reset || $id === null ) {
			$id = is_string( $reset ) ? $reset : MWCryptRand::generateHex( 32 );
		}
		$data['sessionId'] = $id;

		$sessionStore = $this->getSessionStore();
		$key = $this->memcKey( 'session', $id );

		// Copy certain keys from the existing session, if any (T124821)
		$existing = $sessionStore->get( $key );
		if ( is_array( $existing ) ) {
			$data += array_intersect_key( $existing, $keepKeys );
		}

		$isDirty = ( $data !== $existing );
		if ( $isDirty || !isset( $data['expiry'] ) || $data['expiry'] < time() + 32100 ) {
			$data['expiry'] = time() + $sessionStore::TTL_DAY;
			$stime = microtime( true );
			$sessionStore->set(
				$key,
				$data,
				$sessionStore::TTL_DAY,
				$isDirty ? $sessionStore::WRITE_SYNC : 0
			);
			$real = microtime( true ) - $stime;
			$this->statsdDataFactory->timing( 'centralauth.session.write', $real );
		}

		if ( $session ) {
			$session->set( 'CentralAuth::centralSessionId', $id );
		}

		return $id;
	}
}
