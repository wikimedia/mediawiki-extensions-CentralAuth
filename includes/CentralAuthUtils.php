<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MediaWiki\MediaWikiServices;
use Wikimedia\WaitConditionLoop;
use MediaWiki\Logger\LoggerFactory;

class CentralAuthUtils {
	/** @var BagOStuff|null Session cache */
	private static $sessionStore = null;

	public static function isReadOnly() {
		return ( self::getReadOnlyReason() !== false );
	}

	public static function getReadOnlyReason() {
		global $wgCentralAuthDatabase;

		if ( wfReadOnly() ) {
			return wfReadOnlyReason();
		}

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB( $wgCentralAuthDatabase );
		$reason = $lb->getReadOnlyReason( $wgCentralAuthDatabase );
		if ( $reason !== false ) {
			return $reason;
		}

		return false;
	}

	/**
	 * Wait for the CentralAuth DB slaves to catch up
	 */
	public static function waitForSlaves() {
		global $wgCentralAuthDatabase;

		wfWaitForSlaves( false, $wgCentralAuthDatabase );
	}

	/**
	 * Gets a master (read/write) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 * @throws CentralAuthReadOnlyError
	 */
	public static function getCentralDB() {
		global $wgCentralAuthDatabase, $wgCentralAuthReadOnly;

		if ( $wgCentralAuthReadOnly ) {
			throw new CentralAuthReadOnlyError();
		}

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		return $lbFactory->getMainLB( $wgCentralAuthDatabase )->getConnectionRef( DB_MASTER, [],
			$wgCentralAuthDatabase );
	}

	/**
	 * Gets a slave (readonly) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 */
	public static function getCentralReplicaDB() {
		global $wgCentralAuthDatabase;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		return $lbFactory->getMainLB( $wgCentralAuthDatabase )
			->getConnectionRef( DB_REPLICA, 'centralauth', $wgCentralAuthDatabase );
	}

	/**
	 * @return \Wikimedia\Rdbms\IDatabase
	 * @deprecated Since 1.33; use getCentralReplicaDB()
	 */
	public function getCentralSlaveDB() {
		return self::getCentralReplicaDB();
	}

