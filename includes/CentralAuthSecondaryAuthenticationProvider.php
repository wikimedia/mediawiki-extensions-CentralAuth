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
 * @ingroup Auth
 */

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationResponse;

/**
 * This implements CentralAuth checks that should be done on all logins, e.g.
 * being renamed or centrally locked.
 */
class CentralAuthSecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {

	/**
	 * @param array $params
	 */
	public function __construct( $params = [] ) {
	}

	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$centralUser = CentralAuthUser::getInstance( $user );

		// Since logins are rare, check the actual DB
		$rename = $centralUser->renameInProgressOn( wfWikiID() );
		if ( $rename ) {
			// This wiki specifically has a rename in progress, so always abort
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-rename-abortlogin' )
				->params( $user->getName() )
			);
		}

		// Now check if the user is the target of a rename anywhere
		$rename = $centralUser->renameInProgress();
		if ( $rename ) {
			// It's possible a user is being renamed but someone else with
			// an unattached account is trying to login. Since we've already
			// moved everything over to the new account name, we only need
			// to check one username.
			$newCAUser = $rename[1] === $user->getName()
				? $centralUser
				: CentralAuthUser::getInstanceByName( $rename[0] );

			if ( $newCAUser->isAttached() ) {
				// If there is an account using that name that exists on this wiki
				// reject the login.
				return AuthenticationResponse::newFail(
					wfMessage( 'centralauth-rename-abortlogin' )
					->params( $user->getName() )
				);
			}
		}

		if ( $centralUser->canAuthenticate() === 'locked' ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-login-error-locked' )
					->params( wfEscapeWikiText( $centralUser->getName() ) )
			);
		}

		return AuthenticationResponse::newAbstain();
	}

	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
