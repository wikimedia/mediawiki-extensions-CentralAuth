<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\MediaWikiServices;
use MWCryptRand;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Assert\Assert;
use Wikimedia\ObjectCache\BagOStuff;

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
		$token = $options['token'] ?? MWCryptRand::generateHex( 32 );
		$key = $this->makeLegacyTokenKey( $keyPrefix, $token );
		$this->tokenStore->set( $key, $data, BagOStuff::TTL_MINUTE );
		return $token;
	}

	/**
	 * Recover the value concealed with tokenize().
	 *
	 * The value is left in the store. It is the caller's responsibility to prevent replay attacks.
	 *
	 * @param string $token The random key returned by tokenize().
	 * @param string|array $keyPrefix Namespace in the token store.
	 * @return mixed|false The value, or false if it was not found.
	 */
	public function detokenize( string $token, $keyPrefix ) {
		return $this->tokenStore->get( $this->makeLegacyTokenKey( $keyPrefix, $token ) );
	}

	/**
	 * Recover the value concealed with tokenize(), and delete it from the store.
	 *
	 * @param string $token The random key returned by tokenize().
	 * @param string|array $keyPrefix Namespace in the token store.
	 * @return mixed|false The value, or false if it was not found.
	 */
	public function detokenizeAndDelete( string $token, $keyPrefix ) {
		$key = $this->makeLegacyTokenKey( $keyPrefix, $token );
		$value = $this->detokenize( $token, $keyPrefix );
		if ( $value !== false ) {
			$this->tokenStore->delete( $key );
		}
		return $value;
	}

	/**
	 * @return string Database name, for session key creation
	 *
	 * NOTE: If there is more than one CentralAuth database
	 * in use for the same session key store, the database names
	 * MUST be unique.
	 */
	private function getCentralAuthDBForSessionKey() {
		return MediaWikiServices::getInstance()
			->getDBLoadBalancerFactory()->getPrimaryDatabase( 'virtual-centralauth' )->getDomainID();
	}

}
