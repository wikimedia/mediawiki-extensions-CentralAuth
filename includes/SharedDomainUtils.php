<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SsoHookHandler;
use MediaWiki\Request\WebRequest;
use Wikimedia\Assert\Assert;

/**
 * Utilities for handling the shared domain name used for SUL3 login.
 * This class is kept lightweight, so it can be used in early hooks.
 */
class SharedDomainUtils {

	private Config $config;

	private bool $isSharedDomain;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * Whether the current request is to the shared domain used for SUL3 login.
	 *
	 * This assumes:
	 * - $wgCentralAuthSsoUrlPrefix contains the shared domain.
	 * - $wgCanonicalServer is set in site configuration to the current domain
	 *   (instead of the actual canonical domain) for requests to the shared domain.
	 *
	 * @return bool
	 */
	public function isSharedDomain(): bool {
		// @phan-suppress-next-line PhanRedundantCondition
		if ( !isset( $this->isSharedDomain ) ) {
			$centralAuthSsoUrlPrefix = $this->config->get( 'CentralAuthSsoUrlPrefix' );
			if ( !$centralAuthSsoUrlPrefix ) {
				$this->isSharedDomain = false;
			} else {
				$sharedDomain = parse_url( $centralAuthSsoUrlPrefix, PHP_URL_HOST );
				$currentDomain = parse_url( $this->config->get( 'CanonicalServer' ), PHP_URL_HOST );
				$this->isSharedDomain = $sharedDomain && $currentDomain === $sharedDomain;
			}
		}
		return $this->isSharedDomain;
	}

	/**
	 * Whether the current request must deny non-auth actions.
	 *
	 * If $wgCentralAuthRestrictSsoDomain is enabled, then requests to the "fake"
	 * shared domain within $wgCentralAuthSsoUrlPrefix must only be for authentication
	 * purposes. All non-authentication-related actions should be prevented.
	 *
	 * SUL3 login supports both using a dedicated login wiki for the domain where the central
	 * session cookies are stored, and a shared domain which serve any wiki (from a virtual
	 * sub directory). In the latter case, we want to prevent non-authentication actions
	 * to prevent complications like cache splits. This flag differentiates between the two
	 * setups.
	 *
	 * @return bool
	 * @see SsoHookHandler
	 */
	public function shouldRestrictCurrentDomain(): bool {
		return $this->isSharedDomain() && $this->config->get( 'CentralAuthRestrictSsoDomain' );
	}

	/**
	 * Whether SUL3 mode is enabled on this wiki and this request.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public function isSul3Enabled( WebRequest $request ): bool {
		$configFlag = $this->config->get( 'CentralAuthEnableSul3' );
		if ( $configFlag === 'always' ) {
			return true;
		} elseif ( $configFlag === 'query-flag' ) {
			return $request->getCheck( 'usesul3' );
		} else {
			return false;
		}
	}

	/**
	 * Assert that the SUL3 mode is allowed.
	 *
	 * @param WebRequest $request
	 * @return void
	 */
	public function assertSul3Enabled( WebRequest $request ) {
		Assert::precondition(
			$this->isSul3Enabled( $request ),
			'SUL3 is not enabled. Set $wgCentralAuthEnableSul3 to boolean true.'
		);
	}

	/**
	 * Assert that we're on the shared login domain.
	 *
	 * @return void
	 */
	public function assertIsSharedDomain() {
		Assert::precondition(
			$this->isSharedDomain(),
			'This action is not allowed because the domain is not the shared login domain.'
		);
	}

	/**
	 * Assert that we're not on the shared login domain.
	 *
	 * @return void
	 */
	public function assertIsNotSharedDomain() {
		Assert::precondition(
			!( $this->isSharedDomain() ),
			'This action is not allowed because the domain is not the shared login domain.'
		);
	}

}
