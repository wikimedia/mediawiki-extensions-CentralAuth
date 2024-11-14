<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler;
use StatusValue;

/**
 * Helper for SharedDomainHookHandler to persist state across multiple authentication requests.
 *
 * @see SharedDomainHookHandler::onAuthManagerVerifyAuthentication()
 */
class CentralAuthSsoPreAuthenticationProvider extends AbstractPreAuthenticationProvider {

	private FilteredRequestTracker $filteredRequestTracker;

	public function __construct(
		FilteredRequestTracker $filteredRequestTracker
	) {
		$this->filteredRequestTracker = $filteredRequestTracker;
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
