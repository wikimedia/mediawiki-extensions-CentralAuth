<?php

namespace MediaWiki\Extension\CentralAuth;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MobileContext;
use RuntimeException;
use UnexpectedValueException;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;

/**
 * Utilities for handling the shared domain name used for SUL3 login.
 * This class is kept lightweight, so it can be used in early hooks.
 */
class SharedDomainUtils {

	public const SUL3_GLOBAL_PREF = 'centralauth-use-sul3';
	private const SUL3_COOKIE_FLAG = 'sul3OptIn';

	// used to mark an IP user for the SUL3 rollout
	public const SUL3_WANTED_COOKIE = 'sul3wanted';

	private Config $config;
	private TitleFactory $titleFactory;
	private HookRunner $hookRunner;
	private ?bool $isSharedDomain = null;
	private ?MobileContext $mobileContext;
	private bool $isApiRequest;
	/** @var GlobalPreferencesFactory|null|false */
	private $globalPreferencesFactory = false;
	/** @var callable():(?GlobalPreferencesFactory) */
	private $globalPreferencesFactoryFactory;
	private TempUserConfig $tempUserConfig;
	private array $userSUL3RolloutFlags = [];
	private array $noPrefsAvailable = [];

	public function __construct(
		Config $config,
		TitleFactory $titleFactory,
		HookRunner $hookRunner,
		?MobileContext $mobileContext,
		bool $isApiRequest,
		callable $globalPreferencesFactoryFactory,
		TempUserConfig $tempUserConfig
	) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->hookRunner = $hookRunner;
		$this->mobileContext = $mobileContext;
		$this->isApiRequest = $isApiRequest;
		$this->globalPreferencesFactoryFactory = $globalPreferencesFactoryFactory;
		$this->tempUserConfig = $tempUserConfig;
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
	 * In order to facilitate testing and rollout of SUL3 migration,
	 * this method provides mechanisms for testing the SUL3 feature
	 * and for per-wiki or percentage-based rollout, including a
	 * cookie-based feature flag.
	 *
	 * SUL3 mode is enabled if any of the following conditions is true:
	 * - $wgCentralAuthEnableSul3 contains 'always'
	 * - $wgCentralAuthEnableSul3 contains 'cookie' and there is a
	 *   cookie named 'sul3OptIn' with the value '1'
	 * - $wgCentralAuthEnableSul3 contains 'query-flag' and the URL has
	 *   a query parameter 'usesul3' with the value "1". The value "0"
	 *   means switch off SUL3 mode.
	 * - a global preference has been set for the user based on rollout
	 *   configuration settings.
	 * - $wgCentralAuthEnableSul3 contained 'always' for another wiki
	 *   and global preferences were set for the user on that basis,
	 *   keeping the login process consistent for each user... more or less.
	 *
	 * @param WebRequest $request
	 * @param bool|null &$isUnset Set to true if the SUL3 status is unset
	 *   (neither explicitly opted in nor opted out, so the user's cohort
	 *   still needs to be determined). The return value will be false in
	 *   that case.
	 * @return bool
	 */
	public function isSul3Enabled( WebRequest $request, &$isUnset = null ): bool {
		$isUnset = false;

		// T379816: The `clientlogin` API should still work in SUL3 mode as if
		//    we're in SUL2 mode regardless of whether SUL3 is enabled or not.
		//    There are some edge-cases handled below like:
		//       - edits coming from VisualEditor that will trigger CentralLogin
		//         via the action API. Shouldn't really happen because we don't
		//         have VE enabled for anon users in production today but let's
		//         handle these;
		//       - a user trying to authenticate (login/signup) with their permanent
		//         account with a temporary account session active.
		if ( $this->isApiRequest && !$this->isSharedDomain() ) {
			// T384523, T383812: Users sometimes will try to authenticate (login/signup)
			//     with an existing temporary session active. When this happens, we want
			//     to still assume SUL2 mode rather than try to trigger SUL3 login flow.
			//     This can happen for mobile apps (iOS for example) users.
			$user = $request->getSession()->getUser();
			if ( $user->isTemp() || $user->isAnon() ) {
				return false;
			}
		}

		$sul3Config = $this->config->get( CAMainConfigNames::CentralAuthEnableSul3 );
		$user = RequestContext::getMain()->getUser();

		if ( in_array( 'query-flag', $sul3Config, true )
			&& $request->getCheck( 'usesul3' )
		) {
			return $request->getFuzzyBool( 'usesul3' );
		} elseif ( in_array( 'cookie', $sul3Config, true )
			&& $request->getCookie( self::SUL3_COOKIE_FLAG, '' ) !== null
		) {
			return (bool)$request->getCookie( self::SUL3_COOKIE_FLAG, '' );
		} elseif ( in_array( 'always', $sul3Config, true ) ) {
			return true;
		}

		// don't do any looking at users and sessions and the like, if
		// we're not supposed to (note that User::getName() will
		// likely try to loadFromSession() which will explode otherwise)
		if ( !$user->isSafeToLoad() ) {
			// we don't really know whether the user has an unset status, let's go with yes
			$isUnset = true;
			return false;
		}

		// we have not gotten a rollout setting for the user previously.
		// determine one now if possible, from either an sul wanted cookie
		// or a global preference from a UserName cookie
		$config = RequestContext::getMain()->getConfig();

		if ( $config->get( CAMainConfigNames::Sul3RolloutSignupCookie ) ) {
			if ( self::hasSUL3WantedCookie( $request ) ) {
				return true;
			}
		}

		// get prefs the expected way for named users, but if the user is an IP
		// with a UserName cookie, we will get the prefs for the user from the cookie
		$flag = $this->getUserSUL3RolloutFlag( $user, $request );
		$isUnset = ( $flag === null );
		return (bool)$flag;
	}