	/**
	 * @param WebRequest|null $request
	 */
	public static function setP3P( WebRequest $request = null ) {
		if ( !$request ) {
			$request = RequestContext::getMain()->getRequest();
		}
		$response = $request->response();

		$sent = is_callable( [ $response, 'headersSent' ] )
			? $response->headersSent()
			: headers_sent();

		if ( !$sent && $response->getHeader( 'P3P' ) === null ) {
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
				$response->header( "P3P: CP=\"This is not a P3P policy! See $url for more info.\"" );
			} elseif ( $wgCentralAuthCookiesP3P ) {
				$response->header( "P3P: $wgCentralAuthCookiesP3P" );
			}
		}
	}

	/**
	 * @param string ...$args
	 * @return string
	 */
	public static function memcKey( ...$args ) {
		global $wgCentralAuthDatabase;
		return $wgCentralAuthDatabase . ':' . implode( ':', $args );
	}

	/**
	 * Wait for and return the value of a key which is expected to exist from a store
	 *
	 * @param BagOStuff $store
	 * @param string $key A key that will only have one value while it exists
	 * @param int $timeout
	 * @return mixed Key value; false if not found or on error
	 */
	public static function getKeyValueUponExistence( BagOStuff $store, $key, $timeout = 3 ) {
		$value = false;

		$result = ( new WaitConditionLoop(
			function () use ( $store, $key, &$value ) {
				$store->clearLastError();
				$value = $store->get( $key );
				$error = $store->getLastError();
				if ( $value !== false ) {
					return WaitConditionLoop::CONDITION_REACHED;
				} elseif ( $error === $store::ERR_NONE ) {
					return WaitConditionLoop::CONDITION_CONTINUE;
				} else {
					return WaitConditionLoop::CONDITION_ABORTED;
				}
			},
			$timeout
		) )->invoke();

		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		if ( $result === WaitConditionLoop::CONDITION_REACHED ) {
			$logger->info( "Expected key {key} found.", [ 'key' => $key ] );
		} elseif ( $result === WaitConditionLoop::CONDITION_TIMED_OUT ) {
			$logger->error( "Expected key {key} not found due to timeout.", [ 'key' => $key ] );
		} else {
			$logger->error( "Expected key {key} not found due to I/O error.", [ 'key' => $key ] );
		}

		return $value;
	}

	/**
	 * @return BagOStuff
	 */
	public static function getSessionStore() {
		global $wgSessionCacheType;

		if ( !self::$sessionStore ) {
			$cache = ObjectCache::getInstance( $wgSessionCacheType );
			self::$sessionStore = $cache instanceof CachedBagOStuff
				? $cache : new CachedBagOStuff( $cache );
		}
		return self::$sessionStore;
	}

	/**
	 * Auto-create a user
	 * @param User $user
	 * @return StatusValue
	 */
	public static function autoCreateUser( User $user ) {
		// Ignore warnings about master connections/writes...hard to avoid here
		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$authManager = AuthManager::singleton();
		$source = CentralAuthPrimaryAuthenticationProvider::class;
		if ( !$authManager->getAuthenticationProvider( $source ) ) {
			$source = AuthManager::AUTOCREATE_SOURCE_SESSION;
		}
		$sv = $authManager->autoCreateUser( $user, $source, false );

		\MediaWiki\Logger\LoggerFactory::getInstance( 'authevents' )->info( 'Autocreation attempt', [
			'event' => 'autocreate',
			'status' => $sv,
		] );
		return $sv;
	}

	/**
	 * Get the central session data
	 * @param Session|null $session
	 * @return array
	 */
	public static function getCentralSession( $session = null ) {
		if ( !$session ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $id !== null ) {
			return self::getCentralSessionById( $id );
		} else {
			return [];
		}
	}

	/**
	 * Get the central session data
	 * @param string $id
	 * @return array
	 */
	public static function getCentralSessionById( $id ) {
		$sessionStore = self::getSessionStore();
		$key = self::memcKey( 'session', $id );

		$stime = microtime( true );
		$data = $sessionStore->get( $key ) ?: [];
		$real = microtime( true ) - $stime;

		MediaWikiServices::getInstance()
			->getStatsdDataFactory()->timing( 'centralauth.session.read', $real );

		return $data;
	}

	/**
	 * Set data in the central session
	 * @param array $data
	 * @param bool|string $reset Reset the session ID. If a string, this is the new ID.
	 * @param Session|null $session
	 * @return string|null Session ID
	 */
	public static function setCentralSession( array $data, $reset = false, $session = null ) {
		static $keepKeys = [ 'user' => true, 'token' => true, 'expiry' => true ];

		if ( $session === null ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $reset || $id === null ) {
			$id = is_string( $reset ) ? $reset : MWCryptRand::generateHex( 32 );
		}
		$data['sessionId'] = $id;

		$sessionStore = self::getSessionStore();
		$key = self::memcKey( 'session', $id );

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
			MediaWikiServices::getInstance()
				->getStatsdDataFactory()->timing( 'centralauth.session.write', $real );
		}

		if ( $session ) {
			$session->set( 'CentralAuth::centralSessionId', $id );
		}

		return $id;
	}

	/**
	 * Delete the central session data
	 * @param Session|null $session
	 */
	public static function deleteCentralSession( $session = null ) {
		if ( !$session ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $id !== null ) {
			$sessionStore = self::getSessionStore();
			$key = self::memcKey( 'session', $id );

			$stime = microtime( true );
			$sessionStore->delete( $key, $sessionStore::WRITE_SYNC );
			$real = microtime( true ) - $stime;

			MediaWikiServices::getInstance()
				->getStatsdDataFactory()->timing( "centralauth.session.delete", $real );
		}
	}

	/**
	 * Sets up jobs to create and attach a local account for the given user on every wiki listed in
	 * $wgCentralAuthAutoCreateWikis.
	 * @param CentralAuthUser $centralUser
	 */
	public static function scheduleCreationJobs( CentralAuthUser $centralUser ) {
		global $wgCentralAuthAutoCreateWikis;

		$name = $centralUser->getName();
		$thisWiki = wfWikiID();
		$session = RequestContext::getMain()->exportSession();
		foreach ( $wgCentralAuthAutoCreateWikis as $wiki ) {
			if ( $wiki === $thisWiki ) {
				continue;
			}
			$job = Job::factory(
				'CentralAuthCreateLocalAccountJob',
				Title::makeTitleSafe( NS_USER, $name ),
				[ 'name' => $name, 'from' => $thisWiki, 'session' => $session ]
			);
			JobQueueGroup::singleton( $wiki )->lazyPush( $job );
		}
	}
}
