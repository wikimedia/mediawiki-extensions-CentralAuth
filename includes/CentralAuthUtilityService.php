<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\WaitConditionLoop;

/**
 * Utility services that are useful in many parts of CentralAuth.
 *
 * @since 1.36
 */
class CentralAuthUtilityService {
	/** @var BagOStuff|null Session cache */
	private $sessionStore = null;

	/** @var LBFactory */
	private $lbFactory;

	/** @var ReadOnlyMode */
	private $readOnlyMode;

	/** @var Config */
	private $config;

	/** @var AuthManager */
	private $authManager;

	/** @var UserFactory */
	private $userFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var CentralAuthHookRunner */
	private $hookRunner;

	public function __construct(
		LBFactory $lbFactory,
		ReadOnlyMode $readOnlyMode,
		Config $config,
		AuthManager $authManager,
		UserFactory $userFactory,
		PermissionManager $permissionManager,
		IBufferingStatsdDataFactory $statsdDataFactory,
		TitleFactory $titleFactory,
		UserOptionsLookup $userOptionsLookup,
		HookContainer $hookContainer
	) {
		$this->lbFactory = $lbFactory;
		$this->readOnlyMode = $readOnlyMode;
		$this->config = $config;
		$this->authManager = $authManager;
		$this->userFactory = $userFactory;
		$this->permissionManager = $permissionManager;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->titleFactory = $titleFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->hookRunner = new CentralAuthHookRunner( $hookContainer );
	}

	/**
	 * Determine if either the local or the shared CentralAuth database is read only.
	 * @return bool
	 */
	public function isReadOnly() : bool {
		return ( $this->getReadOnlyReason() !== false );
	}

	/**
	 * Return the reason why either the the local or the shared CentralAuth database is read only, false otherwise
	 * @return bool|string
	 */
	public function getReadOnlyReason() {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return $this->readOnlyMode->getReason();
		}

		$database = $this->config->get( 'CentralAuthDatabase' );
		$lb = $this->lbFactory->getMainLB( $database );
		$reason = $lb->getReadOnlyReason( $database );
		if ( $reason !== false ) {
			return $reason;
		}

