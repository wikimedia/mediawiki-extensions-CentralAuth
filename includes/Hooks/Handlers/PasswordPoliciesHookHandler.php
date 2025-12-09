<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\PasswordPoliciesForUserHook;
use MediaWiki\Password\UserPasswordPolicy;
use MediaWiki\User\User;

class PasswordPoliciesHookHandler implements PasswordPoliciesForUserHook {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Apply global password policies when calculating the effective policy for
	 * a user.
	 * @param User $user
	 * @param array &$effectivePolicy
	 * @return bool
	 * @throws Exception
	 */
	public function onPasswordPoliciesForUser( $user, &$effectivePolicy ) {
		$central = CentralAuthUser::getInstance( $user );

		if ( $central->exists() ) {
			$localPolicyGroups = array_intersect(
				array_keys( $this->config->get( CAMainConfigNames::CentralAuthGlobalPasswordPolicies ) ),
				$central->getLocalGroups()
			);

			$effectivePolicy = UserPasswordPolicy::getPoliciesForGroups(
				$this->config->get( CAMainConfigNames::CentralAuthGlobalPasswordPolicies ),
				array_merge( $central->getGlobalGroups(), $localPolicyGroups ),
				$effectivePolicy
			);
		}
		return true;
	}
}
