<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

/**
 * Ensure that the SSO domain cannot be used for anything that is unrelated to its purpose.
 */
class SsoHookHandler implements
	SetupAfterCacheHook,
	GetUserPermissionsErrorsHook,
	ApiCheckCanExecuteHook
{

	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->sharedDomainUtils = $sharedDomainUtils;
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

}
