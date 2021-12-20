<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use Config;
use Exception;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\PasswordPoliciesForUserHook;
use User;
use UserPasswordPolicy;

class PasswordPoliciesHookHandler implements
	PasswordPoliciesForUserHook
{
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
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
				array_keys( $this->config->get( 'CentralAuthGlobalPasswordPolicies' ) ),
				$central->getLocalGroups()
			);

			$effectivePolicy = UserPasswordPolicy::getPoliciesForGroups(
				$this->config->get( 'CentralAuthGlobalPasswordPolicies' ),
				array_merge( $central->getGlobalGroups(), $localPolicyGroups ),
				$effectivePolicy
			);
		}
		return true;
	}
}
