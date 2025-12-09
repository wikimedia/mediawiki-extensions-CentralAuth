<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Auth
 */

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;

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

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/** @inheritDoc */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$centralUser = CentralAuthUser::getInstance( $user );

		// Since logins are rare, check the actual DB
		$rename = $centralUser->renameInProgressOn( WikiMap::getCurrentWikiId() );
		if ( $rename ) {
			// This wiki specifically has a rename in progress, so always abort
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-rename-abortlogin', $user->getName() )
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
					wfMessage( 'centralauth-rename-abortlogin', $user->getName() )
				);
			}
		}

		if ( $centralUser->canAuthenticate() === CentralAuthUser::AUTHENTICATE_LOCKED ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-login-error-locked' )
					->params( wfEscapeWikiText( $centralUser->getName() ) ),
				[ CentralAuthUser::AUTHENTICATE_LOCKED ]
			);
		}

		return AuthenticationResponse::newAbstain();
	}

	/** @inheritDoc */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
