<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Hook\ContentSecurityPolicyDefaultSourceHook;
use MediaWiki\Hook\ContentSecurityPolicyScriptSourceHook;
use MediaWiki\Utils\UrlUtils;

class ContentSecurityPolicyHookHandler implements
	ContentSecurityPolicyDefaultSourceHook,
	ContentSecurityPolicyScriptSourceHook
{

	private UrlUtils $urlUtils;
	private CentralDomainUtils $centralDomainUtils;

	public function __construct( UrlUtils $urlUtils, CentralDomainUtils $centralDomainUtils ) {
		$this->urlUtils = $urlUtils;
		$this->centralDomainUtils = $centralDomainUtils;
	}

	/**
	 * Add other domains as CSP source if auto-login is going to be attempted
	 *
	 * This is adding domains for images (and other req types), not scripts.
	 *
	 * @note We can't do $out->getCSP()->addDefaultSrc in onBeforePageDisplay,
	 * because that hook runs after the header is already outputted.
	 * @param string[] &$defaultSrc Array of allowed CSP sources.
	 * @param array $policyConfig
	 * @param int $mode
	 */
	public function onContentSecurityPolicyDefaultSource(
		&$defaultSrc,
		$policyConfig,
		$mode
	) {
		$out = RequestContext::getMain()->getOutput();

		// Allow-list edge login domains if an edge login is about to happen.
		if (
			// If this is an anonymous request, we need the allowlist because whether an autologin
			// (and then an edge login) happens depends on the CentralAuthAnon cookie and the
			// edge cache doesn't vary on that.
			// If we are logged in, we don't need to worry about caching, and can just check
			// whether an edge login is scheduled to happen on this request.
			!$out->getUser()->isRegistered() ||
				$out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' )
		) {
			foreach ( CentralAuthHooks::getAutoLoginWikis() as $wikiID ) {
				$url = $this->centralDomainUtils->getUrl( $wikiID, 'Special:BadTitle', $out->getRequest() );
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				$defaultSrc[] = $this->urlUtils->parse( $url )['host'];
			}
		}

		// Allow-list the central domain if autologin might be about to happen.
		// As with edge login, we can't rely on anything that caching doesn't vary on.
		if ( !$out->getUser()->isRegistered() &&
			$this->centralDomainUtils->centralDomainExists( $out->getRequest() )
		) {
			// For the non-js case, there is local image loaded, but it redirects to
			// central wiki, so include it.
			$defaultSrc[] = $this->centralDomainUtils->getCentralDomainHost( $out->getRequest() );
		}
	}

	/**
	 * Add other domains as CSP source if auto-login is going to be attempted
	 *
	 * This is adding domains for scripts but not images.
	 *
	 * This is basically for ext.centralauth.centralautologin and corresponds
	 * to self::getCentralautologinJsData
	 *
	 * @note We can't do $out->getCSP()->addScriptSrc() in onBeforePageDisplay,
	 * because that hook runs after the header is already outputted.
	 * @param string[] &$scriptSrc Array of allowed CSP sources.
	 * @param array $policyConfig
	 * @param int $mode
	 */
	public function onContentSecurityPolicyScriptSource(
		&$scriptSrc,
		$policyConfig,
		$mode
	) {
		$out = RequestContext::getMain()->getOutput();
		if ( $this->centralDomainUtils->centralDomainExists( $out->getRequest() ) &&
			!$out->getUser()->isRegistered()
		) {
			$scriptSrc[] = $this->centralDomainUtils->getCentralDomainHost( $out->getRequest() );
		}
	}
}
