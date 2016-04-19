<?php

/**
 * This holds hooks needed for pre-AuthManager versions of MediaWiki (or when
 * $wgDisableAuthManager is set). Once we drop support for those versions, this
 * may be removed.
 * @deprecated
 */
class CentralAuthPreAuthManagerHooks {

	/**
	 * This hook is used in cases where SpecialPageFactory::getPageList() is called before
	 * $wgExtensionFunctions are run, which happens when E:ShortUrl is installed.
	 *
	 * @param array $list
	 * @return bool
	 */
	public static function onSpecialPage_initList( &$list ) {
		global $wgCentralAuthCheckSULMigration;

		if ( $wgCentralAuthCheckSULMigration ) {
			$list['SulRenameWarning'] = 'SpecialSulRenameWarning';
		}

		return true;
	}

	/**
	 * @param $auth
	 * @return bool
	 */
	static function onAuthPluginSetup( &$auth ) {
		$auth = new CentralAuthPlugin;
		return true;
	}

	/**
	 * @param $user User
	 * @param $abortError
	 * @return bool
	 */
	static function onAbortNewAccount( User $user, &$abortError ) {
		global $wgCentralAuthPreventUnattached;
		global $wgCentralAuthEnableGlobalRenameRequest;

		$centralUser = CentralAuthUser::getMasterInstance( $user );
		if ( $centralUser->exists() || $centralUser->renameInProgressOn( wfWikiID() ) ) {
			$abortError = wfMessage( 'centralauth-account-exists' )->text();
			return false;
		}

		if ( $wgCentralAuthPreventUnattached && $centralUser->listUnattached() ) {
			// If no global account exists and there are unattached accounts,
			// don't let a new unattached account get created. We'll pretend
			// that the name is already taken, because someone will eventually
			// get it. See bug 67901.
			$abortError = wfMessage( 'centralauth-account-unattached-exists' )->text();
			return false;
		}

		// Block account creation if name is a pending rename request
		if ( $wgCentralAuthEnableGlobalRenameRequest &&
			GlobalRenameRequest::nameHasPendingRequest( $user->getName() )
		) {
			$abortError = wfMessage( 'centralauth-account-rename-exists' )->text();
			return false;
		}

		return true;
	}

	/**
	 * Prevent accounts from being autocreated if a rename is in progress
	 * @param User $user
	 * @param $msg
	 * @return bool
	 */
	static function onAbortAutoAccount( User $user, &$msg ) {
		$caUser = CentralAuthUser::getInstance( $user );
		if ( $caUser->renameInProgress() ) {
			$msg = 'centralauth-rename-abortlogin';
			return false;
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param string $pass
	 * @param integer &$retval
	 * @param string &$msg
	 * @return bool
	 * @throws Exception
	 */
	static function onAbortLogin( User $user, $pass, &$retval, &$msg ) {
		$centralUser = CentralAuthUser::getMasterInstance( $user );

		// Since logins are rare, check the actual DB
		$rename = $centralUser->renameInProgressOn( wfWikiID() );
		if ( $rename ) {
			// This wiki specifically has a rename in progress, so always abort
			$retval = LoginForm::NOT_EXISTS;
			$msg = 'centralauth-rename-abortlogin';
			return false;
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
				$retval = LoginForm::NOT_EXISTS;
				// This is an icky hack so the message is ->parse()d and
				// gets the username as a parameter
				$msg = 'centralauth-rename-abortlogin';
				return false;
			}
		}

		switch ( (string)$centralUser->canAuthenticate() ) {
			case '1': // boolean true
				return true;

			case 'no user':
				// If they're local, we still want to let them log in. And if they
				// don't exist, this hook wouldn't have even been called.
				return true;

			case 'locked':
				$msg = 'centralauth-login-error-locked';
				$retval = LoginForm::USER_BLOCKED;
				return false;

			default:
				throw new Exception( "Unexpected result from CentralAuthUser::canAuthenticate()" );
		}
	}

	/**
	 * Inform a user that their username was renamed as part of SUL
	 * Finalization, if their previous username doesn't exist any more (winner
	 * was renamed).
	 * @param User $user
	 * @param string &$msg return error key, or return an array with key and params
	 * @return bool
	 */
	public static function onLoginUserMigrated( $user, &$msg ) {
		global $wgCentralAuthCheckSULMigration;
		if ( $wgCentralAuthCheckSULMigration ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $user->getID() === 0 && !$centralUser->exists() ) {
				// If the local and global accounts don't exist,
				// otherwise wgAuth will handle those.
				$testName = $user->getName() . '~' . wfWikiID();
				$test = CentralAuthUser::getInstanceByName( $testName );
				if ( $test->exists() && $test->isAttached() ) {
					$msg = array( 'centralauth-abortlogin-renamed', $testName );
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param $user User
	 * @param $inject_html string
	 * @return bool
	 */
	static function onUserLoginComplete( &$user, &$inject_html ) {
		global $wgCentralAuthCheckSULMigration;

		if ( $wgCentralAuthCheckSULMigration &&
			isset( $user->sulRenamed ) &&
			$user->sulRenamed === true
		) {
			// Add a flag in the user's session to track that they authenticated
			// with a pre-migration username.
			$request = $user->getRequest();
			$request->setSessionData( 'CentralAuthForcedRename', true );
		}

		return true;
	}

	/**
	 * @param $user User
	 * @return bool
	 */
	static function onUserLogout( &$user ) {
		// Clean up any possible forced rename markers
		$user->getRequest()->setSessionData( 'CentralAuthForcedRename', null );

		return true;
	}

	/**
	 * Handler for PostLoginRedirect
	 * @param string $returnTo The page to return to
	 * @param array $returnToQuery Url parameters
	 * @param string $type Type of login redirect
	 * @return bool
	 */
	public static function onPostLoginRedirect(
		&$returnTo, &$returnToQuery, &$type
	) {
		global $wgCentralAuthCheckSULMigration, $wgUser;
		if ( $wgCentralAuthCheckSULMigration &&
			$wgUser->getRequest()->getSessionData( 'CentralAuthForcedRename' ) === true &&
			( $type == 'success' || $type == 'successredirect' )
		) {
			wfDebugLog( 'SUL', 'Redirecting user to Special:SulRenameWarning' );
			// Store current redirect target in session so we can provide a link
			// later.
			$wgUser->getRequest()->setSessionData( 'SulRenameWarning', array(
				'returnTo' => $returnTo,
				'returnToQuery' => $returnToQuery,
			) );
			$returnTo = SpecialPageFactory::getLocalNameFor( 'Special:SulRenameWarning' );
			$returnToQuery = array();
			return false;
		}
		return true;
	}
}