		return false;
	}

	/**
	 * Wait for the CentralAuth DB replicas to catch up
	 */
	public function waitForReplicas() : void {
		$this->lbFactory->waitForReplication( [ 'domain' => $this->config->get( 'CentralAuthDatabase' ) ] );
	}

	/**
	 * Gets a master (read/write) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 * @throws CentralAuthReadOnlyError
	 */
	public function getCentralDB() : IDatabase {
		if ( $this->config->get( 'CentralAuthReadOnly' ) ) {
			throw new CentralAuthReadOnlyError();
		}

		$database = $this->config->get( 'CentralAuthDatabase' );

		return $this->lbFactory->getMainLB( $database )
			->getConnectionRef( DB_MASTER, [], $database );
	}

	/**
	 * Gets a replica (readonly) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 */
	public function getCentralReplicaDB() : IDatabase {
		$database = $this->config->get( 'CentralAuthDatabase' );

		return $this->lbFactory->getMainLB( $database )
			->getConnectionRef( DB_REPLICA, [], $database );
	}

	/**
	 * Sets the Platform for Privacy Preferences Project (P3P) policy header,
	 * if one is configured and the browser requests one.
	 *
	 * @param WebRequest|null $request
	 */
	public function setP3P( WebRequest $request = null ) : void {
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
			$policy = $this->config->get( 'CentralAuthCookiesP3P' );

			if ( $policy === true ) {
				// Note this policy is not valid: it has no valid tokens, while
				// a valid policy would contain an "access" token and at least
				// one statement, which would contain either the NID token or
				// at least one "purpose" token, one "recipient" token, and one
				// "retention" token.
				$url = $this->titleFactory
					->makeTitle( NS_SPECIAL, 'CentralAutoLogin/P3P' )
					->getCanonicalURL();
				$response->header( "P3P: CP=\"This is not a P3P policy! See $url for more info.\"" );
			} elseif ( $policy ) {
				$response->header( "P3P: $policy" );
			}
		}
	}

	/**
	 * @param string ...$args
	 * @return string
	 */
	public function memcKey( ...$args ) : string {
		$database = $this->config->get( 'CentralAuthDatabase' );
		return $database . ':' . implode( ':', $args );
	}

	/**
	 * Wait for and return the value of a key which is expected to exist from a store
	 *
	 * @param BagOStuff $store
	 * @param string $key A key that will only have one value while it exists
	 * @param int $timeout
	 * @return mixed Key value; false if not found or on error
	 */
	public function getKeyValueUponExistence( BagOStuff $store, $key, $timeout = 3 ) {
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

	public function getSessionStore() : BagOStuff {
		if ( !$this->sessionStore ) {
			$sessionCacheType = $this->config->get( 'CentralAuthSessionCacheType' )
				?? $this->config->get( 'SessionCacheType' );
			$cache = ObjectCache::getInstance( $sessionCacheType );
			$this->sessionStore = $cache instanceof CachedBagOStuff
				? $cache : new CachedBagOStuff( $cache );
		}

		return $this->sessionStore;
	}

	public function autoCreateUser( User $user ) : StatusValue {
		// Ignore warnings about master connections/writes...hard to avoid here

		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$source = CentralAuthPrimaryAuthenticationProvider::class;
		if ( !$this->authManager->getAuthenticationProvider( $source ) ) {
			$source = AuthManager::AUTOCREATE_SOURCE_SESSION;
		}
		$sv = $this->authManager->autoCreateUser( $user, $source, false );

		LoggerFactory::getInstance( 'authevents' )->info( 'Autocreation attempt', [
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
	 * Attempt to create a local user for the specified username.
	 * @param string $username
	 * @param User|null $performer
	 * @param string|null $reason
	 * @return Status
	 */
	public function attemptAutoCreateLocalUserFromName(
		string $username,
		$performer = null,
		$reason = null
	) : Status {
		$user = $this->userFactory->newFromName( $username );

		if ( !$user ) {
			// invalid username
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $user->getId() ) {
			return Status::newFatal( 'centralauth-createlocal-already-exists' );
		}

		$centralUser = CentralAuthUser::getInstance( $user );

		if ( !$centralUser->exists() ) {
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $centralUser->isOversighted() ) {
			$canOversight = $performer && $this->permissionManager
				->userHasRight( $performer, 'centralauth-oversight' );

			return Status::newFatal( $canOversight
				? 'centralauth-createlocal-suppressed'
				: 'centralauth-createlocal-no-global-account' );
		}

		$status = $this->autoCreateUser( $user );
		if ( !$status->isGood() ) {
			return Status::wrap( $status );
		}

		// Add log entry
		if ( $performer ) {
			$logEntry = new ManualLogEntry( 'newusers', 'forcecreatelocal' );
			$logEntry->setPerformer( $performer );
			$logEntry->setTarget( $user->getUserPage() );
			$logEntry->setComment( $reason );
			$logEntry->setParameters( [
				'4::userid' => $user->getId(),
			] );

			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}

		// Update user count
		SiteStatsUpdate::factory( [ 'users' => 1 ] )->doUpdate();

		return Status::newGood();
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

	/**
	 * Delete the central session data
	 * @param Session|null $session
	 */
	public function deleteCentralSession( $session = null ) {
		if ( !$session ) {
			$session = SessionManager::getGlobalSession();
		}
		$id = $session->get( 'CentralAuth::centralSessionId' );

		if ( $id !== null ) {
			$sessionStore = $this->getSessionStore();
			$key = $this->memcKey( 'session', $id );

			$stime = microtime( true );
			$sessionStore->delete( $key, $sessionStore::WRITE_SYNC );
			$real = microtime( true ) - $stime;

			$this->statsdDataFactory->timing( "centralauth.session.delete", $real );
		}
	}

	/**
	 * Sets up jobs to create and attach a local account for the given user on every wiki listed in
	 * $wgCentralAuthAutoCreateWikis.
	 * @param CentralAuthUser $centralUser
	 */
	public function scheduleCreationJobs( CentralAuthUser $centralUser ) {
		$name = $centralUser->getName();
		$thisWiki = WikiMap::getCurrentWikiId();
		$session = RequestContext::getMain()->exportSession();
		foreach ( $this->config->get( 'CentralAuthAutoCreateWikis' ) as $wiki ) {
			if ( $wiki === $thisWiki ) {
				continue;
			}
			$job = Job::factory(
				'CentralAuthCreateLocalAccountJob',
				[ 'name' => $name, 'from' => $thisWiki, 'session' => $session ]
			);
			JobQueueGroup::singleton( $wiki )->lazyPush( $job );
		}
	}

	/**
	 * Check whether the user's preferences are such that a UI reload is
	 * recommended.
	 * @param User $user
	 * @return bool
	 */
	public function isUIReloadRecommended( User $user ) {
		foreach ( $this->config->get( 'CentralAuthPrefsForUIReload' ) as $pref ) {
			if ( $this->userOptionsLookup->getOption( $user, $pref )
				!== $this->userOptionsLookup->getDefaultOption( $pref ) ) {
				return true;
			}
		}

		$recommendReload = false;
		$this->hookRunner->onCentralAuthIsUIReloadRecommended( $user, $recommendReload );
		return $recommendReload;
	}
}
