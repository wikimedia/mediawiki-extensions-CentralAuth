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

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MWCryptRand;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\CachedBagOStuff;
use Wikimedia\Stats\IBufferingStatsdDataFactory;
use Wikimedia\Stats\StatsFactory;

class CentralAuthSessionManager {

	/**
	 * @internal Only public for service wiring use
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CentralAuthSessionCacheType',
		'SessionCacheType',
	];

	/** @var BagOStuff|null Session cache */
	private $sessionStore = null;

	private ServiceOptions $options;
	private IBufferingStatsdDataFactory $statsdDataFactory;
	private StatsFactory $statsFactory;

	public function __construct(
		ServiceOptions $options,
		IBufferingStatsdDataFactory $statsdDataFactory,
		StatsFactory $statsFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * @return string db name, for session key creation
	 * Note that if there is more than one CentralAuth database
	 * in use for the same session key store, the database names
	 * MUST be unique.
	 */
	private function getCentralAuthDBForSessionKey() {
		return MediaWikiServices::getInstance()
			->getDBLoadBalancerFactory()->getPrimaryDatabase( 'virtual-centralauth' )->getDomainID();
	}

	/**
	 * @param string $keygroup
	 * @param string ...$components
	 * @return string The global session key (with proper escaping)
	 */
	public function makeSessionKey( string $keygroup, ...$components ): string {
		return $this->getSessionStore()->makeGlobalKey(
			$keygroup, $this->getCentralAuthDBForSessionKey(), ...$components
		);
	}

	/**
	 * Get a cache for storage of central sessions
	 * @return BagOStuff
	 */
	public function getSessionStore(): BagOStuff {
		if ( !$this->sessionStore ) {
			$sessionCacheType = $this->options->get( 'CentralAuthSessionCacheType' )
				?? $this->options->get( 'SessionCacheType' );
			$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getInstance( $sessionCacheType );
			$this->sessionStore = $cache instanceof CachedBagOStuff
				? $cache : new CachedBagOStuff( $cache );
		}

		return $this->sessionStore;
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
		$key = $this->makeSessionKey( 'session', $id );

		$stime = microtime( true );
		$data = $this->getSessionStore()->get( $key ) ?: [];
		$real = microtime( true ) - $stime;

		// Stay backward compatible with the dashboard feeding on
		// this data. NOTE: $real is in second with microsecond-level
		// precision. This is reconciled on the grafana dashboard.
		$this->statsdDataFactory->timing( 'centralauth.session.read', $real );

		$this->statsFactory->withComponent( 'CentralAuth' )
			->getTiming( 'session_read_seconds' )
			->observe( $real * 1000 );

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

		$session ??= SessionManager::getGlobalSession();
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $reset || $id === null ) {
			$id = is_string( $reset ) ? $reset : MWCryptRand::generateHex( 32 );
		}
		$data['sessionId'] = $id;

		$sessionStore = $this->getSessionStore();
		$key = $this->makeSessionKey( 'session', $id );

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
				$sessionStore::TTL_DAY
			);
			$real = microtime( true ) - $stime;
			// Stay backward compatible with the dashboard feeding on
			// this data. NOTE: $real is in second with microsecond-level
			// precision. This is reconciled on the grafana dashboard.
			$this->statsdDataFactory->timing( 'centralauth.session.write', $real );

			$this->statsFactory->withComponent( 'CentralAuth' )
				->getTiming( 'session_write_seconds' )
				->observe( $real * 1000 );
		}

		if ( $session ) {
			$session->set( 'CentralAuth::centralSessionId', $id );
		}

		return $id;
	}
}
