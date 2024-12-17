<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\MediaWikiServices;
use MWCryptRand;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Assert\Assert;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\WaitConditionLoop;

class CentralAuthTokenManager {

	private BagOStuff $tokenStore;
	private LoggerInterface $logger;

	/**
	 * @param BagOStuff $tokenStore Store for short-lived tokens used during authentication.
	 *   Typically {@see MediaWikiServices::getMicroStash()}.
	 */
	public function __construct(
		BagOStuff $tokenStore,
		?LoggerInterface $logger = null
	) {
		$this->tokenStore = $tokenStore;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * @param string $keygroup
	 * @param string ...$components
	 * @return string The global token key (with proper escaping)
	 */
	private function makeTokenKey( string $keygroup, ...$components ): string {
		return $this->tokenStore->makeGlobalKey(
			$keygroup, $this->getCentralAuthDBForSessionKey(), ...$components
		);
	}

	/**
	 * Some existing keys both pre- and postfix the token.
	 *
	 * @param string|array $namespace Key parts; the first goes before the token, the rest go after.
	 * @param string $token
	 * @return string
	 */
	private function makeLegacyTokenKey( $namespace, $token ): string {
		if ( is_array( $namespace ) ) {
			$head = array_shift( $namespace );
			$tail = $namespace;
		} else {
			$head = $namespace;
			$tail = [];
		}
		return $this->makeTokenKey( $head, $token, ...$tail );
	}

	/**
	 * Store a value for a short time via the shared token store, and return the random key it's
	 * stored under. This can be used to pass data between requests in a redirect chain via a
	 * random URL token that cannot be sniffed or tampered with.
	 *
	 * An attacker can start the process that involves generating the token, but then instead of
	 * following the redirect, tricking a victim to follow it, e.g. to set up a session fixation
	 * attack. It is the caller's responsibility to handle this threat.
	 *
	 * @param mixed $data The value to store. Must be serializable and can't be boolean false.
	 * @param string|array $keyPrefix Namespace in the token store.
	 * @param array $options Options:
	 *   - expiry (int, default 60): Expiration time of the token store record in seconds.
	 *   - token(string): Reuse the given token (presumably one from an earlier tokenize()
	 *     call) instead of generating a new random token.
	 * @return string The random key (without the prefix).
	 */
	public function tokenize(
		$data,
		$keyPrefix,
		array $options = []
	): string {
		Assert::parameter( $data !== false, '$data', 'cannot be boolean false' );
		$expiry = $options['expiry'] ?? ExpirationAwareness::TTL_MINUTE;
		$token = $options['token'] ?? MWCryptRand::generateHex( 32 );
		$key = $this->makeLegacyTokenKey( $keyPrefix, $token );
		$this->tokenStore->set( $key, $data, $expiry );
		return $token;
	}

	/**
	 * Recover the value concealed with tokenize().
	 *
	 * The value is left in the store. It is the caller's responsibility to prevent replay attacks.
	 *
	 * @param string $token The random key returned by tokenize().
	 * @param string|array $keyPrefix Namespace in the token store.
	 * @param array $options Options:
	 *   - timeout (int, default 3): Seconds to wait for the token store record to be created
	 *     by another thread, when the first lookup doesn't find it.
	 * @return mixed|false The value, or false if it was not found.
	 */
	public function detokenize(
		string $token,
		$keyPrefix,
		array $options = []
	) {
		$timeout = $options['timeout'] ?? 3;
		$key = $this->makeLegacyTokenKey( $keyPrefix, $token );
		return $this->getKeyValueUponExistence( $key, $timeout );
	}

	/**
	 * Recover the value concealed with tokenize(), and delete it from the store.
	 *
	 * @param string $token The random key returned by tokenize().
	 * @param string|array $keyPrefix Namespace in the token store.
	 * @param array $options Options:
	 * *   - timeout (int, default 3): Seconds to wait for the token store record to be created
	 * *     by another thread, when the first lookup doesn't find it.
	 * @return mixed|false The value, or false if it was not found.
	 */
	public function detokenizeAndDelete(
		string $token,
		$keyPrefix,
		array $options = []
	) {
		$key = $this->makeLegacyTokenKey( $keyPrefix, $token );
		$value = $this->detokenize( $token, $keyPrefix, $options );
		if ( $value !== false ) {
			$this->tokenStore->delete( $key );
		}
		return $value;
	}

	/**
	 * Wait for and return the value of a key which is expected to exist from a store
	 *
	 * @param string $key A key that will only have one value while it exists
	 * @param int $timeout
	 * @return mixed Key value; false if not found or on error
	 */
	private function getKeyValueUponExistence( $key, $timeout = 3 ) {
		$value = false;

		$result = ( new WaitConditionLoop(
			function () use ( $key, &$value ) {
				$store = $this->tokenStore;
				$watchPoint = $store->watchErrors();
				$value = $store->get( $key );
				$error = $store->getLastError( $watchPoint );
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

		if ( $result === WaitConditionLoop::CONDITION_REACHED ) {
			$this->logger->info( "Expected key {key} found.", [ 'key' => $key ] );
		} elseif ( $result === WaitConditionLoop::CONDITION_TIMED_OUT ) {
			$this->logger->info( "Expected key {key} not found due to timeout.", [ 'key' => $key ] );
		} else {
			$this->logger->error( "Expected key {key} not found due to I/O error.", [ 'key' => $key ] );
		}

		return $value;
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

}
