<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;

/**
 * Utilities for handling the central domain (during SUL2 login, the central login
 * wiki; during SUL3 login, the shared login domain).
 */
class CentralDomainUtils {
	/**
	 * @internal
	 * Pseudo-wiki-ID for the central domain (the central login wiki
	 * in SUL2 mode, the shared login domain in SUL3 mode).
	 *
	 * Should only be passed to methods that explicitly document accepting it.
	 * @see CentralDomainUtils::getUrl()
	 */
	public const CENTRAL_DOMAIN_ID = '#central#';

	private Config $config;
	private TitleFactory $titleFactory;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/**
	 * Returns a URL for the given wiki and page. The URL will
	 * preserve the current mobile mode (determined via MobileContext
	 * or the presence of a 'mobile' flag in $params).
	 *
	 * @param string $wikiId Wiki ID or CENTRAL_DOMAIN_ID
	 * @param string $page Title of the page the URL should point to.
	 * @param WebRequest $request
	 * @param array $params Query parameters to apply to the URL.
	 * @return string
	 */
	public function getUrl( string $wikiId, string $page, WebRequest $request, array $params = [] ): string {
		if ( $wikiId === self::CENTRAL_DOMAIN_ID ) {
			if ( $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
				$localUrl = $this->titleFactory->newFromText( $page )->getLocalURL();
				$url = $this->config->get( CAMainConfigNames::CentralAuthSharedDomainPrefix ) . $localUrl;
			} else {
				$centralWikiId = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki );
				$url = WikiMap::getWiki( $centralWikiId )->getCanonicalUrl( $page );
			}
		} else {
			$url = WikiMap::getWiki( $wikiId )->getCanonicalUrl( $page );
		}

		return wfAppendQuery( $this->sharedDomainUtils->makeUrlDeviceCompliant( $url ), $params );
	}

	/**
	 * Check if we're on the central domain in SUL2 mode. $wgCentralAuthLoginWiki is
	 * setup to be the central login wiki in SUL2. Is the current wiki the central
	 * login wiki? Or on SUL3, we check if SUL3 mode is enabled and if we're on the
	 * shared domain. Otherwise, we're not on the central domain.
	 *
	 * @param WebRequest $request
	 * @param string|null $loginWiki
	 *
	 * @return bool
	 */
	public function isCentralDomain( WebRequest $request, ?string $loginWiki = null ): bool {
		if ( $loginWiki === null ) {
			$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki );
		}

		return ( !$this->sharedDomainUtils->isSul3Enabled( $request ) && WikiMap::getCurrentWikiId() === $loginWiki )
			|| ( $this->sharedDomainUtils->isSul3Enabled( $request ) && $this->sharedDomainUtils->isSharedDomain() );
	}

	/**
	 * Checks if we have a central domain for the user currently performing
	 * the request. Central domain is either the central login wiki in SUL2
	 * or the shared domain in SUL3 mode.
	 *
	 * @param WebRequest $webRequest
	 *
	 * @return bool
	 */
	public function centralDomainExists( WebRequest $webRequest ): bool {
		return $this->getLoginWikiId( $webRequest ) !== false;
	}

	/**
	 * In SUL2 mode, the login wiki will be $wgCentralAuthLoginWiki or false
	 * depending on the configuration but in SUL3 mode, this will be the fake
	 * central domain ID.
	 *
	 * @param WebRequest $request
	 *
	 * @return string|false
	 */
	public function getLoginWikiId( WebRequest $request ) {
		if ( $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
			return self::CENTRAL_DOMAIN_ID;
		}

		$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki );
		if ( $loginWiki ) {
			return $loginWiki;
		}

		return false;
	}

	/**
	 * @param WebRequest $request
	 *
	 * @return string The canonical server of the central domain
	 */
	public function getCentralDomainHost( WebRequest $request ): string {
		$loginWikiId = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki );
		$sharedDomainUrl = $this->config->get( CAMainConfigNames::CentralAuthSharedDomainPrefix );

		if ( $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
			return parse_url( $sharedDomainUrl, PHP_URL_HOST );
		} elseif ( $loginWikiId ) {
			$centralDomainUrl = WikiMap::getWiki( $loginWikiId )->getCanonicalServer();
			return parse_url( $centralDomainUrl, PHP_URL_HOST );
		}

		// If we're hitting this, then something is likely wrong with our configuration. SUL2 should set
		// $wgCentralAuthLoginWiki or SUL3 should set $wgCentralAuthSharedDomainPrefix
		throw new \RuntimeException( __METHOD__ . " must not be called when there is no central domain" );
	}

}
