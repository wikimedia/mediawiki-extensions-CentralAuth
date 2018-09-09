<?php
/**
 * "Attached" accounts always require authentication against the central password.
 *
 * "Unattached" accounts may be allowed to login on the local password if
 *   $wgCentralAuthStrict is not set, but they will not have access to any
 *   central password or settings.
 */

use MediaWiki\MediaWikiServices;

/**
 * @deprecated
 */
class CentralAuthPlugin extends AuthPlugin {

	/**
	 * Username forced on the user by single user login migration.
	 * @var string $sulMigrationName
	 */
	public $sulMigrationName = null;

	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param string $username
	 * @return bool
	 */
	public function userExists( $username ) {
		$central = CentralAuthUser::getInstanceByName( $username );
		return $central->exists();
	}

	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public function authenticate( $username, $password ) {
		global $wgCentralAuthAutoMigrate, $wgCentralAuthCheckSULMigration;
		global $wgCentralAuthAutoMigrateNonGlobalAccounts;
		global $wgCentralAuthStrict;

		$central = CentralAuthUser::getMasterInstanceByName( $username );
		$passwordMatch = self::checkPassword( $central, $password );

		if ( !$passwordMatch && $wgCentralAuthCheckSULMigration ) {
			// Check to see if this is a user who was affected by a global username
			// collision during a forced migration to central auth accounts.
			$renamedUsername = User::getCanonicalName(
				$username . '~' . str_replace( '_', '-', wfWikiID() )
			);
			if ( $renamedUsername !== false ) {
				$renamed = CentralAuthUser::getMasterInstanceByName( $renamedUsername );

				if ( $renamed->getId() ) {
					wfDebugLog( 'CentralAuth',
						"CentralAuthMigration: Checking for migration of '{$username}' to " .
							"'{$renamedUsername}'"
					);
					MediaWikiServices::getInstance()->getStatsdDataFactory()->increment(
						'centralauth.migration.check'
					);

					$passwordMatch = self::checkPassword( $renamed, $password );

					// Remember that the user was authenticated under
					// a different name.
					if ( $passwordMatch ) {
						$this->sulMigrationName = $renamedUsername;
					}
				}

				if ( !$passwordMatch && $wgCentralAuthStrict ) {
					// Will also create log entry
					$this->checkAttached( $central, $username );
				}

				// Since we are falling back to check a force migrated user,
				// we are done regardless of password match status. We don't
				// want to try to automigrate or check detached accounts.
				return $passwordMatch;
			}
		}

		if ( !$central->exists() ) {
			wfDebugLog(
				'CentralAuth',
				"plugin: no global account for '$username'"
			);
			// See if all the unattached accounts match passwords
			// and can be globalized. (bug 70392)
			if ( $wgCentralAuthAutoMigrateNonGlobalAccounts ) {
				$ok = $central->storeAndMigrate(
					[ $password ],
					/* $sendToRC = */ true,
					/* $safe = */ true,
					/* $checkHome = */ true
				);
				if ( $ok ) {
					wfDebugLog( 'CentralAuth',
						"wgCentralAuthAutoMigrateNonGlobalAccounts successful in creating a " .
						"global account for '$username'"
					);
					return true;
				}
			}
			return false;
		}

		if ( $passwordMatch && $wgCentralAuthAutoMigrate ) {
			// If the user passed in the global password, we can identify
			// any remaining local accounts with a matching password
			// and migrate them in transparently.
			// That may or may not include the current wiki.
			wfDebugLog( 'CentralAuth',
				"plugin: attempting wgCentralAuthAutoMigrate for '$username'" );
			$central->attemptPasswordMigration( $password );
		}

		// Will also create log entry
		if ( $this->checkAttached( $central, $username ) === false ) {
			return false;
		}

		return $passwordMatch;
	}

