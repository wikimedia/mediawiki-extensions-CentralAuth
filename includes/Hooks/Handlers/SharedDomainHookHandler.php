<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Api\Hook\ApiQueryCheckCanExecuteHook;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\AuthManagerFilterProvidersHook;
use MediaWiki\Auth\Hook\AuthManagerVerifyAuthenticationHook;
use MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\FilteredRequestTracker;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\GetLocalURLHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Hook\SiteNoticeBeforeHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderModifyEmbeddedSourceUrlsHook;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Hook\RestCheckCanExecuteHook;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Module\Module;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use RuntimeException;
use Wikimedia\Message\MessageValue;
use Wikimedia\NormalizedException\NormalizedException;

/**
 * Ensure that the shared domain cannot be used for anything that is unrelated to its purpose.
 */
class SharedDomainHookHandler implements
	ApiCheckCanExecuteHook,
	ApiQueryCheckCanExecuteHook,
	AuthManagerFilterProvidersHook,
	AuthManagerVerifyAuthenticationHook,
	BeforePageDisplayHook,
	GetLocalURLHook,
	GetUserPermissionsErrorsHook,
	LoginFormValidErrorMessagesHook,
	MakeGlobalVariablesScriptHook,
	ResourceLoaderModifyEmbeddedSourceUrlsHook,
	RestCheckCanExecuteHook,
	SetupAfterCacheHook,
	SiteNoticeBeforeHook,
	SpecialPageBeforeExecuteHook
{
	/**
	 * List of entry points that are allowed on the shared domain.
	 * @see MW_ENTRY_POINT
	 */
	private const ALLOWED_ENTRY_POINTS = 'allowedEntryPoints';
	/**
	 * List of the special pages that are allowed on the shared domain.
	 */
	private const ALLOWED_SPECIAL_PAGES = 'allowedSpecialPages';
	/**
	 * List of the special pages that are only allowed on the shared domain
	 * (the user should be redirected when trying to access them on the local domain).
	 *
	 * Unlike CENTRAL_SPECIAL_PAGES_AUTHENTICATED, no effort is made to ensure the
	 * user is logged in.
	 */
	private const CENTRAL_SPECIAL_PAGES_UNAUTHENTICATED = 'centralSpecialPagesUnauthenticated';
	/**
	 * List of the special pages that are only allowed on the shared domain
	 * (the user should be redirected when trying to access them on the local domain)
	 * and we prefer the user to be authenticated while using them.
	 *
	 * For pages on this list, if the user was logged in when trying to access
	 * them on the local wiki, we'll ensure they are logged in on the central
	 * domain as well before sending them to the page.
	 *
	 * In practice, these will be the credentials change related pages.
	 * Authentication-related pages are not included here - even though the user will only
	 * see them on the central domain, they need special logic and can't be handled here.
	 */
	private const CENTRAL_SPECIAL_PAGES_AUTHENTICATED = 'centralSpecialPagesAuthenticated';
	/**
	 * List of action API modules that are allowed on the shared domain.
	 * @see ApiBase::getModulePath()
	 */
	private const ALLOWED_ACTION_API_MODULES = 'allowedActionApiModules';
	/**
	 * List of REST API handler classes that are allowed on the shared domain.
	 * Each value is an array in the form of [ 'handler' => <handler class name> ].
	 */
	private const ALLOWED_REST_API_ENDPOINTS = 'allowedRestApiEndpoints';
	/**
	 * List of authentication providers which should *not* be skipped on the local login page in
	 * SUL3 mode. Every other provider will be ignored (they will be applied on the shared domain,
	 * and we want to avoid double-applying providers, or show authentication-related UI on the
	 * local domain).
	 * The values are they keys of $wgAuthManagerAutoConfig - usually but not always the
	 * provider class name.
	 * @see MainConfigNames::AuthManagerAutoConfig
	 */
	private const ALLOWED_LOCAL_PROVIDERS = 'allowedLocalProviders';

	/**
	 * Default restrictions for the shared domain. These values will be merged with the contents of
	 * $wgCentralAuthSul3SharedDomainRestrictions.
	 * Stored here rather than in extension.json because 1) can use comments and statically checked
	 * class constants this way; 2) MediaWiki doesn't have an array_merge_recursive strategy anymore,
	 * but for list items required for correct functionality, like these, you always want to add
	 * and not replace.
	 */
	private const DEFAULT_RESTRICTIONS = [
		// 'static' is used by WMF's custom entry points (static.php, favicon.php etc), serving some
		// files on the shared domain (T374286). 'fatal-error' is WMF's custom entry point
		// (fatal-error.php) used for testing.
		self::ALLOWED_ENTRY_POINTS => [ 'index', 'rest', 'api', 'static', 'cli', 'fatal-error' ],
		self::ALLOWED_SPECIAL_PAGES => [
			// authentication
			'Userlogin', 'Userlogout', 'CreateAccount', 'CentralAutoLogin', 'CentralLogin', 'Captcha',
			// credentials change
			'PasswordReset', 'ChangePassword', 'ChangeCredentials', 'RemoveCredentials', 'OATHManage',
			'LinkAccounts', 'UnlinkAccounts',
			// debugging
			'WikimediaDebug',
		],
		self::CENTRAL_SPECIAL_PAGES_AUTHENTICATED => [
			// credentials change special pages that require the user to be authenticated.
			'ChangePassword', 'ChangeCredentials', 'RemoveCredentials', 'OATHManage',
		],
		self::CENTRAL_SPECIAL_PAGES_UNAUTHENTICATED => [
			// credentials change special pages that may not require the user to be authenticated.
			'PasswordReset',
		],
		self::ALLOWED_ACTION_API_MODULES => [
			// needed for allowing any query API, even if we only want meta modules; it can be
			// used to check page existence, which is unwanted functionality on the shared domain,
			// but permissions will still be checked, so it's not a risk.
			'query',
			// allow login/logout/signup directly via the API + help for those APIs
			'clientlogin', 'logout', 'createaccount', 'query+authmanagerinfo', 'paraminfo', 'help',
			// APIs used during web login
			'validatepassword', 'query+users', 'webauthn', 'fancycaptchareload',
			// generic meta APIs, there's a good chance something somewhere will use them
			'query+tokens', 'query+siteinfo', 'query+globaluserinfo',
		],
		self::ALLOWED_REST_API_ENDPOINTS => [
			// used by CheckUser to collect data about authentication attempts
			[ 'handler' => UserAgentClientHintsHandler::class ]
		],
		self::ALLOWED_LOCAL_PROVIDERS => [
			'preauth' => [
				// T393473: Don't disable abuse filter provider on shared
				// domain during autocreation on local domain.
				'AbuseFilterPreAuthenticationProvider',
				// makes sure that during logins that we can't accidentally mix up SUL2 and SUL3
				// authentication, which would have security consequences
				'CentralAuthSharedDomainPreAuthenticationProvider',
			],
			'primaryauth' => [
				'CentralAuthRedirectingPrimaryAuthenticationProvider',
				// handles various non-login things, e.g. autocreation
				'CentralAuthPrimaryAuthenticationProvider',
				// FIXME probably not really needed
				'CentralAuthTemporaryPasswordPrimaryAuthenticationProvider',
			],
			'secondaryauth' => [],
		],
	];

	private Config $config;
	private UrlUtils $urlUtils;
	private FilteredRequestTracker $filteredRequestTracker;
	private SharedDomainUtils $sharedDomainUtils;
	private ?MobileContext $mobileContext;

	public function __construct(
		Config $config,
		UrlUtils $urlUtils,
		FilteredRequestTracker $filteredRequestTracker,
		SharedDomainUtils $sharedDomainUtils,
		?MobileContext $mobileContext = null
	) {
		$this->config = $config;
		$this->urlUtils = $urlUtils;
		$this->filteredRequestTracker = $filteredRequestTracker;
		$this->sharedDomainUtils = $sharedDomainUtils;
		$this->mobileContext = $mobileContext;
	}

	/** @inheritDoc */
	public function onSetupAfterCache() {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			// FIXME The REST API does not provide a hook for disabling APIs. No rest APIs
			//   should be needed for login and signup so we can just throw unconditionally,
			//   but this should be improved in the future.
			// FIXME should not log a production error
			$allowedEntryPoints = $this->getRestrictions( self::ALLOWED_ENTRY_POINTS );
			if ( !in_array( MW_ENTRY_POINT, $allowedEntryPoints, true ) ) {
				throw new RuntimeException( MW_ENTRY_POINT . ' endpoint is not allowed on the shared domain' );
			}
		}
	}

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			if ( !$title->isSpecialPage() ) {
				$result = wfMessage( 'badaccess-group0' );
				return false;
			}
			$allowedSpecialPages = $this->getRestrictions( self::ALLOWED_SPECIAL_PAGES );
			foreach ( $allowedSpecialPages as $name ) {
				if ( $title->isSpecial( $name ) ) {
					return true;
				}
			}
			$result = wfMessage( 'badaccess-group0' );
			return false;
		}
	}

	/** @inheritDoc */
	public function onApiCheckCanExecute( $module, $user, &$message ) {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			$allowedActionApiModules = $this->getRestrictions( self::ALLOWED_ACTION_API_MODULES );
			if ( !in_array( $module->getModulePath(), $allowedActionApiModules ) ) {
				$message = [ 'apierror-moduledisabled', $module->getModulePath() ];
				return false;
			}
		}
	}

	/** @inheritDoc */
	public function onApiQueryCheckCanExecute( $modules, $authority, &$message ) {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			$allowedActionApiModules = $this->getRestrictions( self::ALLOWED_ACTION_API_MODULES );
			foreach ( $modules as $module ) {
				if ( !in_array( $module->getModulePath(), $allowedActionApiModules ) ) {
					$message = [ 'apierror-moduledisabled', $module->getModulePath() ];
					return false;
				}
			}
		}
	}

	public function onRestCheckCanExecute(
		Module $module,
		Handler $handler,
		string $path,
		RequestInterface $request,
		?HttpException &$error
	): bool {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			$allowedRestApiEndpoints = $this->getRestrictions( self::ALLOWED_REST_API_ENDPOINTS );
			foreach ( $allowedRestApiEndpoints as $allowedEndpoint ) {
				if ( $handler instanceof $allowedEndpoint['handler'] ) {
					return true;
				}
			}
			$msg = new MessageValue( 'centralauth-action-forbidden-shared-domain' );
			$error = new LocalizedHttpException( $msg, 403 );
			return false;
		}
		return true;
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			$out->disallowUserJs();
		}
	}

	/** @inheritDoc */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			$conf = $out->getConfig();
			// Define page-specific overrides for config vars that are normally site-level,
			// because the load.php request that normally sets them is served from the normal domain,
			// giving wrong values for page views on the shared domain. (T380552)
			$vars += [
				'wgArticlePath' => $conf->get( MainConfigNames::ArticlePath ),
				'wgScriptPath' => $conf->get( MainConfigNames::ScriptPath ),
				'wgScript' => $conf->get( MainConfigNames::Script ),
				'wgVariantArticlePath' => $conf->get( MainConfigNames::VariantArticlePath ),
				'wgServer' => $conf->get( MainConfigNames::Server ),
				'wgActionPaths' => (object)$conf->get( MainConfigNames::ActionPaths ),
			];
		}
	}

	public static function onExtensionFunctions(): void {
		// Duplicate the disallowUserJs() call from BeforePageDisplay for good measure.
		// While in theory it can be called at any time before output is generated,
		// some extensions check OutputPage::getAllowedModules() directly to decide whether
		// to do things functionally equivalent to running user JS, and our BeforePageDisplay
		// handler might only run after that.
		$sharedDomainUtils = CentralAuthServices::getSharedDomainUtils();
		if ( $sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			$out = RequestContext::getMain()->getOutput();
			$out->disallowUserJs();
		}
	}

	/**
	 * @inheritDoc
	 * @phan-param array{local:string} $urls
	 */
	public function onResourceLoaderModifyEmbeddedSourceUrls( array &$urls ): void {
		$local = $urls['local'];
		$local = $this->urlUtils->expand( $local, PROTO_CURRENT );
		// reassure Phan that expand() won't return null
		'@phan-var string $local';
		if ( $this->mobileContext && $this->mobileContext->usingMobileDomain() ) {
			$local = $this->mobileContext->getMobileUrl( $local );
		}
		$urls['local'] = $local;
	}

	/**
	 * If we are not on the shared domain and SUL3 is enabled, remove some authentication
	 * providers. They will run after the redirect on shared domain, so it's not necessary to
	 * run them locally, and on the local domain they would generate a login form, and we
	 * don't want that.
	 * @inheritDoc
	 * @note
	 */
	public function onAuthManagerFilterProviders( array &$providers ): void {
		$request = RequestContext::getMain()->getRequest();
		if ( $this->sharedDomainUtils->isSul3Enabled( $request )
			 && !$this->sharedDomainUtils->isSharedDomain()
		) {
			$allowedProviders = $this->getRestrictions( self::ALLOWED_LOCAL_PROVIDERS );
			foreach ( $providers as $stage => $providersAtStage ) {
				foreach ( $providersAtStage as $provider => $_ ) {
					if ( !in_array( $provider, $allowedProviders[$stage], true ) ) {
						$providers[$stage][$provider] = false;
					}
				}
			}

			// We'll rely on CentralAuthSharedDomainPreAuthenticationProvider to make sure filtering does not
			// happen at the wrong time so make sure it's in place.
			if ( !isset( $providers['preauth']['CentralAuthSharedDomainPreAuthenticationProvider'] ) ) {
				throw new LogicException(
					'CentralAuthSharedDomainPreAuthenticationProvider not found during SUL3 login'
				);
			}

			// This is security-critical code. If the providers are removed but some
			// non-redirect-based login is still possible, or the providers are erroneously
			// removed on the shared domain as well, that would circumvent important security
			// checks. To prevent mistakes, we sync with the behavior of the
			// AuthManagerVerifyAuthentication hook.
			$this->filteredRequestTracker->markRequestAsFiltered( $request );
		}
	}

	/** @inheritDoc */
	public function onAuthManagerVerifyAuthentication(
		?UserIdentity $user,
		AuthenticationResponse &$response,
		AuthManager $authManager,
		array $info
	) {
		if ( $this->filteredRequestTracker->isCurrentAuthenticationFlowFiltered( $authManager )
			&& $info['primaryId'] !== CentralAuthRedirectingPrimaryAuthenticationProvider::class
		) {
			// If providers were filtered, but then authentication wasn't handled by redirecting,
			// report and interrupt.
			MWExceptionHandler::logException( new NormalizedException(
				'Providers were filtered but redirecting provider was not the primary',
				[
					'user' => $user->getName(),
					'result' => $response->status,
				] + $info
			) );
			$response = AuthenticationResponse::newFail( wfMessage( 'internalerror' ) );
			return false;
		}
	}

	/** @inheritDoc */
	public function onGetLocalURL( $title, &$url, $query ) {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
			// Only allow links to auth-related special pages on the shared domain.
			// Point all other links to the normal wiki domain.
			$allowedSpecialPages = $this->getRestrictions( self::ALLOWED_SPECIAL_PAGES );
			foreach ( $allowedSpecialPages as $name ) {
				if ( $title->isSpecial( $name ) ) {
					return;
				}
			}
			if ( $title->getInterwiki() !== '' ) {
				return;
			}

			// WikiMap entry for the current wiki points to the normal wiki domain,
			// even when $wgServer etc. were overridden for the shared domain.
			$currentWiki = WikiMap::getWiki( WikiMap::getCurrentWikiId() );
			$url = wfAppendQuery( $currentWiki->getCanonicalUrl( $title->getPrefixedText() ), $query );

			if ( $this->mobileContext && $this->mobileContext->shouldDisplayMobileView() ) {
				$url = $this->mobileContext->getMobileUrl( $url );
			}
		}
	}

	/**
	 * Redirect credentials change special pages to the shared domain if SUL3 is enabled.
	 *
	 * Note that this hook runs before SpecialPage::beforeExecute (which tends to handle security
	 * reauthentication), so reauthentication will happen fully on the shared domain.
	 *
	 * @inheritDoc
	 *
	 * @see SpecialPageBeforeExecuteHookHandler::onSpecialPageBeforeExecute()
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$credentialsChangeSpecialPages = array_merge(
			$this->getRestrictions( self::CENTRAL_SPECIAL_PAGES_AUTHENTICATED ),
			$this->getRestrictions( self::CENTRAL_SPECIAL_PAGES_UNAUTHENTICATED )
		);
		$noAuthCredSpecialPages = $this->getRestrictions( self::CENTRAL_SPECIAL_PAGES_UNAUTHENTICATED );

		if ( $this->sharedDomainUtils->isSul3Enabled( $request )
			&& !$this->sharedDomainUtils->isSharedDomain()
			&& ( in_array( $special->getName(), $credentialsChangeSpecialPages, true )
				// The !isNamed() case is handled in SpecialPageBeforeExecuteHookHandler::onSpecialPageBeforeExecute()
				|| ( $special->getName() === 'CreateAccount' && $special->getUser()->isNamed() ) )
		) {
			if ( $special->getUser()->isNamed() &&
				// T409984: Don't loop PasswordReset through user login. We can land directly on the
				// password reset page whether or not the user is logged-in.
				!in_array( $special->getName(), $noAuthCredSpecialPages, true )
			) {
				// Redirect through Special:UserLogin in order to display a custom message if the user
				// is no longer logged in on the central domain (T393459).
				// Redirect directly to Special:UserLogin on central domain (not on local domain),
				// because if the user is logged in, that short-circuits the central domain redirect.
				$query = $request->getQueryValues();
				unset( $query['title'] );
				// Adapted from UserNotLoggedIn::report() and AuthManagerSpecialPage::handleReauthBeforeExecute().
				// TODO: These different ways of redirecting to the login page should be consolidated...
				$keepParams = [ 'uselang', 'useskin', 'useformat', 'variant', 'debug', 'safemode' ];
				$localUrl = SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL( [
					'returnto' => $special->getFullTitle()->getFullText(),
					'returntoquery' => wfArrayToCgi( $query ),
					'warning' => 'centralauth-warning-reauth',
				] + array_intersect_key( $query, array_fill_keys( $keepParams, true ) ) );
			} else {
				$localUrl = $request->getRequestURL();
			}
			$url = $this->sharedDomainUtils->getSharedDomainPrefix() . $localUrl;
			$url = $this->sharedDomainUtils->makeUrlDeviceCompliant( $url );
			$special->getOutput()->redirect( $url );
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onLoginFormValidErrorMessages( array &$messages ) {
		$userIsLoggedIn = RequestContext::getMain()->getUser()->isNamed();
		if ( $this->sharedDomainUtils->isSharedDomain() && !$userIsLoggedIn ) {
			$messages[] = 'centralauth-warning-reauth';
		}
	}

	/**
	 * Disallow sitenotices on the shared domain.
	 * @inheritDoc
	 */
	public function onSiteNoticeBefore( &$siteNotice, $skin ) {
		if ( $this->sharedDomainUtils->isSharedDomain()
			// check whether we are on the SUL2 central wiki for some non-authentication-related reason
			&& $this->sharedDomainUtils->isSul3Enabled( $skin->getRequest() )
		) {
			$siteNotice = '';
			return false;
		}
	}

	private function getRestrictions( string $type ): array {
		$allRestrictions = $this->config->get( CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions );
		if ( $type === self::ALLOWED_LOCAL_PROVIDERS ) {
			$allowedLocalProvidersConstant = self::DEFAULT_RESTRICTIONS[$type];
			$allowedLocalProvidersGlobal = $allRestrictions[$type] ?? [];
			$restrictions = [];
			foreach ( $allowedLocalProvidersConstant as $providerStage => $_ ) {
				$restrictions[$providerStage] = array_merge(
					$allowedLocalProvidersConstant[$providerStage],
					$allowedLocalProvidersGlobal[$providerStage] ?? []
				);
			}
		} else {
			$restrictions = array_merge(
				self::DEFAULT_RESTRICTIONS[$type],
				$allRestrictions[$type] ?? []
			);
		}
		return $restrictions;
	}

	/**
	 * @internal To be used by PHPUnit tests ONLY.
	 */
	public function getDefaultRestrictions(): array {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new RuntimeException( 'This method should not be called outside tests' );
		}

		return self::DEFAULT_RESTRICTIONS;
	}

}
