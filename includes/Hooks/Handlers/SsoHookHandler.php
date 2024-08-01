<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderModifyStartupSourceUrlsHook;
use MediaWiki\Utils\UrlUtils;
use MobileContext;

/**
 * Ensure that the SSO domain cannot be used for anything that is unrelated to its purpose.
 */
class SsoHookHandler implements
	SetupAfterCacheHook,
	GetUserPermissionsErrorsHook,
	ApiCheckCanExecuteHook,
	ResourceLoaderModifyStartupSourceUrlsHook
{

	private UrlUtils $urlUtils;
	private SharedDomainUtils $sharedDomainUtils;
	private ?MobileContext $mobileContext;

	public function __construct(
		UrlUtils $urlUtils,
		SharedDomainUtils $sharedDomainUtils,
		MobileContext $mobileContext = null
	) {
		$this->urlUtils = $urlUtils;
		$this->sharedDomainUtils = $sharedDomainUtils;
		$this->mobileContext = $mobileContext;
	}

	/** @inheritDoc */
	public function onSetupAfterCache() {
		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			// FIXME The REST API does not provide a hook for disabling APIs. No rest APIs
			//   should be needed for login and signup so we can just throw unconditionally,
			//   but this should be improved in the future.
			// FIXME should not log a production error
			if ( !in_array( MW_ENTRY_POINT, [ 'index', 'api', 'load' ], true ) ) {
				throw new \RuntimeException( MW_ENTRY_POINT . ' endpoint is not allowed on the SSO domain' );
			}
		}
	}

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			if ( !$title->isSpecialPage() ) {
				$result = wfMessage( 'badaccess-group0' );
				return false;
			}
			// FIXME this should be an extension attribute eventually
			$allowlist = [ 'Userlogin', 'Userlogout', 'CreateAccount', 'PasswordReset', 'Captcha' ];
			foreach ( $allowlist as $name ) {
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
		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			// FIXME this should be an extension attribute eventually
			$allowlist = [
				// needed for allowing any query API, even if we only want meta modules; it can be
				// used to check page existence (which is unwanted functionality on the SSO domain),
				// which is unfortunate but permissions will still be checked, so it's not a risk.
				'query',
				// allow login/signup directly via the API + help for those APIs
				'clientlogin', 'createaccount', 'authmanagerinfo', 'paraminfo', 'help',
				// APIs used during web login
				'validatepassword', 'userinfo', 'webauthn', 'fancycaptchareload',
				// generic meta APIs, there's a good chance something somewhere will use them
				'siteinfo', 'globaluserinfo', 'tokens',
			];

			if ( !in_array( $module->getModuleName(), $allowlist ) ) {
				$message = 'apierror-moduledisabled';
				return false;
			}
		}
	}

	/**
	 * @inheritDoc
	 * @phan-param array{local:string} $urls
	 */
	public function onResourceLoaderModifyStartupSourceUrls( array &$urls, Context $context ): void {
		$local = $urls['local'];
		$local = $this->urlUtils->expand( $local, PROTO_CURRENT );
		// reassure Phan that expand() won't return null
		'@phan-var string $local';
		if ( $this->mobileContext && $this->mobileContext->usingMobileDomain() ) {
			$local = $this->mobileContext->getMobileUrl( $local );
		}
		$urls['local'] = $local;
	}
}
