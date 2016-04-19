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

use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Auth\ButtonAuthenticationRequest;

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
	 */
	public function __construct( $params = [] ) {
		global $wgCentralAuthCheckSULMigration, $wgCentralAuthAutoMigrate,
			$wgCentralAuthAutoMigrateNonGlobalAccounts, $wgCentralAuthPreventUnattached,
			$wgCentralAuthStrict;

		$params += [
			'checkSULMigration' => $wgCentralAuthCheckSULMigration,
			'autoMigrate' => $wgCentralAuthAutoMigrate,
			'autoMigrateNonGlobalAccounts' => $wgCentralAuthAutoMigrateNonGlobalAccounts,
			'preventUnattached' => $wgCentralAuthPreventUnattached,
			'authoritative' => $wgCentralAuthStrict,
		];

		parent::__construct( $params );

		$this->checkSULMigration = (bool)$params['checkSULMigration'];
		$this->autoMigrate = (bool)$params['autoMigrate'];
		$this->autoMigrateNonGlobalAccounts = (bool)$params['autoMigrateNonGlobalAccounts'];
		$this->preventUnattached = (bool)$params['preventUnattached'];
	}

	public function beginPrimaryAuthentication( array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, PasswordAuthenticationRequest::class );
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
		if ( !$status->isOk() ) {
			// Fatal, can't log in
			return AuthenticationResponse::newFail( $status->getMessage() );
		}

		$pass = false;

		// First, check normal login
		$centralUser = CentralAuthUser::getMasterInstanceByName( $username );
		$pass = $centralUser->authenticate( $req->password ) === 'ok';

		// See if it's a user affected by a rename, if applicable.
		if ( !$pass && $this->checkSULMigration ) {
			$renamedUsername = User::getCanonicalName(
				$req->username . '~' . str_replace( '_', '-', wfWikiID() )
			);
			if ( $renamedUsername !== false ) {
				$renamed = CentralAuthUser::getMasterInstanceByName( $renamedUsername );
				if ( $renamed->getId() ) {
					$this->logger->debug(
						'CentralAuthMigration: Checking for migration of "{oldname}" to "{newname}"',
						[
							'oldname' => $username,
							'newname' => $renamedUsername,
						]
					);
					RequestContext::getMain()->getStats()->increment( 'centralauth.migration.check' );

					if ( $renamed->authenticate( $req->password ) === 'ok' ) {
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
						$this->manager->setAuthenticationSessionData( 'CA-renamed-to', $renamedUsername );
						return AuthenticationResponse::newUI(
							[
								new ButtonAuthenticationRequest(
									'caRenameOk', wfMessage( 'ok' ), wfMessage( 'sulrenamewarning-authmanager-ok-help' )
								)
							],
							wfMessage( 'sulrenamewarning-renamed', $username, $renamedUsername )
						);
					}
				}
			}
		}

		// If we don't have a central account, see if all local accounts match
		// the password and can be globalized. (bug T72392)
		if ( !$centralUser->exists() ) {
			$this->logger->debug( 'no global account for "{username}"', [ 'username' => $username ] );
			if ( $this->autoMigrateNonGlobalAccounts ) {
				$ok = $centralUser->storeAndMigrate( [ $req->password ], /* $sendToRC = */ true, /* $safe = */ true, /* $checkHome = */ true );
				if ( $ok ) {
					$this->logger->debug( 'wgCentralAuthAutoMigrateNonGlobalAccounts successful in creating a global account for "{username}"', [
						'username' => $username
					] );
					return AuthenticationResponse::newPass( $username );
				}
			}
			return $this->failResponse( $req );
		}

		if ( $pass && $this->autoMigrate ) {
			// If the user passed in the global password, we can identify
			// any remaining local accounts with a matching password
			// and migrate them in transparently.
			//
			// That may or may not include the current wiki.
			//
			$this->logger->debug( 'attempting wgCentralAuthAutoMigrate for "{username}"', [
				'username' => $username,
			] );
			$centralUser->attemptPasswordMigration( $req->password );
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
			return AuthenticationResponse::newFail( wfMessage( 'authmanager-authn-not-in-progress' ) );
		}

		$req = ButtonAuthenticationRequest::getRequestByName( $reqs, 'caRenameOk' );
		if ( $req ) {
			return AuthenticationResponse::newPass( $renamedUsername );
		} else {
			// Try again, client, and please get it right this time.
			return AuthenticationResponse::newUI(
				[
					new ButtonAuthenticationRequest(
						'caRenameOk', wfMessage( 'ok' ), wfMessage( 'sulrenamewarning-authmanager-ok-help' )
					)
				],
				wfMessage( 'sulrenamewarning-renamed', $username, $renamedUsername )
			);
		}
	}

	public function postAuthentication( $user, AuthenticationResponse $response ) {
		if ( $response->status === AuthenticationResponse::PASS ) {
			$centralUser = CentralAuthUser::getMasterInstance( $user );
			if ( $centralUser->exists() && $centralUser->isAttached() &&
				$centralUser->getEmail() != $user->getEmail()
			) {
				$user->setEmail( $centralUser->getEmail() );
				$user->mEmailAuthenticated = $centralUser->getEmailAuthenticationTimestamp();
				$user->saveSettings();
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
				if ( $centralUser && $centralUser->isAttached() ) {
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
		$username = $req->username !== null ? User::getCanonicalName( $req->username, 'usable' ) : false;
		if ( $username === false ) {
			return;
		}

		if ( get_class( $req ) === PasswordAuthenticationRequest::class ) {
			$centralUser = CentralAuthUser::getMasterInstanceByName( $username );
			if ( $centralUser && $centralUser->isAttached() ) {
				$centralUser->setPassword( $req->password );
			}
		}
	}

	public function accountCreationType() {
		return self::TYPE_CREATE;
	}

	public function testUserForCreation( $user, $autocreate ) {
		global $wgCentralAuthEnableGlobalRenameRequest;

		$status = parent::testUserForCreation( $user, $autocreate );
		if ( !$status->isOk() ) {
			return $status;
		}

		$centralUser = CentralAuthUser::getMasterInstance( $user );

		// Rename in progress?
		if ( $centralUser->renameInProgressOn( wfWikiID() ) ) {
			$status->fatal( 'centralauth-rename-abortlogin' );
			return $status;
		}

		if ( $autocreate !== $this->getUniqueId() ) {
			// Prevent creation if the user exists centrally
			if ( $centralUser->exists() ) {
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

			// Check CentralAuthAntiSpoof, if applicable
			if ( class_exists( 'AntiSpoof' ) ) {
				$status->merge( CentralAuthAntiSpoofHooks::testNewAccount( $user ) );
			}
		}

		return $status;
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, PasswordAuthenticationRequest::class );

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
		return $ret;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, PasswordAuthenticationRequest::class );
		if ( $req ) {
			if ( $req->username !== null && $req->password !== null ) {
				$centralUser = CentralAuthUser::getMasterInstance( $user );
				if ( $centralUser->exists() ) {
					return AuthenticationResponse::newFail( wfMessage( 'centralauth-account-exists' ) );
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
				$centralUser->attach( wfWikiID(), 'new' );
				CentralAuthUtils::scheduleCreationJobs( $centralUser );
				return AuthenticationResponse::newPass( $user->getName() );
			}
		}

		return AuthenticationResponse::newAbstain();
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
