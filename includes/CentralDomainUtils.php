<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use RuntimeException;
use Wikimedia\NormalizedException\NormalizedException;

/**
 * Utilities for handling the central domain (during SUL2 login, the central login
 * wiki; during SUL3 login, the shared login domain).
 */
class CentralDomainUtils {
	/**
	 * @internal
	 * Pseudo-wiki-ID for the CentralDomainUtils::getUrl() method.
	 *
	 * Resolves to a central domain:
	 * - In SUL2 mode, the central login wiki.
	 * - In SUL3 mode, the current wiki on the shared login domain. Titles are localized.
	 */
	public const CENTRAL_DOMAIN_ID = '#central#';

	/**
	 * @internal
	 * Pseudo-wiki-ID for the CentralDomainUtils::getUrl() method.
	 *
	 * Resolves to a central domain suitable for autologin (see T387357):
	 * - In SUL2 mode, the central login wiki.
	 * - In SUL3 mode, the central login wiki on the shared login domain. Titles are not localized.
	 */
	public const AUTOLOGIN_CENTRAL_DOMAIN_ID = '#autologin_central#';

	/**
	 * @internal
	 * Pseudo-wiki-ID for the CentralDomainUtils::getUrl() method.
	 *
	 * Resolves to the SUL2 central login wiki.
	 */
	public const SUL2_CENTRAL_DOMAIN_ID = '#sul2-central#';

	/**
	 * @internal
	 * Pseudo-wiki-ID for the CentralDomainUtils::getUrl() method.
	 *
	 * Resolves to the central login wiki on the SUL3 shared login domain. Titles are not localized.
	 * This is suitable for autologin, same as CentralDomainUtils::AUTOLOGIN_CENTRAL_DOMAIN_ID.
	 */
	public const SUL3_CENTRAL_DOMAIN_ID = '#sul3-central#';

	private Config $config;
	private TitleFactory $titleFactory;
	private SharedDomainUtils $sharedDomainUtils;

	/** Wiki where the user has a logged-in session. Only used when loginwiki isn't configured. */
	private ?string $fallbackLoginWikiId = null;

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
	 * Returns a URL for the given wiki and page.
	 *
	 * The URL is guaranteed to have the following properties:
	 * - Preserves the current mobile mode, even if the target wiki does not have a mobile mode (so
	 *   that at the end of the redirect chain the user is still in the same mode they started with).
	 * - Uses a nice URL (no index.php). This simplifies URL pattern recognition at the edge cache.
	 * - When using CENTRAL_DOMAIN_ID or PASSIVE_CENTRAL_DOMAIN_ID, and it resolves to the SUL3
	 *   domain, the namespace will be formatted like in a local URL.
	 *   In all other cases, it uses the page name as it was passed (does not localize namespaces).
	 *   This can be important for identifying authentication-related cross-wiki requests at the
	 *   edge cache level.
	 *
	 * @param string $wikiId Wiki ID or CentralDomainUtils pseudo-wiki-ID
	 * @param string $page Title of the page the URL should point to; must be the normalized,
	 *   canonical form.
	 * @param WebRequest $request
	 * @param array $params Query parameters to apply to the URL.
	 * @return string
	 * @see CentralAuthHooks::onTestCanonicalRedirect()
	 */
	public function getUrl( string $wikiId, string $page, WebRequest $request, array $params = [] ): string {
		if ( $wikiId === self::CENTRAL_DOMAIN_ID
			|| $wikiId === self::AUTOLOGIN_CENTRAL_DOMAIN_ID
			|| $wikiId === self::SUL2_CENTRAL_DOMAIN_ID
			|| $wikiId === self::SUL3_CENTRAL_DOMAIN_ID
		) {
			if ( $wikiId === self::SUL2_CENTRAL_DOMAIN_ID ) {
				$useSul3Domain = false;
			} else {
				$useSul3Domain = $this->sharedDomainUtils->isSul3Enabled( $request ) ||
					$wikiId === self::SUL3_CENTRAL_DOMAIN_ID;
			}

			if ( $useSul3Domain ) {
				$sharedDomainWikiId = null;
				if ( $wikiId === self::AUTOLOGIN_CENTRAL_DOMAIN_ID || $wikiId === self::SUL3_CENTRAL_DOMAIN_ID ) {
					$sharedDomainWikiId = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
						?? WikiMap::getCurrentWikiId();

					// The shared domain will parse this URL with the configuration of a different
					// wiki, possibly in a different language; everything must be canonical.
					$normalUrl = WikiMap::getWiki( $sharedDomainWikiId )->getCanonicalUrl( $page );
					$parts = parse_url( $normalUrl );
					$localUrl = wfAppendQuery( $parts['path'] ?? '', $parts['query'] ?? '' );
				} else {
					// The shared domain will parse this URL with the configuration of the current
					// wiki; use a localized title to spare an extra redirect.
					$localUrl = $this->titleFactory->newFromText( $page )->getLocalURL();
				}
				$sharedDomainPrefix = $this->sharedDomainUtils->getSharedDomainPrefix( $sharedDomainWikiId );
				if ( !$sharedDomainPrefix ) {
					throw new RuntimeException( 'SUL3 enabled but $wgCentralAuthSharedDomainCallback not set' );
				}
				$url = $sharedDomainPrefix . $localUrl;
			} else {
				$centralWikiId = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
					?? $this->fallbackLoginWikiId;
				$url = $this->getWikiPageUrl( $centralWikiId, $page );
			}
		} else {
			$url = $this->getWikiPageUrl( $wikiId, $page );
		}

		return wfAppendQuery( $this->sharedDomainUtils->makeUrlDeviceCompliant( $url ), $params );
	}

