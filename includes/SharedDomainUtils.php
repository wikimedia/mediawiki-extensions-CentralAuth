<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use RuntimeException;
use Wikimedia\Assert\Assert;

/**
 * Utilities for handling the shared domain name used for SUL3 login.
 * This class is kept lightweight, so it can be used in early hooks.
 */
class SharedDomainUtils {

	private Config $config;
	private SpecialPageFactory $specialPageFactory;
	private HookRunner $hookRunner;
	private ?bool $isSharedDomain = null;
	private ?MobileContext $mobileContext;
	private bool $isApiRequest;

	public function __construct(
		Config $config,
		SpecialPageFactory $specialPageFactory,
		HookRunner $hookRunner,
		?MobileContext $mobileContext,
		bool $isApiRequest
	) {
		$this->config = $config;
		$this->specialPageFactory = $specialPageFactory;
		$this->hookRunner = $hookRunner;
		$this->mobileContext = $mobileContext;
		$this->isApiRequest = $isApiRequest;
	}

	/**
	 * Return the URL prefix for using the shared authentication domain. The server will
	 * handle a request to this URL mostly the same as a request to the specified wiki.
	 * The returned prefix is equivalent to $wgCanonicalServer in how it should be used to
	 * construct URLs.
	 *
	 * @param ?string $wikiId The ID of the wiki which should be used for configuration when the
	 *   shared domain URL is processed. Defaults to the current wiki ID.
	 * @return string|null URL prefix for the shared authentication domain, without a trailing
	 *   slash; or null if the shared domain is not configured.
	 */
	public function getSharedDomainPrefix( ?string $wikiId = null ): ?string {
		$wikiId ??= WikiMap::getCurrentWikiId();
		$sharedDomainCallback = $this->config->get( CAMainConfigNames::CentralAuthSharedDomainCallback );
		return $sharedDomainCallback ? $sharedDomainCallback( $wikiId ) : null;
	}

	/**
	 * Whether the current request is to the shared domain used for SUL3 login.
	 *
	 * This assumes:
	 * - $wgCentralAuthSharedDomainCallback contains the shared domain.
	 * - $wgCanonicalServer is set in site configuration to the current domain
	 *   (instead of the actual canonical domain) for requests to the shared domain.
	 */
	public function isSharedDomain(): bool {
		if ( $this->isSharedDomain === null ) {
			$sharedDomainPrefix = $this->getSharedDomainPrefix();
			if ( !$sharedDomainPrefix ) {
				$this->isSharedDomain = false;
			} else {
				$sharedDomain = parse_url( $sharedDomainPrefix, PHP_URL_HOST );
				$currentDomain = parse_url(
					$this->config->get( MainConfigNames::CanonicalServer ), PHP_URL_HOST
				);
				$this->isSharedDomain = $sharedDomain && $currentDomain === $sharedDomain;
			}
		}
		return $this->isSharedDomain;
	}

	/**
	 * Whether the current request must deny non-auth actions.
	 *
	 * If $wgCentralAuthRestrictSharedDomain is enabled, then requests to the "fake"
	 * shared domain within $wgCentralAuthSharedDomainCallback must only be for authentication
	 * purposes. All non-authentication-related actions should be prevented.
	 *
	 * SUL3 login supports both using a dedicated login wiki for the domain where the central
	 * session cookies are stored, and a shared domain which serve any wiki (from a virtual
	 * subdirectory). In the latter case, we want to prevent non-authentication actions
	 * to prevent complications like cache splits. This flag differentiates between the two
	 * setups.
	 *
	 * @return bool
	 * @see SharedDomainHookHandler
	 */
	public function shouldRestrictCurrentDomain(): bool {
		return $this->isSharedDomain() && $this->config->get( CAMainConfigNames::CentralAuthRestrictSharedDomain );
	}

	/**
	 * Whether the wiki supports SUL3 at all. This does not necessarily mean SUL3 is enabled on
	 * the wiki; but at a minimum, when this function is true, it can be enabled for a given
	 * request by using the usesul3=1 URL parameter.
	 *
	 */
	public function canSul3BeEnabled(): bool {
		return (bool)$this->config->get( CAMainConfigNames::CentralAuthSharedDomainCallback );
	}

