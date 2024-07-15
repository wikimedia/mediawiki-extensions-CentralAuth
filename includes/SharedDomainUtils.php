<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Utils\UrlUtils;

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

}
