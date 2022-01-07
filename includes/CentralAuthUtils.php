<?php

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Session\Session;

/**
 * @deprecated since 1.36, use CentralAuthUtilityService or CentralAuthDatabaseManager
 * or CentralAuthSessionManager instead
 */
class CentralAuthUtils {
	private static function getUtilityService(): CentralAuthUtilityService {
		return CentralAuthServices::getUtilityService();
	}

	private static function getDatabaseManager(): CentralAuthDatabaseManager {
		return CentralAuthServices::getDatabaseManager();
	}

	private static function getSessionManager(): CentralAuthSessionManager {
		return CentralAuthServices::getSessionManager();
	}

	public static function isReadOnly() {
		return self::getDatabaseManager()->isReadOnly();
	}

	public static function getReadOnlyReason() {
		return self::getDatabaseManager()->getReadOnlyReason();
	}

	/**
	 * Wait for the CentralAuth DB replicas to catch up
	 */
	public static function waitForReplicas() {
		self::getDatabaseManager()->waitForReplication();
	}

	/**
	 * Gets a primary (read/write) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 * @throws CentralAuthReadOnlyError
	 */
	public static function getCentralDB() {
		return self::getDatabaseManager()->getCentralDB( DB_PRIMARY );
	}

	/**
	 * Gets a replica (readonly) database connection to the CentralAuth database
	 *
	 * @return \Wikimedia\Rdbms\IDatabase
	 */
	public static function getCentralReplicaDB() {
		return self::getDatabaseManager()->getCentralDB( DB_REPLICA );
	}

	/**
	 * @param WebRequest|null $request
	 */
	public static function setP3P( WebRequest $request = null ) {
		self::getUtilityService()->setP3P( $request );
	}

	/**
	 * @param string ...$args
	 * @return string
	 */
	public static function memcKey( ...$args ) {
		return self::getSessionManager()->memcKey( ...$args );
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
		return self::getUtilityService()->getKeyValueUponExistence( $store, $key, $timeout );
	}

	/**
	 * @return BagOStuff
	 */
	public static function getSessionStore() {
		return self::getSessionManager()->getSessionStore();
	}

	/**
	 * @return BagOStuff
	 */
	public static function getTokenStore() {
		return self::getSessionManager()->getTokenStore();
	}

	/**
	 * Auto-create a user
	 * @param User $user
	 * @return StatusValue
	 */
	public static function autoCreateUser( User $user ) {
		return self::getUtilityService()->autoCreateUser( $user );
	}

	/**
	 * Get the central session data
	 * @param Session|null $session
	 * @return array
	 */
	public static function getCentralSession( $session = null ) {
		return self::getSessionManager()->getCentralSession( $session );
	}

	/**
	 * Get the central session data
	 * @param string $id
	 * @return array
	 */
	public static function getCentralSessionById( $id ) {
		return self::getSessionManager()->getCentralSessionById( $id );
	}

	/**
	 * Set data in the central session
	 * @param array $data
	 * @param bool|string $reset Reset the session ID. If a string, this is the new ID.
	 * @param Session|null $session
	 * @return string|null Session ID
	 */
	public static function setCentralSession( array $data, $reset = false, $session = null ) {
		return self::getSessionManager()->setCentralSession( $data, $reset, $session );
	}

	/**
	 * Sets up jobs to create and attach a local account for the given user on every wiki listed in
	 * $wgCentralAuthAutoCreateWikis.
	 * @param CentralAuthUser $centralUser
	 */
	public static function scheduleCreationJobs( CentralAuthUser $centralUser ) {
		self::getUtilityService()->scheduleCreationJobs( $centralUser );
	}
}