	private function getWikiPageUrl( string $wikiId, string $page ): string {
		$wiki = WikiMap::getWiki( $wikiId );
		if ( $wiki ) {
			return $wiki->getCanonicalUrl( $page );
		} else {
			throw new NormalizedException( __METHOD__ . ': Invalid wiki ID: {wikiId}',
				[ 'wikiId' => $wikiId ] );
		}
	}

	/**
	 * Check if we're on the central domain.
	 * In SUL2 mode, this means the current wiki is $wgCentralAuthLoginWiki (or the fallback).
	 * In SUL3 mode, this means we're using the shared domain.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public function isCentralDomain( WebRequest $request ): bool {
		$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
			?? $this->fallbackLoginWikiId;

		return ( !$this->sharedDomainUtils->isSul3Enabled( $request ) && WikiMap::getCurrentWikiId() === $loginWiki )
			|| ( $this->sharedDomainUtils->isSul3Enabled( $request ) && $this->sharedDomainUtils->isSharedDomain() );
	}

	/**
	 * Check if we are either on the SUL2 or the SUL3 central domain, ignoring which is the
	 * "correct" one for the user's SUL3 opt-in flag.
	 */
	public function isActiveOrPassiveCentralDomain( WebRequest $request ): bool {
		$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
			?? $this->fallbackLoginWikiId;
		return WikiMap::getCurrentWikiId() === $loginWiki || $this->sharedDomainUtils->isSharedDomain();
	}

	/**
	 * Checks if we have a central domain for the user currently performing
	 * the request. Central domain is either the central login wiki in SUL2
	 * or the shared domain in SUL3 mode.
	 *
	 * @param WebRequest $request
	 *
	 * @return bool
	 */
	public function centralDomainExists( WebRequest $request ): bool {
		if ( $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
			return true;
		}
		$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
			?? $this->fallbackLoginWikiId;
		return (bool)$loginWiki;
	}

	/**
	 * @param WebRequest $request
	 *
	 * @return string The canonical server of the central domain
	 */
	public function getCentralDomainHost( WebRequest $request ): string {
		$loginWikiId = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki )
			?? $this->fallbackLoginWikiId;
		$sharedDomainUrl = $this->sharedDomainUtils->getSharedDomainPrefix();

		if ( $sharedDomainUrl && $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
			return parse_url( $sharedDomainUrl, PHP_URL_HOST );
		} elseif ( $loginWikiId ) {
			$centralDomainUrl = WikiMap::getWiki( $loginWikiId )->getCanonicalServer();
			return parse_url( $centralDomainUrl, PHP_URL_HOST );
		}

		// If we're hitting this, then something is likely wrong with our configuration. SUL2 should set
		// $wgCentralAuthLoginWiki or SUL3 should set $wgCentralAuthSharedDomainCallback.
		throw new \RuntimeException( __METHOD__ . " must not be called when there is no central domain" );
	}

	/**
	 * Get a new instance of CentralDomainUtils with a fallback login wiki ID set.
	 * @param string $fallbackLoginWikiId A wiki where the user has a logged-in session.
	 *   Used as fallback when loginwiki isn't configured.
	 */
	public function withFallbackLoginWikiId( string $fallbackLoginWikiId ): self {
		$centralDomainUtilsWithFallback = new self( $this->config, $this->titleFactory,
			$this->sharedDomainUtils );
		$centralDomainUtilsWithFallback->fallbackLoginWikiId = $fallbackLoginWikiId;
		return $centralDomainUtilsWithFallback;
	}

}