	/**
	 * Whether SUL3 mode is enabled on this wiki and/or this request.
	 *
	 * In order to facilitate testing and rollout of SUL3 migration,
	 * this method provides mechanisms for testing the SUL3 feature.
	 *
	 * SUL3 mode is available if $wgCentralAuthSharedDomainCallback is configured.
	 * It's enabled or disabled by default using $wgCentralAuthEnableSul3,
	 * which can be overridden by the user by setting the 'usesul3'
	 * URL query parameter to "1" or "0" (or other fuzzy bool values).
	 *
	 * @param WebRequest $request
	 * @param bool $excludeApiRequest Whether to exclude this API request from the
	 *    SUL3 mode.
	 *
	 * @return bool
	 */
	public function isSul3Enabled( WebRequest $request, bool $excludeApiRequest = true ): bool {
		if ( !$this->canSul3BeEnabled() ) {
			return false;
		}

		// T379816: The `clientlogin` API should still work in SUL3 mode as if
		//     we're in SUL2 mode regardless of whether SUL3 is enabled or not.
		//     There are some edge-cases handled below like:
		//       - edits coming from VisualEditor that will trigger CentralLogin
		//         via the action API. Shouldn't really happen because we don't
		//         have VE enabled for anon users in production today but let's
		//         handle these;
		//       - a user trying to authenticate (login/signup) with their permanent
		//         account with a temporary account session active.
		// T384523, T383812: Users sometimes will try to authenticate (login/signup)
		//     with an existing temporary session active. When this happens, we want
		//     to still assume SUL2 mode rather than try to trigger SUL3 login flow.
		//     This can happen for mobile apps (iOS for example) users.
		// T390751: There is a special case where we want to create an account on-behalf
		//     of another user and mail them the temp password. In this case, the user
		//     that is creating the account is authenticated (API or regular web request).
		//     So in all cases, where we're using the API, regardless of the user (temp,
		//     anon or named), we want to just disable SUL3 and fallback to SUL2.
		if ( $this->isApiRequest && $excludeApiRequest && !$this->isSharedDomain() ) {
			return false;
		}

		if ( $request->getCheck( 'usesul3' ) ) {
			return $request->getFuzzyBool( 'usesul3' );
		}

		return (bool)$this->config->get( CAMainConfigNames::CentralAuthEnableSul3 );
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

	/**
	 * Get the login/signup URL on the shared domain in SUL3 mode. Should only be called during
	 * authentication.
	 *
	 * @note This utility method supports URLs for SUL3 mode only. It
	 *       doesn't have any concept of SUL2 mode. For shared domain
	 *       URLs that support SUL2 and SUL3, see get the generic method
	 *       CentralDomainUtils::getCentralDomainURL().
	 *
	 * @param string $action 'login' or 'signup' action
	 * @param WebRequest $request There could be more to look at
	 *    in the request like if we're coming from a campaign link.
	 *
	 * @return string
	 */
	public function getUrlForSharedDomainAction( string $action, WebRequest $request ): string {
		switch ( $action ) {
			case 'login':
				$localUrl = $this->specialPageFactory->getTitleForAlias( 'Userlogin' )->getLocalURL();
				break;
			case 'signup':
				$localUrl = $this->specialPageFactory->getTitleForAlias( 'CreateAccount' )->getLocalURL();
				break;
			default:
				throw new RuntimeException( 'Unknown action: ' . $action );
		}

		$sharedDomainPrefix = $this->getSharedDomainPrefix();
		if ( !$sharedDomainPrefix ) {
			throw new RuntimeException( 'SUL3 action used but $wgCentralAuthSharedDomainCallback not configured' );
		}
		$url = $this->makeUrlDeviceCompliant( $sharedDomainPrefix . $localUrl );

		$params = [];
		$this->hookRunner->onAuthPreserveQueryParams( $params, [ 'request' => $request ] );
		// replicate the non-hook part of LoginSignupSpecialPage::getPreservedParams()
		$params += [
			'display' => $request->getRawVal( 'display' ),
			'uselang' => $request->getRawVal( 'uselang' ),
			'variant' => $request->getRawVal( 'variant' ),
			'returnto' => $request->getRawVal( 'returnto' ),
			'returntoquery' => $request->getRawVal( 'returntoquery' ),
			'returntoanchor' => $request->getRawVal( 'returntoanchor' ),
			'redoLocalAuthentication' => $request->getRawVal( 'redoLocalAuthentication' ),
		];
		// already handled in makeUrlDeviceCompliant()
		unset( $params['useformat'] );

		// Make sure SUL3 opt-in state is preserved, e.g. in case of the user changing networks
		$params['usesul3'] = '1';

		// Skip the login form if the user is already logged in on the central domain.
		// See LoginSignupSpecialPage: "In the case where the user is already logged in"...
		if ( ( $params['returnto'] ?? '' ) === '' && ( $params['returntoquery'] ?? '' ) === '' ) {
			// This is enough to pass the check in LoginSignupSpecialPage and make sure
			// PostLoginRedirect is called even when the user is already logged in.
			$params['returntoquery'] = '?';
			// In the future maybe we'll want to use a more robust redirection mechanism instead of
			// relying on PostLoginRedirect (see also T369467).
		}

		return wfAppendQuery( $url, $params );
	}

	/**
	 * @return bool True if on mobile device
	 */
	public function shouldUseMobile(): bool {
		return $this->mobileContext && $this->mobileContext->shouldDisplayMobileView();
	}

	/**
	 * Check the URL and apply transformation based on the device
	 * that is currently looking at it. If mobile, apply the mobile
	 * transformation to the URL so we view the correct rendering.
	 *
	 * Get the mobile domain (m.) version of the URL if available
	 * configured (in that WMF is currently configured to have separate
	 * domain for mobile and desktop versions of sites) and we want that
	 * instead of just appending a `useformat` query parameter, if the
	 * domain is a mobile domain, just return it but if it's not, we
	 * detect that and append a `useformat` query param..
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function makeUrlDeviceCompliant( string $url ): string {
		// Assume either all or none of the wikis in the farm have MobileFrontend
		if ( !$this->mobileContext ) {
			return $url;
		}

		$mobileUrl = $this->mobileContext->getMobileUrl( $url );
		// Some wikis don't have separate mobile and desktop versions at different URLs,
		// in which case getMobileUrl() is a no-op.
		$hasMobileUrl = ( $mobileUrl !== $url );

		if ( $this->mobileContext->shouldDisplayMobileView() ) {
			return $hasMobileUrl ? $mobileUrl : wfAppendQuery( $url, [ 'useformat' => 'mobile' ] );
		} else {
			// useformat=desktop is the default, and so we don't really need to set it,
			// but we want to consider the possibility that the user has previously used
			// the central domain and set it to mobile mode via a cookie. In that case,
			// we want to prioritize the consistency of the current mode over that setting.
			return $hasMobileUrl ? $url : wfAppendQuery( $url, [ 'useformat' => 'desktop' ] );
		}
	}

}
