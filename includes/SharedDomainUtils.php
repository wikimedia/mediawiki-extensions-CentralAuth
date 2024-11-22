<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MobileContext;
use RuntimeException;
use Wikimedia\Assert\Assert;

/**
 * Utilities for handling the shared domain name used for SUL3 login.
 * This class is kept lightweight, so it can be used in early hooks.
 */
class SharedDomainUtils {

	private const SUL3_COOKIE_FLAG = 'sul3OptIn';
	private Config $config;
	private TitleFactory $titleFactory;
	private ?bool $isSharedDomain = null;
	private ?MobileContext $mobileContext;
	private bool $isApiRequest;

	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		?MobileContext $mobileContext,
		bool $isApiRequest
	) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->mobileContext = $mobileContext;
		$this->isApiRequest = $isApiRequest;
	}

	/**
	 * Whether the current request is to the shared domain used for SUL3 login.
	 *
	 * This assumes:
	 * - $wgCentralAuthSharedDomainPrefix contains the shared domain.
	 * - $wgCanonicalServer is set in site configuration to the current domain
	 *   (instead of the actual canonical domain) for requests to the shared domain.
	 *
	 * @return bool
	 */
	public function isSharedDomain(): bool {
		if ( $this->isSharedDomain === null ) {
			$sharedDomainPrefix = $this->config->get( CAMainConfigNames::CentralAuthSharedDomainPrefix );
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
	 * shared domain within $wgCentralAuthSharedDomainPrefix must only be for authentication
	 * purposes. All non-authentication-related actions should be prevented.
	 *
	 * SUL3 login supports both using a dedicated login wiki for the domain where the central
	 * session cookies are stored, and a shared domain which serve any wiki (from a virtual
	 * sub directory). In the latter case, we want to prevent non-authentication actions
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
	 * Whether SUL3 mode is enabled on this wiki and/or this request.
	 *
	 * In order to facilitate testing of SUL3 migration, this method
	 * provides mechanisms for testing the SUL3 feature including a
	 * cookie-based feature flag.
	 *
	 * SUL3 mode is enabled if any of the following conditions is true:
	 * - $wgCentralAuthEnableSul3 contains 'always'
	 * - $wgCentralAuthEnableSul3 contains 'cookie' and there is a
	 *   cookie named 'sul3OptIn' with the value '1'
	 * - $wgCentralAuthEnableSul3 contains 'query-flag' and the URL has
	 *   a query parameter 'usesul3' with the value "1". The value "0"
	 *   means switch off SUL3 mode.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public function isSul3Enabled( WebRequest $request ): bool {
		// T379816: `clientlogin` API should still work in SUL3 mode as if we're
		//    in SUL2 mode regardless of whether SUL3 is enabled or not. This provider
		//    should operate the same in both modes when the request is an API request.
		if ( $this->isApiRequest && !$this->isSharedDomain() ) {
			return false;
		}

		$sul3Config = $this->config->get( CAMainConfigNames::CentralAuthEnableSul3 );

		if ( in_array( 'query-flag', $sul3Config, true )
			&& $request->getCheck( 'usesul3' )
		) {
			return $request->getFuzzyBool( 'usesul3' );
		} elseif ( in_array( 'cookie', $sul3Config, true )
			&& $request->getCookie( self::SUL3_COOKIE_FLAG, '' ) === '1'
		) {
			return true;
		} elseif ( in_array( 'always', $sul3Config, true ) ) {
			return true;
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

	/**
	 * Get the login/signup URL on the shared login domain wiki.
	 *
	 * @param string $action 'login' or 'signup' action
	 * @param WebRequest|null $request There could be more to look at
	 *    in the request like if we're coming from a campaign link.
	 *
	 * @return string
	 */
	public function getUrlForSharedDomainAction( string $action, ?WebRequest $request = null ): string {
		switch ( $action ) {
			case 'login':
				$localUrl = $this->titleFactory->newFromText( 'Special:UserLogin' )->getLocalURL();
				break;
			case 'signup':
				$localUrl = $this->titleFactory->newFromText( 'Special:CreateAccount' )->getLocalURL();
				break;
			default:
				throw new RuntimeException( 'Unknown action: ' . $action );
		}

		$url = $this->makeUrlDeviceCompliant(
			$this->config->get( CAMainConfigNames::CentralAuthSharedDomainPrefix ) . $localUrl
		);

		return wfAppendQuery( $url, [
			// TODO: Fix T369467
			'returnto' => 'Main_Page',
			'usesul3' => '1',
			'campaign' => $request ? $request->getRawVal( 'campaign' ) : null,
		] );
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
