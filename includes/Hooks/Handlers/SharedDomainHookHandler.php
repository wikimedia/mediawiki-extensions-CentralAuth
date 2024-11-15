<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use LogicException;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\AuthManagerFilterProvidersHook;
use MediaWiki\Auth\Hook\AuthManagerVerifyAuthenticationHook;
use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\FilteredRequestTracker;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\GetLocalURLHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderModifyEmbeddedSourceUrlsHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use MWExceptionHandler;
use Wikimedia\NormalizedException\NormalizedException;

/**
 * Ensure that the shared domain cannot be used for anything that is unrelated to its purpose.
 */
class SharedDomainHookHandler implements
	ApiCheckCanExecuteHook,
	AuthManagerFilterProvidersHook,
	AuthManagerVerifyAuthenticationHook,
	BeforePageDisplayHook,
	GetLocalURLHook,
	GetUserPermissionsErrorsHook,
	ResourceLoaderModifyEmbeddedSourceUrlsHook,
	SetupAfterCacheHook
{

	// Allowlists of things a user can do on the shared domain.
	// FIXME these should be configurable and/or come from extension attributes
	// 'static' is WMF's custom static.php entry point, serving some files on the shared domain (T374286)
	private const ALLOWED_ENTRY_POINTS = [ 'index', 'api', 'static', 'cli' ];
	private const ALLOWED_SPECIAL_PAGES = [ 'Userlogin', 'Userlogout', 'CreateAccount',
		'PasswordReset', 'Captcha' ];
	private const ALLOWED_API_MODULES = [
		// needed for allowing any query API, even if we only want meta modules; it can be
		// used to check page existence (which is unwanted functionality on the shared domain),
		// which is unfortunate but permissions will still be checked, so it's not a risk.
		'query',
		// allow login/signup directly via the API + help for those APIs
		'clientlogin', 'createaccount', 'authmanagerinfo', 'paraminfo', 'help',
		// APIs used during web login
		'validatepassword', 'userinfo', 'webauthn', 'fancycaptchareload',
		// generic meta APIs, there's a good chance something somewhere will use them
		'siteinfo', 'globaluserinfo', 'tokens',
	];

	// List of authentication providers which should be skipped on the local login page in
	// SUL3 mode, because they will be done on the shared domain instead.
	// This is somewhat fragile, e.g. in case of class renamespacing. We inherit that from
	// AuthManager and can't do much about it. It fails in the safe direction, though - on
	// provider key mismatch there will be unnecessary extra checks.
	private const DISALLOWED_LOCAL_PROVIDERS = [
		// FIXME what about preauth providers like AbuseFilter which don't generate a form
		//   but might prevent login and then the user ends up on a confusing login page?
		// FIXME what about providers (if any) which generate a form but also do something else?
		'preauth' => [
			'CaptchaPreAuthenticationProvider',
		],
		'primaryauth' => [
			TemporaryPasswordPrimaryAuthenticationProvider::class,
			LocalPasswordPrimaryAuthenticationProvider::class,
		],
		'secondaryauth' => [
			'OATHSecondaryAuthenticationProvider',
		],
	];

	private UrlUtils $urlUtils;
	private FilteredRequestTracker $filteredRequestTracker;
	private SharedDomainUtils $sharedDomainUtils;
	private ?MobileContext $mobileContext;

	public function __construct(
		UrlUtils $urlUtils,
		FilteredRequestTracker $filteredRequestTracker,
		SharedDomainUtils $sharedDomainUtils,
		?MobileContext $mobileContext = null
	) {
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
			if ( !in_array( MW_ENTRY_POINT, self::ALLOWED_ENTRY_POINTS, true ) ) {
				throw new \RuntimeException(
					MW_ENTRY_POINT . ' endpoint is not allowed on the shared domain'
				);
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
			foreach ( self::ALLOWED_SPECIAL_PAGES as $name ) {
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
			if ( !in_array( $module->getModuleName(), self::ALLOWED_API_MODULES ) ) {
				$message = [ 'apierror-moduledisabled', $module->getModuleName() ];
				return false;
			}
		}
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->sharedDomainUtils->shouldRestrictCurrentDomain() ) {
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

			foreach ( self::DISALLOWED_LOCAL_PROVIDERS as $stage => $disallowedProviders ) {
				foreach ( $disallowedProviders as $disallowedProvider ) {
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
			foreach ( self::ALLOWED_SPECIAL_PAGES as $name ) {
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
}
