<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SsoHookHandler;
use MediaWiki\Request\WebRequest;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\Assert\Assert;

/**
 * Utilities for handling the shared domain name used for SUL3 login.
 * This class is kept lightweight, so it can be used in early hooks.
 */
class SharedDomainUtils {

	private Config $config;
	private UrlUtils $urlUtils;

	private bool $isSharedDomain;

	public function __construct(
		Config $config,
		UrlUtils $urlUtils
	) {
		$this->config = $config;
		$this->urlUtils = $urlUtils;
	}

	/**
	 * Check if the current domain is the shared domain used for SUL3 login
	 * (i.e. the domain in $CentralAuthSsoUrlPrefix). Assumes that $wgCanonicalServer
	 * is set to the shared domain when the wiki is accessed via that domain.
	 * @return bool
	 */
	public function isSharedDomain(): bool {
		// @phan-suppress-next-line PhanRedundantCondition
		if ( isset( $this->isSharedDomain ) ) {
			return $this->isSharedDomain;
		}

		$centralAuthSsoUrlPrefix = $this->config->get( 'CentralAuthSsoUrlPrefix' );
		if ( !$centralAuthSsoUrlPrefix ) {
			$this->isSharedDomain = false;
			return $this->isSharedDomain;
		}
		$sharedDomain = $this->urlUtils->parse( $centralAuthSsoUrlPrefix )['host'] ?? null;
		$currentDomain = $this->urlUtils->parse(
			$this->urlUtils->getServer( PROTO_CANONICAL ) ?? ''
		)['host'] ?? null;
		$this->isSharedDomain = $sharedDomain && $currentDomain === $sharedDomain;
		return $this->isSharedDomain;
	}

	/**
	 * True when the current domain is an authentication-only "fake" domain and all
	 * non-authentication-related actions should be prevented.
	 *
	 * SUL3 login supports both using a dedicated login wiki for the domain where the central
	 * session cookies are stored, and a shared domain which can be served by any wiki. In the
	 * latter case, we want to prevent non-authentication actions to prevent complications like
	 * cache splits. This flag differentiates between the two setups.
	 *
	 * @return bool
	 * @see SsoHookHandler
	 */
	public function shouldRestrictCurrentDomain(): bool {
		return $this->isSharedDomain() && $this->config->get( 'CentralAuthRestrictSsoDomain' );
	}

	/**
	 * Detects if we're in SUL3 mode. Returns true if that is the case
	 * and false otherwise.
	 *
	 * @param WebRequest $request
	 *
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
	 * Assert that the SUL3 mode is set.
	 *
	 * @param WebRequest $request
	 *
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