	/**
	 * if the user is an IP, check for a UserName cookie (via session trickery)
	 * and return the user corresponding to that, if any. Otherwise return null
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @return User|null
	 */
	public static function getLastUser( $user, $request ) {
		$noUser = null;

		if ( !IPUtils::isIPAddress( $user->getName() ) ) {
			return $noUser;
		}

		$sessionUserName = $request->getSession()->suggestLoginUsername();
		if ( !$sessionUserName ) {
			return $noUser;
		}
		return User::newFromName( $sessionUserName ) ?: $noUser;
	}

	private function getGlobalPreferencesFactory(): ?GlobalPreferencesFactory {
		if ( $this->globalPreferencesFactory === false ) {
			$this->globalPreferencesFactory = ( $this->globalPreferencesFactoryFactory )();
		}
		return $this->globalPreferencesFactory;
	}

	/**
	 * try to retrieve global preferences for the user, using
	 * the session cookie UserName if the user is an IP, or the
	 * user name otherwise
	 * return the SUL3 rollout flag value if it exists, or
	 * null otherwise
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @return bool|null
	 */
	private function getUserSUL3RolloutFlag( $user, $request ) {
		$noPrefsAvailable = null;

		// check the session's UserName cookie for IP users
		$prefsUser = IPUtils::isIPAddress( $user->getName() )
			? ( self::getLastUser( $user, $request ) ?: $user )
			: $user;

		// if we have an IP user, this will always fall through
		if ( isset( $this->userSUL3RolloutFlags[ $prefsUser->getName() ] ) ) {
			return $this->userSUL3RolloutFlags[ $prefsUser->getName() ];
		} elseif ( isset( $this->noPrefsAvailable[ $prefsUser->getName() ] ) ) {
			return $noPrefsAvailable;
		}

		$globalPreferencesFactory = $this->getGlobalPreferencesFactory();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' )
			|| !$globalPreferencesFactory
		) {
			return $noPrefsAvailable;
		}

		if ( IPUtils::isIPAddress( $prefsUser->getName() ) ) {
			return $noPrefsAvailable;
		}

		$prefs = $globalPreferencesFactory->getGlobalPreferencesValues( $prefsUser );
		if ( !$prefs || !isset( $prefs[ self::SUL3_GLOBAL_PREF ] ) ) {
			$this->noPrefsAvailable[ $prefsUser->getName() ] = true;
			return $noPrefsAvailable;
		}

