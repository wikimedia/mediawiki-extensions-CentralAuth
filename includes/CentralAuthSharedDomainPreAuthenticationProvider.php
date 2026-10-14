<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler;
use StatusValue;

/**
 * Helper for SharedDomainHookHandler to persist state across multiple authentication requests.
 *
 * @see SharedDomainHookHandler::onAuthManagerFilterProviders()
 * @see SharedDomainHookHandler::onAuthManagerVerifyAuthentication()
 */
class CentralAuthSharedDomainPreAuthenticationProvider extends AbstractPreAuthenticationProvider {

	public function __construct(
		private readonly FilteredRequestTracker $filteredRequestTracker,
	) {
	}

	/** @inheritDoc */
	public function testForAuthentication( array $reqs ) {
		$this->filteredRequestTracker->saveState( $this->manager );
		return StatusValue::newGood();
	}

	/** @inheritDoc */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$this->filteredRequestTracker->saveState( $this->manager );
		return StatusValue::newGood();
	}

}
