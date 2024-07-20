<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\MediaWikiServices;
use MWCryptRand;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
		LoggerInterface $logger = null
	) {
		$this->tokenStore = $tokenStore;
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * Get a cache for storage of temporary cross-site tokens
	 * @return BagOStuff
	 */
	public function getTokenStore(): BagOStuff {
		// FIXME remove callers and make private
		return $this->tokenStore;
	}

	/**
	 * @param string $keygroup
	 * @param string ...$components
	 * @return string The global token key (with proper escaping)
	 */
	public function makeTokenKey( string $keygroup, ...$components ): string {
		// FIXME remove callers and make private
		return $this->getTokenStore()->makeGlobalKey(
			$keygroup, $this->getCentralAuthDBForSessionKey(), ...$components
		);
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
	 * @param string $value The value to store.
	 * @param string $keyPrefix Namespace in the token store.
	 * @return string The random key (without the prefix).
	 */
	public function tokenize(
		string $value,
		string $keyPrefix
	): string {
		$token = MWCryptRand::generateHex( 32 );
		$key = $this->makeTokenKey( $keyPrefix, $token );
		$this->getTokenStore()->set( $key, $value, ExpirationAwareness::TTL_MINUTE );
		return $token;
	}

	/**
	 * Recover the value concealed with tokenize().
	 * @param string $token The random key returned by tokenize().
	 * @param string $keyPrefix Namespace in the token store.
	 * @return string|false The value, or false if it was not found.
	 */
	public function detokenize(
		string $token,
		string $keyPrefix
	) {
		$key = $this->makeTokenKey( $keyPrefix, $token );
		return $this->getKeyValueUponExistence( $key );
	}

	/**
	 * Wait for and return the value of a key which is expected to exist from a store
	 *
	 * @param string $key A key that will only have one value while it exists
	 * @param int $timeout
	 * @return mixed Key value; false if not found or on error
	 */
	public function getKeyValueUponExistence( $key, $timeout = 3 ) {
		// FIXME make this private
		$value = false;

		$result = ( new WaitConditionLoop(
			function () use ( $key, &$value ) {
				$store = $this->getTokenStore();
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
			$this->logger->error( "Expected key {key} not found due to timeout.", [ 'key' => $key ] );
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