		$flag = (bool)$prefs[ self::SUL3_GLOBAL_PREF ];
		$this->userSUL3RolloutFlags[ $prefsUser->getName() ] = $flag;
		return $flag;
	}

	/**
	 * return true if the short-term sul3 wanted cookie is set,
	 * false otherwise
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public static function hasSUL3WantedCookie( $request ) {
		$prefix = RequestContext::getMain()->getConfig()->get( CAMainConfigNames::CentralAuthCookiePrefix );
		$cookie = $request->getCookie( self::SUL3_WANTED_COOKIE, $prefix );
		if ( $cookie === '1' ) {
			return true;
		}
		return false;
	}

	/**
	 * set SUL3 wanted cookie; caller should ensure that the user is
	 * an IP address, and that no cookie is already set
	 *
	 * @param Webrequest $request
	 * @return void
	 */
	public function setSUL3RolloutCookie( $request ) {
		$prefix = RequestContext::getMain()->getConfig()->get( CAMainConfigNames::CentralAuthCookiePrefix );
		$expiry = time() + 300;

		$request->response()->setCookie(
			self::SUL3_WANTED_COOKIE, '1', $expiry, [ 'prefix' => $prefix ] );
	}

	/**
	 * see if the user name (IP or otherwise) when hashed maps to
	 * lower than the cutoff the percentage requested; return
	 * true if so, false otherwise
	 *
	 * @param User $user
	 * @param string $settingValue
	 * @param string $settingName
	 * @return bool
	 * @throws UnexpectedValueException
	 */
	public function checkPercentage( $user, $settingValue, $settingName ) {
		$percentage = intval( $settingValue );
		if ( $percentage < 0 || $percentage > 100 ) {
			throw new UnexpectedValueException(
				$settingName . ' setting must be an integer from 0 to 100' );
		}
		if ( $percentage == 0 ) {
			return false;
		} elseif ( $percentage == 100 ) {
			return true;
		}

		// cheap hash, we don't care about collisions
		$userHash = hash( 'crc32', $user->getName(), false );
		if ( hexdec( $userHash ) % 100 <= $percentage ) {
			return true;
		}
		return false;
	}

	/**
	 * if the local wiki is configured to always have SUL3 enabled,
	 * return true, false otherwise
	 *
	 * @return bool
	 */
	public function sul3AlwaysEnabledHere() {
		$sul3Config = $this->config->get( CAMainConfigNames::CentralAuthEnableSul3 );
		return in_array( 'always', $sul3Config, true );
	}

	/**
	 * check if we should set SUL3 rollout global pref:
	 * is the user a named (not temp) user? does the user name
	 * meet the conditions for participation in the rollout?
	 * alternatively, is the local wiki set to always be sul3 enabled?
	 * return true if so, false otherwise
	 *
	 * @param Webrequest $request
	 * @param User $user
	 * @return bool
	 */
	public function shouldSetSUL3RolloutGlobalPref( $request, $user ) {
		if ( IPUtils::isIPAddress( $user->getName() )
			|| $this->tempUserConfig->isTempName( $user->getName() ) ) {
			return false;
		}
		if ( $this->sul3AlwaysEnabledHere() ) {
			return true;
		}

		$sul3RolloutNamedUserConfig = $this->config->get( CAMainConfigNames::Sul3RolloutUserPercentage );
		if ( $this->checkPercentage(
			$user, $sul3RolloutNamedUserConfig, 'Sul3RolloutUserPercentage' ) ) {
			return true;
		}

		if ( $this->config->get( CAMainConfigNames::Sul3RolloutSignupCookie ) ) {
			// the user got the sul3 wanted cookie earlier from being at the signup page
			if ( self::hasSUL3WantedCookie( $request ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * set the SUL3 rollout preference for the specified user;
	 * caller should ensure that the user is not an IP address
	 * returns true if the preference was set, false otherwise
	 *
	 * @param User $user
	 * @param bool $value
	 * @return bool
	 */
	public function setSUL3RolloutGlobalPref( $user, $value ) {
		$globalPreferencesFactory = $this->getGlobalPreferencesFactory();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' )
			|| !$globalPreferencesFactory
		) {
			return false;
		}

		// remove record of any older misses
		unset( $this->userSUL3RolloutFlags[ $user->getName() ] );
		unset( $this->noPrefsAvailable[ $user->getName() ] );

		// read them, add/update the new one, write them all back. sigh
		$currentPrefs = $globalPreferencesFactory->getGlobalPreferencesValues( $user );
		// no global user id, apparently. let's just bail in that case
		if ( $currentPrefs === false ) {
			return false;
		}

		$prefs = array_merge( $currentPrefs, [ self::SUL3_GLOBAL_PREF => $value ] );

		$globalPreferencesFactory->setGlobalPreferences( $user, $prefs, RequestContext::getMain() );
		return true;
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

		$params = [];
		$this->hookRunner->onAuthPreserveQueryParams( $params, [ 'request' => $request ] );
		// already handled in makeUrlDeviceCompliant()
		unset( $params['useformat'] );
		// these will be preserved via the 'centralauthLoginToken' parameter, but we don't
		// actually want to return anywhere while on the login domain
		unset( $params['returnto'], $params['returntoquery'], $params['returntoanchor'] );
		$params['usesul3'] = '1';
		// In the future maybe we'll want to use a more robust redirection mechanism instead of
		// relying on PostLoginRedirect (see also T369467). For now, we just add a fake 'returnto'
		// parameter, which is enough to make sure PostLoginRedirect is called even when the user
		// is already logged in.
		$params['returnto'] = 'Main_Page';

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