	/**
	 * Check if the provided user is attached, if not generate a log
	 * entry
	 *
	 * @param CentralAuthUser $central
	 * @param string $username
	 * @return bool
	 */
	protected function checkAttached( CentralAuthUser $central, $username ) {
		// Several possible states here:
		// global exists, local exists, attached: require global auth
		// global exists, local exists, unattached: require LOCAL auth to login
		// global exists, local doesn't exist: require global auth -> will autocreate local
		// global doesn't exist, local doesn't exist: no authentication
		if ( !$central->isAttached() ) {
			$local = User::newFromName( $username );
			if ( $local && $local->getId() ) {
				// An unattached local account; central authentication can't
				// be used until this account has been transferred.
				// $wgCentralAuthStrict will determine if local login is allowed.
				wfDebugLog( 'CentralAuth',
					"plugin: unattached account for '$username'" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Check the user's password.
	 *
	 * @param CentralAuthUser $user
	 * @param string $password
	 * @return bool
	 */
	protected static function checkPassword( CentralAuthUser $user, $password ) {
		return $user->authenticate( $password ) == "ok";
	}

	/**
	 * Check if a user should authenticate locally if the global authentication fails.
	 * If either this or strict() returns true, local authentication is not used.
	 *
	 * @param string $username
	 * @return bool
	 */
	public function strictUserAuth( $username ) {
		// Authenticate locally if the global account doesn't exist,
		// or the local account isn't attached
		// If strict is on, local authentication won't work at all
		$central = CentralAuthUser::getInstanceByName( $username );
		return $central->exists() && $central->isAttached();
	}

	/**
	 * When a user logs in, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param User &$user
	 * @return bool
	 */
	public function updateUser( &$user ) {
		global $wgCentralAuthCheckSULMigration;

		if ( $wgCentralAuthCheckSULMigration && $this->sulMigrationName !== null ) {
			wfDebugLog( 'CentralAuth',
				"CentralAuthMigration: Coercing user to '{$this->sulMigrationName}'"
			);
			MediaWikiServices::getInstance()->getStatsdDataFactory()->increment(
				'centralauth.migration.coerce'
			);

			// Create a new user object using the post-migration name
			$user = User::newFromName( $this->sulMigrationName );

			// Annotate the user so we can tell them about the change to their
			// username.
			$user->sulRenamed = true;
		}

		$central = CentralAuthUser::getMasterInstance( $user );
		if ( $central->exists() && $central->isAttached() &&
			$central->getEmail() != $user->getEmail()
		) {
			$user->setEmail( $central->getEmail() );
			$user->mEmailAuthenticated = $central->getEmailAuthenticationTimestamp();
			$user->saveSettings();
		}
		return true;
	}

	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 */
	public function autoCreate() {
		// Yes unless account creation is restricted on this wiki
		return User::isEveryoneAllowed( 'createaccount' )
			|| User::isEveryoneAllowed( 'autocreateaccount' );
	}

	/**
	 * Set the given password in the authentication database.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 */
	public function setPassword( $user, $password ) {
		// Fixme: password changes should happen through central interface.
		$central = CentralAuthUser::getMasterInstance( $user );
		if ( $central->isAttached() ) {
			return $central->setPassword( $password );
		} else {
			// Not attached, local password is set only
			return true;
		}
	}

	/**
	 * Update user information in the external authentication database.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @return bool
	 */
	public function updateExternalDB( $user ) {
		return true;
	}

	/**
	 * Check to see if external accounts can be created.
	 * Return true if external accounts can be created.
	 * @return bool
	 */
	public function canCreateAccounts() {
		// Require accounts to be created through the central login interface?
		return true;
	}

	/**
	 * Add a user to the external authentication database.
	 * Email and real name addresses are provided by the
	 * registering user, and may or may not be accepted.
	 *
	 * Return true if successful.
	 *
	 * @param User $user - only the name should be assumed valid at this point
	 * @param string $password
	 * @param string $email
	 * @param string $realname
	 * @return bool
	 */
	public function addUser( $user, $password, $email = '', $realname = '' ) {
		$central = CentralAuthUser::getMasterInstance( $user );
		if ( !$central->exists() && !$central->listUnattached() ) {
			// Username is unused; set up as a global account
			// @fixme is this even vaguely reliable? pah
			$ok = $central->register( $password, $email );
			if ( $ok ) {
				$central->attach( wfWikiID(), 'new' );
				CentralAuthUtils::getCentralDB()->onTransactionIdle( function () use ( $central ) {
					CentralAuthUtils::scheduleCreationJobs( $central );
				} );
			} else {
				return false;
			}
		}
		// Note: If $wgCentralAuthPreventUnattached is enabled,
		// accounts where a global does not exist, but there are
		// unattached accounts will have been denied creation in
		// the AbortNewAccount hook.
		return true;
	}

	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 */
	public function strict() {
		global $wgCentralAuthStrict;
		return $wgCentralAuthStrict;
	}

	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param User &$user
	 * @param bool $autocreate
	 */
	public function initUser( &$user, $autocreate = false ) {
		if ( $autocreate ) {
			$central = CentralAuthUser::getMasterInstance( $user );
			if ( $central->exists() ) {
				$central->attach( wfWikiID(), 'login' );
				$central->addLocalName( wfWikiID() );
				$this->updateUser( $user );
				// Log the autocreation just happened
				$user->addNewUserLogEntryAutoCreate();
			} else {
				wfDebugLog( 'CentralAuth-Bug39996', __METHOD__ .
					": CentralAuthUser::exists returned false for " .
					"\"{$user->getName()}\" even though \$autocreate = true." );
			}
		}
	}

	/**
	 * @param User &$user
	 * @return CentralAuthUser
	 */
	public function getUserInstance( User &$user ) {
		// Needs to be a master instance because we don't know if the caller is
		// going to call write methods like AuthPluginUser::resetAuthToken().
		return CentralAuthUser::getMasterInstance( $user );
	}

}
