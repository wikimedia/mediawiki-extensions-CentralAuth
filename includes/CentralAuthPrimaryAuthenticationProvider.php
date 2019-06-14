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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\MediaWikiServices;

/**
 * A primary authentication provider that uses the CentralAuth password.
 */
class CentralAuthPrimaryAuthenticationProvider
	extends AbstractPasswordPrimaryAuthenticationProvider
{
	/** @var bool Whether to check for force-renamed users on login */
	protected $checkSULMigration = null;

	/** @var bool Whether to auto-migrate non-merged accounts on login */
	protected $autoMigrate = null;

	/** @var bool Whether to auto-migrate non-global accounts on login */
	protected $autoMigrateNonGlobalAccounts = null;

	/** @var bool Whether to prevent a new account from being created if the
	 * account exists on other wikis in the SUL group. */
	protected $preventUnattached = null;

	/** @var bool Whether to check for spoofed user names */
	protected $antiSpoofAccounts = null;

	/**
	 * @param array $params Settings. All are optional, defaulting to the
	 *  similarly-named $wgCentralAuth* globals.
	 *  - checkSULMigration: If true, check if the user was force-renamed for
	 *    SUL unification on login.
	 *  - autoMigrate: If true, attempt to auto-migrate local accounts on other
	 *    wikis when logging in.
	 *  - autoMigrateNonGlobalAccounts: If true, attempt to auto-migrate
	 *    non-global accounts on login.
	 *  - preventUnattached: Whether to prevent new unattached accounts from
	 *    being created.
	 *  - antiSpoofAccounts: Whether to anti-spoof new accounts. Ignored if the
	 *    AntiSpoof extension isn't installed or the extension is outdated.
	 */
	public function __construct( $params = [] ) {
		global $wgCentralAuthCheckSULMigration, $wgCentralAuthAutoMigrate,
			$wgCentralAuthAutoMigrateNonGlobalAccounts, $wgCentralAuthPreventUnattached,
			$wgCentralAuthStrict, $wgAntiSpoofAccounts;

		$params += [
			'checkSULMigration' => $wgCentralAuthCheckSULMigration,
			'autoMigrate' => $wgCentralAuthAutoMigrate,
			'autoMigrateNonGlobalAccounts' => $wgCentralAuthAutoMigrateNonGlobalAccounts,
			'preventUnattached' => $wgCentralAuthPreventUnattached,
			'antiSpoofAccounts' => $wgAntiSpoofAccounts,
			'authoritative' => $wgCentralAuthStrict,
		];

		parent::__construct( $params );

		$this->checkSULMigration = (bool)$params['checkSULMigration'];
		$this->autoMigrate = (bool)$params['autoMigrate'];
		$this->autoMigrateNonGlobalAccounts = (bool)$params['autoMigrateNonGlobalAccounts'];
		$this->preventUnattached = (bool)$params['preventUnattached'];
		$this->antiSpoofAccounts = (bool)$params['antiSpoofAccounts'];
	}

	public function getAuthenticationRequests( $action, array $options ) {
		$ret = parent::getAuthenticationRequests( $action, $options );

		if ( $this->antiSpoofAccounts && $action === AuthManager::ACTION_CREATE &&
			class_exists( AntiSpoofAuthenticationRequest::class )
		) {
			$user = User::newFromName( $options['username'] ) ?: new User();
			if ( $user->isAllowed( 'override-antispoof' ) ) {
				$ret[] = new AntiSpoofAuthenticationRequest();
			}
		}

		return $ret;
	}

	public function beginPrimaryAuthentication( array $reqs ) {
		/** @var PasswordAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, PasswordAuthenticationRequest::class
		);
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}

		if ( $req->username === null || $req->password === null ) {
			return AuthenticationResponse::newAbstain();
		}

		$username = User::getCanonicalName( $req->username, 'usable' );
		if ( $username === false ) {
			return AuthenticationResponse::newAbstain();
		}

		$status = $this->checkPasswordValidity( $username, $req->password );
		if ( !$status->isOK() ) {
			// Fatal, can't log in
			return AuthenticationResponse::newFail( $status->getMessage() );
		}

		// First, check normal login
		$centralUser = CentralAuthUser::getInstanceByName( $username );

		// The secondary provider also checks this. It needs to check this
		// for non-unified logins, but we also need to check this to show
		// the right error message for unified logins as in that case the
		// secondary auth wouldn't run as we would have already failed.
		if ( $centralUser->canAuthenticate() === 'locked' ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-login-error-locked' )
					->params( wfEscapeWikiText( $centralUser->getName() ) )
			);
		}

		$pass = $centralUser->authenticate( $req->password ) === 'ok';

		// See if it's a user affected by a rename, if applicable.
		if ( !$pass && $this->checkSULMigration ) {
			$renamedUsername = User::getCanonicalName(
				$req->username . '~' . str_replace( '_', '-', wfWikiID() )
			);
			if ( $renamedUsername !== false ) {
				$renamed = CentralAuthUser::getInstanceByName( $renamedUsername );
				if ( $renamed->getId() ) {
					$this->logger->debug(
						'CentralAuthMigration: Checking for migration of "{oldname}" to "{newname}"',
						[
							'oldname' => $username,
							'newname' => $renamedUsername,
						]
					);
					MediaWikiServices::getInstance()
						->getStatsdDataFactory()->increment( 'centralauth.migration.check' );

					if ( $renamed->authenticate( $req->password ) === 'ok' ) {
						// At this point the user will be passed, so set the
						// reset flag now.
						$this->setPasswordResetFlag( $renamedUsername, $status );

						// Don't do any of the checks below if we checked for a
						// renamed user. But do notify, unless this is coming
						// through the API action=login where it's better to
						// preserve BC and just let it go.
						if ( defined( 'MW_API' ) &&
							$this->manager->getRequest()->getVal( 'action' ) === 'login'
						) {
							return AuthenticationResponse::newPass( $renamedUsername );
						}
						$this->manager->setAuthenticationSessionData( 'CA-renamed-from', $username );
						$this->manager->setAuthenticationSessionData(
							'CA-renamed-to', $renamedUsername
						);
						return AuthenticationResponse::newUI(
							[
								new ButtonAuthenticationRequest(
									'caRenameOk', wfMessage( 'ok' ),
									wfMessage( 'sulrenamewarning-authmanager-ok-help' )
								)
							],
							wfMessage( 'sulrenamewarning-renamed', $username, $renamedUsername ),
							'warning'
						);
					}
				}
			}
		}

		// If we don't have a central account, see if all local accounts match
		// the password and can be globalized. (bug T72392)
		if ( !$centralUser->exists() ) {
			$this->logger->debug(
				'no global account for "{username}"', [ 'username' => $username ] );
			// Confirm using DB_MASTER in case of replication lag
			$latestCentralUser = CentralAuthUser::getMasterInstanceByName( $username );
			if ( $this->autoMigrateNonGlobalAccounts && !$latestCentralUser->exists() ) {
				$ok = $latestCentralUser->storeAndMigrate(
					[ $req->password ],
					/* $sendToRC = */ true,
					/* $safe = */ true,
					/* $checkHome = */ true
				);
				if ( $ok ) {
					$this->logger->debug(
						'wgCentralAuthAutoMigrateNonGlobalAccounts successful in creating ' .
						'a global account for "{username}"',
						[ 'username' => $username ]
					);
					$this->setPasswordResetFlag( $username, $status );
					return AuthenticationResponse::newPass( $username );
				}
			}
			return $this->failResponse( $req );
		}

		if ( $pass && $this->autoMigrate ) {
			// If the user passed in the global password, we can identify
			// any remaining local accounts with a matching password
			// and migrate them in transparently.
			// That may or may not include the current wiki.
			$this->logger->debug( 'attempting wgCentralAuthAutoMigrate for "{username}"', [
				'username' => $username,
			] );
			if ( $centralUser->isAttached() ) {
				// Defer any automatic migration for other wikis
				DeferredUpdates::addCallableUpdate( function () use ( $username, $req ) {
					$latestCentralUser = CentralAuthUser::getMasterInstanceByName( $username );
					$latestCentralUser->attemptPasswordMigration( $req->password );
				} );
			} else {
				// The next steps depend on whether a migration happens for this wiki.
				// Update the $centralUser instance so the checks below reflect any migrations.
				$centralUser = CentralAuthUser::getMasterInstanceByName( $username );
				$centralUser->attemptPasswordMigration( $req->password );
			}
		}

		if ( !$centralUser->isAttached() ) {
			$local = User::newFromName( $username );
			if ( $local && $local->getId() ) {
				// An unattached local account; central authentication can't
				// be used until this account has been transferred.
				// $wgCentralAuthStrict will determine if local login is allowed.
				$this->logger->debug( 'unattached account for "{username}"', [
					'username' => $username,
				] );
				return $this->failResponse( $req );
			}
		}

		if ( $pass ) {
			$this->setPasswordResetFlag( $username, $status );
			return AuthenticationResponse::newPass( $username );
		} else {
			// We know the central user is attached at this point, so never
			// fall back to other password providers.
			return AuthenticationResponse::newFail( wfMessage( 'wrongpassword' ) );
		}
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		$username = $this->manager->getAuthenticationSessionData( 'CA-renamed-from' );
		$renamedUsername = $this->manager->getAuthenticationSessionData( 'CA-renamed-to' );
		if ( $username === null || $renamedUsername === null ) {
			// What?
			$this->logger->debug( 'Missing "CA-renamed-from" or "CA-renamed-to" in session data' );
			return AuthenticationResponse::newFail(
				wfMessage( 'authmanager-authn-not-in-progress' )
			);
		}

		$req = ButtonAuthenticationRequest::getRequestByName( $reqs, 'caRenameOk' );
		if ( $req ) {
			return AuthenticationResponse::newPass( $renamedUsername );
		} else {
			// Try again, client, and please get it right this time.
			return AuthenticationResponse::newUI(
				[
					new ButtonAuthenticationRequest(
						'caRenameOk', wfMessage( 'ok' ),
						wfMessage( 'sulrenamewarning-authmanager-ok-help' )
					)
				],
				wfMessage( 'sulrenamewarning-renamed', $username, $renamedUsername ),
				'warning'
			);
		}
	}

	public function postAuthentication( $user, AuthenticationResponse $response ) {
		if ( $response->status === AuthenticationResponse::PASS ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $centralUser->exists() &&
				$centralUser->isAttached() &&
				$centralUser->getEmail() != $user->getEmail() &&
				!wfReadOnly()
			) {
				DeferredUpdates::addCallableUpdate( function () use ( $user ) {
					$centralUser = CentralAuthUser::getMasterInstance( $user );
					if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
						return; // something major changed?
					}

					$user->setEmail( $centralUser->getEmail() );
					// @TODO: avoid direct User object field access
					$user->mEmailAuthenticated = $centralUser->getEmailAuthenticationTimestamp();
					$user->saveSettings();
				} );
			}
		}
	}

	public function testUserCanAuthenticate( $username ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return false;
		}

		// Note this omits the case where an unattached local user exists but
		// will be globalized on login thanks to $this->autoMigrate or
		// $this->autoMigrateNonGlobalAccounts. Both are impossible to really
		// test here because they both need cleartext passwords to do their
		// thing. If you have such accounts on your wiki, you should have
		// LocalPasswordPrimaryAuthenticationProvider configured too which
		// will return true for such users.

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		return $centralUser && $centralUser->exists() &&
			( $centralUser->isAttached() || !User::idFromName( $username ) ) &&
			!$centralUser->getPasswordObject() instanceof InvalidPassword;
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		return $centralUser && $centralUser->exists();
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if ( get_class( $req ) === PasswordAuthenticationRequest::class ) {
			if ( !$checkData ) {
				return StatusValue::newGood();
			}

			$username = User::getCanonicalName( $req->username, 'usable' );
			if ( $username !== false ) {
				$centralUser = CentralAuthUser::getInstanceByName( $username );
				if ( $centralUser->exists() &&
					( $centralUser->isAttached() ||
					!User::idFromName( $username, User::READ_LATEST ) )
				) {
					$sv = StatusValue::newGood();
					if ( $req->password !== null ) {
						if ( $req->password !== $req->retype ) {
							$sv->fatal( 'badretype' );
						} else {
							$sv->merge( $this->checkPasswordValidity( $username, $req->password ) );
						}
					}
					return $sv;
				}
			}
		}

		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		$username = $req->username !== null
			? User::getCanonicalName( $req->username, 'usable' )
			: false;
		if ( $username === false ) {
			return;
		}

		if ( get_class( $req ) === PasswordAuthenticationRequest::class ) {
			$centralUser = CentralAuthUser::getMasterInstanceByName( $username );
			if ( $centralUser->exists() &&
				( $centralUser->isAttached() || !User::idFromName( $username, User::READ_LATEST ) )
			) {
				$centralUser->setPassword( $req->password );
			}
		}
	}

	public function accountCreationType() {
		return self::TYPE_CREATE;
	}

	public function testUserForCreation( $user, $autocreate, array $options = [] ) {
		global $wgCentralAuthEnableGlobalRenameRequest;

		$options += [ 'flags' => User::READ_NORMAL ];

		$status = parent::testUserForCreation( $user, $autocreate, $options );
		if ( !$status->isOK() ) {
			return $status;
		}

		$centralUser = ( $options['flags'] & User::READ_LATEST ) == User::READ_LATEST
			? CentralAuthUser::getMasterInstance( $user )
			: CentralAuthUser::getInstance( $user );

		// Rename in progress?
		if ( $centralUser->renameInProgressOn( wfWikiID(), $options['flags'] ) ) {
			$status->fatal( 'centralauth-rename-abortlogin' );
			return $status;
		}

		if ( $autocreate !== $this->getUniqueId() ) {
			// Prevent creation if the user exists centrally
			if ( $centralUser->exists() &&
				$autocreate !== AuthManager::AUTOCREATE_SOURCE_SESSION
			) {
				$status->fatal( 'centralauth-account-exists' );
				return $status;
			}

			// Prevent creation if the user exists anywhere else we know about,
			// and we're asked to
			if ( $this->preventUnattached && $centralUser->listUnattached() ) {
				$status->fatal( 'centralauth-account-unattached-exists' );
				return $status;
			}

			// Block account creation if name is a pending rename request
			if ( $wgCentralAuthEnableGlobalRenameRequest &&
				GlobalRenameRequest::nameHasPendingRequest( $user->getName() )
			) {
				$status->fatal( 'centralauth-account-rename-exists' );
				return $status;
			}
		}

		// Check CentralAuthAntiSpoof, if applicable. Assume the user will override if they can.
		if ( $this->antiSpoofAccounts && class_exists( AntiSpoofAuthenticationRequest::class ) &&
			empty( $options['creating'] ) &&
			!RequestContext::getMain()->getUser()->isAllowed( 'override-antispoof' )
		) {
			$status->merge( CentralAuthAntiSpoofHooks::testNewAccount(
				$user, new User, true, false, new \Psr\Log\NullLogger
			) );
		}

		return $status;
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, PasswordAuthenticationRequest::class
		);

		$ret = StatusValue::newGood();
		if ( $req && $req->username !== null && $req->password !== null ) {
			if ( $req->password !== $req->retype ) {
				$ret->fatal( 'badretype' );
			} else {
				$ret->merge(
					$this->checkPasswordValidity( $user->getName(), $req->password )
				);
			}
		}

		// Check CentralAuthAntiSpoof, if applicable
		if ( class_exists( AntiSpoofAuthenticationRequest::class ) ) {
			$antiSpoofReq = AuthenticationRequest::getRequestByClass(
				$reqs, AntiSpoofAuthenticationRequest::class
			);
			$ret->merge( CentralAuthAntiSpoofHooks::testNewAccount(
				$user, $creator, $this->antiSpoofAccounts,
				$antiSpoofReq && $antiSpoofReq->ignoreAntiSpoof
			) );
		}

		return $ret;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, PasswordAuthenticationRequest::class
		);
		if ( $req ) {
			if ( $req->username !== null && $req->password !== null ) {
				$centralUser = CentralAuthUser::getMasterInstance( $user );
				if ( $centralUser->exists() ) {
					return AuthenticationResponse::newFail(
						wfMessage( 'centralauth-account-exists' )
					);
				}
				if ( $centralUser->listUnattached() ) {
					// $this->testUserForCreation() will already have rejected it if necessary
					return AuthenticationResponse::newAbstain();
				}
				// Username is unused; set up as a global account
				if ( !$centralUser->register( $req->password, $user->getEmail() ) ) {
					// Wha?
					return AuthenticationResponse::newFail( wfMessage( 'userexists' ) );
				}
				return AuthenticationResponse::newPass( $user->getName() );
			}
		}
		return AuthenticationResponse::newAbstain();
	}

	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$centralUser = CentralAuthUser::getMasterInstance( $user );
		// Populate the table of local users with this name post-send (if not done already)
		DeferredUpdates::addCallableUpdate( function () use ( $centralUser ) {
			$centralUser->lazyImportLocalNames();
		} );
		// Do the attach in finishAccountCreation instead of begin because now the user has been
		// added to database and local ID exists (which is needed in attach)
		$centralUser->attach( wfWikiID(), 'new' );
		CentralAuthUtils::getCentralDB()->onTransactionCommitOrIdle(
			function () use ( $centralUser ) {
				CentralAuthUtils::scheduleCreationJobs( $centralUser );
			}
		);
		return null;
	}

	public function autoCreatedAccount( $user, $source ) {
		$centralUser = CentralAuthUser::getMasterInstance( $user );
		if ( !$centralUser->exists() ) {
			$this->logger->debug(
				'Not centralizing auto-created user {username}, central account doesn\'t exist',
				[
					'user' => $user->getName(),
				]
			);
		} elseif ( $source !== $this->getUniqueId() && $centralUser->listUnattached() ) {
			$this->logger->debug(
				'Not centralizing auto-created user {username}, unattached accounts exist',
				[
					'user' => $user->getName(),
					'source' => $source,
				]
			);
		} else {
			$this->logger->debug(
				'Centralizing auto-created user {username}',
				[
					'user' => $user->getName(),
				]
			);
			$centralUser->attach( wfWikiID(), 'login' );
			$centralUser->addLocalName( wfWikiID() );

			if ( $centralUser->getEmail() != $user->getEmail() ) {
				$user->setEmail( $centralUser->getEmail() );
				$user->mEmailAuthenticated = $centralUser->getEmailAuthenticationTimestamp();
			}
		}
	}
}
