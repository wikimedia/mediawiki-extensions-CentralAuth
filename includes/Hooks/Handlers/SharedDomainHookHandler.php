<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Api\Hook\ApiQueryCheckCanExecuteHook;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\CheckBlocksSecondaryAuthenticationProvider;
use MediaWiki\Auth\Hook\AuthManagerFilterProvidersHook;
use MediaWiki\Auth\Hook\AuthManagerVerifyAuthenticationHook;
use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\ThrottlePreAuthenticationProvider;
use MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\FilteredRequestTracker;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\GetLocalURLHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderModifyEmbeddedSourceUrlsHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Hook\RestCheckCanExecuteHook;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Module\Module;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use MWExceptionHandler;
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
	ResourceLoaderModifyEmbeddedSourceUrlsHook,
	RestCheckCanExecuteHook,
	SetupAfterCacheHook,
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
	 * In practice, these will be the credentials change related pages.
	 * Authentication-related pages are not included here - even though the user will only
	 * see them on the central domain, they need special handling locally.
	 */
	private const CENTRAL_SPECIAL_PAGES = 'centralSpecialPages';
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
	 * List of authentication providers which should be skipped on the local login page in
	 * SUL3 mode, because they will be applied on the shared domain instead.
	 * The values are they keys of $wgAuthManagerAutoConfig - usually but not always the
	 * provider class name.
	 * @see MainConfigNames::AuthManagerAutoConfig
	 * @note This is somewhat fragile, e.g. in case of class renamespacing. We inherit that from
	 * AuthManager and can't do much about it. It fails in the safe direction, though - on
	 * provider key mismatch there will be unnecessary extra checks.
	 */
	private const DISALLOWED_LOCAL_PROVIDERS = 'disallowedLocalProviders';

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
		],
		self::CENTRAL_SPECIAL_PAGES => [
			// credentials change
			'PasswordReset', 'ChangePassword', 'ChangeCredentials', 'RemoveCredentials',
		],
		self::ALLOWED_ACTION_API_MODULES => [
			// needed for allowing any query API, even if we only want meta modules; it can be
			// used to check page existence, which is unwanted functionality on the shared domain,
			// but permissions will still be checked, so it's not a risk.
			'query',
			// allow login/signup directly via the API + help for those APIs
			'clientlogin', 'createaccount', 'query+authmanagerinfo', 'paraminfo', 'help',
			// APIs used during web login
			'validatepassword', 'query+users', 'webauthn', 'fancycaptchareload',
			// generic meta APIs, there's a good chance something somewhere will use them
			'query+tokens', 'query+siteinfo', 'query+globaluserinfo',
		],
		self::ALLOWED_REST_API_ENDPOINTS => [
			// used by CheckUser to collect data about authentication attempts
			[ 'handler' => UserAgentClientHintsHandler::class ]
		],
		self::DISALLOWED_LOCAL_PROVIDERS => [
			'preauth' => [
				'AbuseFilterPreAuthenticationProvider',
				'AntiSpoofPreAuthenticationProvider',
				'CaptchaPreAuthenticationProvider',
				'IPReputationPreAuthenticationProvider',
				'SpamBlacklistPreAuthenticationProvider',
				ThrottlePreAuthenticationProvider::class,
				'TitleBlacklistPreAuthenticationProvider',
			],
			'primaryauth' => [
				// CentralAuthPrimaryAuthenticationProvider is needed for autocreation, so it
				//   handles ignoring SUL3 local requests internally.
				LocalPasswordPrimaryAuthenticationProvider::class,
				TemporaryPasswordPrimaryAuthenticationProvider::class,
			],
			'secondaryauth' => [
				CheckBlocksSecondaryAuthenticationProvider::class,
				'CentralAuthSecondaryAuthenticationProvider',
				'OATHSecondaryAuthenticationProvider',
			],
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
				throw new \RuntimeException( MW_ENTRY_POINT . ' endpoint is not allowed on the shared domain' );
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

		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			// Override some global `mw.config` items defined by ResourceLoader::getSiteConfigSettings()
			// (used by 'mediawiki.base'), because the load.php request that normally sets them is served
			// from the normal domain, giving wrong values for page views on the shared domain. (T380552)
			// This can't use the 'MakeGlobalVariablesScript' hook to set page-specific `mw.config` items,
			// because global items with identical names override them. There is therefore a very small
			// risk of race conditions where another inline script reads `mw.config` before we can set it.
			$conf = $out->getConfig();
			$vars = [
				'wgArticlePath' => $conf->get( MainConfigNames::ArticlePath ),
				'wgScriptPath' => $conf->get( MainConfigNames::ScriptPath ),
				'wgScript' => $conf->get( MainConfigNames::Script ),
				'wgVariantArticlePath' => $conf->get( MainConfigNames::VariantArticlePath ),
				'wgServer' => $conf->get( MainConfigNames::Server ),
				'wgActionPaths' => (object)$conf->get( MainConfigNames::ActionPaths ),
			];
			$out->addHeadItem(
				'CentralAuth-SharedDomain-Variables',
				// We must wait until after 'mediawiki.base' sets the global `mw.config` items
				Html::inlineScript( ResourceLoader::makeInlineCodeWithModule(
					// Implicit dependency on 'mediawiki.base'
					[],
					ResourceLoader::makeConfigSetScript( $vars )
				) )
			);
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
			// We'll rely on CentralAuthSharedDomainPreAuthenticationProvider to make sure filtering does not
			// happen at the wrong time so make sure it's in place.
			if ( !isset( $providers['preauth']['CentralAuthSharedDomainPreAuthenticationProvider'] ) ) {
				throw new LogicException(
					'CentralAuthSharedDomainPreAuthenticationProvider not found during SUL3 login'
				);
			}

			$disallowedProviders = $this->getRestrictions( self::DISALLOWED_LOCAL_PROVIDERS );
			foreach ( $disallowedProviders as $stage => $disallowedProvidersAtStage ) {
				foreach ( $disallowedProvidersAtStage as $disallowedProvider ) {
					unset( $providers[$stage][$disallowedProvider] );
				}
			}
			// This is security-critical code. If these providers are removed but some
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
		$credentialsChangeSpecialPages = $this->getRestrictions( self::CENTRAL_SPECIAL_PAGES );

		if ( $this->sharedDomainUtils->isSul3Enabled( $request )
			 && !$this->sharedDomainUtils->isSharedDomain()
			 && in_array( $special->getName(), $credentialsChangeSpecialPages, true )
		) {
			$url = $this->sharedDomainUtils->getSharedDomainPrefix() . $request->getRequestURL();
			$url = $this->sharedDomainUtils->makeUrlDeviceCompliant( $url );
			$special->getOutput()->redirect( $url );
			return false;
		}

		return true;
	}

	private function getRestrictions( string $type ): array {
		$allRestrictions = $this->config->get( CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions );
		if ( $type === self::DISALLOWED_LOCAL_PROVIDERS ) {
			$disallowedLocalProvidersConstant = self::DEFAULT_RESTRICTIONS[$type];
			$disallowedLocalProvidersGlobal = $allRestrictions[$type] ?? [];
			$restrictions = [];
			foreach ( $disallowedLocalProvidersConstant as $providerStage => $_ ) {
				$restrictions[$providerStage] = array_merge(
					$disallowedLocalProvidersConstant[$providerStage],
					$disallowedLocalProvidersGlobal[$providerStage] ?? []
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
}
