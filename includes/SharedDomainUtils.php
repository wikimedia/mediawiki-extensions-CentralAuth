<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SsoHookHandler;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
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
	private bool $isSharedDomain;
	private ?MobileContext $mobileContext = null;

	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		?MobileContext $mobileContext = null
	) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->mobileContext = $mobileContext;
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
	 *   a query parameter 'usesul3' with a truthy value
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public function isSul3Enabled( WebRequest $request ): bool {
		$sul3Config = $this->config->get( 'CentralAuthEnableSul3' );

		if ( in_array( 'always', $sul3Config, true ) ) {
			return true;
		} elseif ( in_array( 'cookie', $sul3Config, true )
			&& $request->getCookie( self::SUL3_COOKIE_FLAG, '' ) === '1'
		) {
			return true;
		} elseif ( in_array( 'query-flag', $sul3Config, true )
			&& $request->getCheck( 'usesul3' )
		) {
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

		$url = $this->config->get( 'CentralAuthSsoUrlPrefix' ) . $localUrl;

		if ( $this->mobileContext && $this->mobileContext->shouldDisplayMobileView() ) {
			$url = wfAppendQuery( $url, [ 'useformat' => 'mobile' ] );
		} else {
			// This is not supposed to happen on the SSO domain but if we're
			// in a situation where the shared domain is in mobile view and the
			// user is coming from a desktop view, let's inherit that experience
			// to the shared domain.
			$url = wfAppendQuery( $url, [ 'useformat' => 'desktop' ] );
		}

		return wfAppendQuery( $url, [
			// At this point, we should just be leaving the local
			// wiki before hitting the loginwiki.
			'wikiid' => WikiMap::getCurrentWikiId(),
			// TODO: Fix T369467
			'returnto' => 'Main_Page',
			'usesul3' => '1',
			'campaign' => $request ? $request->getRawVal( 'campaign' ) : null,
		] );
	}

}
