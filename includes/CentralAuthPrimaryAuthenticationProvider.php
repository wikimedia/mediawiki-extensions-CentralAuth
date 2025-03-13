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

namespace MediaWiki\Extension\CentralAuth;

use CentralAuthSessionProvider;
use CentralAuthTokenSessionProvider;
use LogicException;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AntiSpoof\AntiSpoofAuthenticationRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Password\InvalidPassword;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use MWExceptionHandler;
use Psr\Log\NullLogger;
use StatusValue;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * A primary authentication provider that uses the CentralAuth password.
 */
class CentralAuthPrimaryAuthenticationProvider
	extends AbstractPasswordPrimaryAuthenticationProvider
{
	/** @var string The internal ID of this provider. */
	public const ID = 'CentralAuthPrimaryAuthenticationProvider';

	private ReadOnlyMode $readOnlyMode;
	private UserIdentityLookup $userIdentityLookup;
	private CentralAuthAntiSpoofManager $caAntiSpoofManager;
	private CentralAuthDatabaseManager $databaseManager;
	private CentralAuthUtilityService $utilityService;
	private GlobalRenameRequestStore $globalRenameRequestStore;
	private SharedDomainUtils $sharedDomainUtils;

	/** @var bool Whether to auto-migrate non-merged accounts on login */
	protected $autoMigrate = null;

	/** @var bool Whether to auto-migrate non-global accounts on login */
	protected $autoMigrateNonGlobalAccounts = null;
	/** @var bool Whether to check for spoofed user names */
	protected $antiSpoofAccounts = null;

	/**
	 * @param ReadOnlyMode $readOnlyMode
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param CentralAuthAntiSpoofManager $caAntiSpoofManager
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param CentralAuthUtilityService $utilityService
	 * @param GlobalRenameRequestStore $globalRenameRequestStore
	 * @param SharedDomainUtils $sharedDomainUtils
	 * @param array $params Settings. All are optional, defaulting to the
	 *  similarly-named $wgCentralAuth* globals.
	 *  - autoMigrate: If true, attempt to auto-migrate local accounts on other
	 *    wikis when logging in.
	 *  - autoMigrateNonGlobalAccounts: If true, attempt to auto-migrate
	 *    non-global accounts on login.
	 *  - antiSpoofAccounts: Whether to anti-spoof new accounts. Ignored if the
	 *    AntiSpoof extension isn't installed or the extension is outdated.
	 */
	public function __construct(
		ReadOnlyMode $readOnlyMode,
		UserIdentityLookup $userIdentityLookup,
		CentralAuthAntiSpoofManager $caAntiSpoofManager,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUtilityService $utilityService,
		GlobalRenameRequestStore $globalRenameRequestStore,
		SharedDomainUtils $sharedDomainUtils,
		$params = []
	) {
		global $wgCentralAuthAutoMigrate,
			$wgCentralAuthAutoMigrateNonGlobalAccounts,
			$wgCentralAuthStrict, $wgAntiSpoofAccounts;

		$this->readOnlyMode = $readOnlyMode;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->caAntiSpoofManager = $caAntiSpoofManager;
		$this->databaseManager = $databaseManager;
		$this->utilityService = $utilityService;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
		$this->sharedDomainUtils = $sharedDomainUtils;

		$params += [
			'autoMigrate' => $wgCentralAuthAutoMigrate,
			'autoMigrateNonGlobalAccounts' => $wgCentralAuthAutoMigrateNonGlobalAccounts,
			'antiSpoofAccounts' => $wgAntiSpoofAccounts,
			'authoritative' => $wgCentralAuthStrict,
		];

		parent::__construct( $params );

		$this->autoMigrate = (bool)$params['autoMigrate'];
		$this->autoMigrateNonGlobalAccounts = (bool)$params['autoMigrateNonGlobalAccounts'];
		$this->antiSpoofAccounts = (bool)$params['antiSpoofAccounts'];
	}

	/** @inheritDoc */
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $this->sharedDomainUtils->isSul3Enabled( $this->manager->getRequest() )
			&& !$this->sharedDomainUtils->isSharedDomain()
			&& in_array( $action, [ AuthManager::ACTION_LOGIN, AuthManager::ACTION_CREATE ], true )
		) {
			return [];
		}

		$ret = parent::getAuthenticationRequests( $action, $options );

		if ( $this->antiSpoofAccounts && $action === AuthManager::ACTION_CREATE ) {
			$user = User::newFromName( $options['username'] ) ?: new User();
			if ( $user->isAllowed( 'override-antispoof' ) ) {
				$ret[] = new AntiSpoofAuthenticationRequest();
			}
		}

		return $ret;
	}

	/**
	 * @param array $reqs
	 * @return PasswordAuthenticationRequest|null
	 */
	private static function getPasswordAuthenticationRequest( array $reqs ) {
		return AuthenticationRequest::getRequestByClass(
			$reqs, PasswordAuthenticationRequest::class
		);
	}

	/** @inheritDoc */
	public function beginPrimaryAuthentication( array $reqs ) {
		$req = self::getPasswordAuthenticationRequest( $reqs );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}

		if ( $req->username === null || $req->password === null ) {
			return AuthenticationResponse::newAbstain();
		}

		$username = $this->userNameUtils->getCanonical( $req->username, UserNameUtils::RIGOR_USABLE );
		if ( $username === false ) {
			return AuthenticationResponse::newAbstain();
		}

		$status = $this->checkPasswordValidity( $username, $req->password );
		if ( !$status->isOK() ) {
			return $this->getFatalPasswordErrorResponse( $username, $status );
		}

		if ( $this->sharedDomainUtils->isSul3Enabled( $this->manager->getRequest() )
			&& !$this->sharedDomainUtils->isSharedDomain()
		) {
			// We are in SUL3 mode on the local domain, we should not have gotten here,
			// it should have been handled by the redirect provider. It is important to
			// prevent authentication as SharedDomainHookHandler might have disabled important checks.
			// But it's relatively easy to get here by accident, if the brittle logic in
			// SharedDomainHookHandler::onAuthManagerFilterProviders fails to disable some provider
			// that generates a password form, so we should fail in some user-comprehensible way.
			MWExceptionHandler::logException( new LogicException( 'Invoked SUL2 provider in SUL3 mode' ) );
			return AuthenticationResponse::newFail( wfMessage( 'centralauth-login-error-usesul3' ) );
		}

		// First, check normal login
		$centralUser = CentralAuthUser::getInstanceByName( $username );

		$authenticateResult = $centralUser->authenticate( $req->password );

		$pass = $authenticateResult === [ CentralAuthUser::AUTHENTICATE_OK ];

		if ( in_array( CentralAuthUser::AUTHENTICATE_LOCKED, $authenticateResult ) ) {
			if ( !in_array( CentralAuthUser::AUTHENTICATE_BAD_PASSWORD, $authenticateResult ) ) {
				// Because the absence of "bad password" for any code that hooks and receives
				// the returned AuthenticationResponse means either that the password
				// was correct or that the password was not checked, provide "good password"
				// which removes the two possible meanings of no "bad password".
				$authenticateResult[] = CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD;
			}
			return AuthenticationResponse::newFail(
				wfMessage( 'centralauth-login-error-locked' )
					->params( wfEscapeWikiText( $centralUser->getName() ) ),
				$authenticateResult
			);
		}

		// If we don't have a central account, see if all local accounts match
		// the password and can be globalized. (bug T72392)
		if ( !$centralUser->exists() ) {
			$this->logger->debug(
				'no global account for "{username}"', [ 'username' => $username ] );
			// Confirm using DB_PRIMARY in case of replication lag
			$latestCentralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
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
				DeferredUpdates::addCallableUpdate( static function () use ( $username, $req ) {
					$latestCentralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
					$latestCentralUser->attemptPasswordMigration( $req->password );
				} );
			} else {
				// The next steps depend on whether a migration happens for this wiki.
				// Update the $centralUser instance so the checks below reflect any migrations.
				$centralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
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

	/** @inheritDoc */
	public function postAuthentication( $user, AuthenticationResponse $response ) {
		if ( $response->status === AuthenticationResponse::PASS ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $centralUser->exists() &&
				$centralUser->isAttached() &&
				$centralUser->getEmail() != $user->getEmail() &&
				!$this->readOnlyMode->isReadOnly()
			) {
				DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
					$centralUser = CentralAuthUser::getPrimaryInstance( $user );
					if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
						// something major changed?
						return;
					}

					$user->setEmail( $centralUser->getEmail() );
					// @TODO: avoid direct User object field access
					$user->mEmailAuthenticated = $centralUser->getEmailAuthenticationTimestamp();
					$user->saveSettings();
				} );
			}

			// Trigger edge login on the next pageview, except in SUL3 mode, when this is
			// the wrong domain / session.
			if ( !$this->sharedDomainUtils->isSharedDomain() ) {
				$this->logger->debug( 'Edge login on the next pageview after central login on shared domain' );
				$this->manager->getRequest()->setSessionData( 'CentralAuthDoEdgeLogin', true );
			}
		}
	}

	/** @inheritDoc */
	public function testUserCanAuthenticate( $username ) {
		$username = $this->userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_USABLE );
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
		$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $username );
		return $centralUser->exists() &&
			( $centralUser->isAttached() || !$userIdentity || !$userIdentity->isRegistered() ) &&
			!$centralUser->getPasswordObject() instanceof InvalidPassword;
	}

	/** @inheritDoc */
	public function testUserExists( $username, $flags = IDBAccessObject::READ_NORMAL ) {
		$username = $this->userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_USABLE );
		if ( $username === false ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		return $centralUser->exists();
	}

	/** @inheritDoc */
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if ( get_class( $req ) === PasswordAuthenticationRequest::class ) {
			if ( !$checkData ) {
				return StatusValue::newGood();
			}

			$username = $this->userNameUtils->getCanonical( $req->username, UserNameUtils::RIGOR_USABLE );
			if ( $username !== false ) {
				$centralUser = CentralAuthUser::getInstanceByName( $username );
				$userIdentity = $this->userIdentityLookup
					->getUserIdentityByName( $username, IDBAccessObject::READ_LATEST );
				if ( $centralUser->exists() &&
					( $centralUser->isAttached() ||
					!$userIdentity || !$userIdentity->isRegistered() )
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

	/** @inheritDoc */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		$username = $req->username !== null
			? $this->userNameUtils->getCanonical( $req->username, UserNameUtils::RIGOR_USABLE )
			: false;
		if ( $username === false ) {
			return;
		}

		if ( get_class( $req ) === PasswordAuthenticationRequest::class ) {
			$centralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $username, IDBAccessObject::READ_LATEST );
			if ( $centralUser->exists() &&
				( $centralUser->isAttached() || !$userIdentity || !$userIdentity->isRegistered() )
			) {
				$centralUser->setPassword( $req->password );
			}
		}
	}

	public function accountCreationType() {
		return self::TYPE_CREATE;
	}

	/** @inheritDoc */
	public function testUserForCreation( $user, $autocreate, array $options = [] ) {
		global $wgCentralAuthEnableGlobalRenameRequest;

		$options += [ 'flags' => IDBAccessObject::READ_NORMAL ];

		$status = parent::testUserForCreation( $user, $autocreate, $options );
		if ( !$status->isOK() ) {
			return $status;
		}

		$centralUser = DBAccessObjectUtils::hasFlags( $options['flags'], IDBAccessObject::READ_LATEST )
			? CentralAuthUser::getPrimaryInstance( $user )
			: CentralAuthUser::getInstance( $user );

		// Rename in progress?
		if ( $centralUser->renameInProgressOn( WikiMap::getCurrentWikiId(), $options['flags'] ) ) {
			$status->fatal( 'centralauth-rename-abortlogin', $user->getName() );
			return $status;
		}

		if ( !$this->isAutoCreatedByCentralAuth( $user, $autocreate ) ) {
			// Prevent creation if the user exists centrally
			if ( $centralUser->exists() ) {
				$status->fatal( 'centralauth-account-exists' );
				return $status;
			}

			// Prevent creation of a new account that would create a global account
			// if it'd steal the global name of existing unattached local accounts
			if ( $centralUser->listUnattached() && $autocreate === false ) {
				$status->fatal( 'centralauth-account-unattached-exists' );
				return $status;
			}

			// Block account creation if name is a pending rename request
			if ( $wgCentralAuthEnableGlobalRenameRequest &&
				$this->globalRenameRequestStore->nameHasPendingRequest( $user->getName() )
			) {
				$status->fatal( 'centralauth-account-rename-exists' );
				return $status;
			}
		}

		// Check CentralAuthAntiSpoof, if applicable. Assume the user will override if they can.
		if ( $this->antiSpoofAccounts && empty( $options['creating'] ) &&
			!RequestContext::getMain()->getAuthority()->isAllowed( 'override-antispoof' )
		) {
			$status->merge( $this->caAntiSpoofManager->testNewAccount(
				$user, new User, true, false, new NullLogger
			) );
		}

		return $status;
	}

	/**
	 * @param array $reqs
	 * @return AntiSpoofAuthenticationRequest|null
	 */
	private static function getAntiSpoofAuthenticationRequest( array $reqs ) {
		return AuthenticationRequest::getRequestByClass(
			$reqs,
			AntiSpoofAuthenticationRequest::class
		);
	}

	/** @inheritDoc */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$req = self::getPasswordAuthenticationRequest( $reqs );

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
		$antiSpoofReq = self::getAntiSpoofAuthenticationRequest( $reqs );
		$ret->merge( $this->caAntiSpoofManager->testNewAccount(
			$user, $creator, $this->antiSpoofAccounts,
			$antiSpoofReq && $antiSpoofReq->ignoreAntiSpoof
		) );

		return $ret;
	}

	/** @inheritDoc */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$req = self::getPasswordAuthenticationRequest( $reqs );
		if ( $req ) {
			if ( $req->username !== null && $req->password !== null ) {
				$centralUser = CentralAuthUser::getPrimaryInstance( $user );
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

	/** @inheritDoc */
	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		// Do the attach in finishAccountCreation instead of begin because now the user has been
		// added to database and local ID exists (which is needed in attach)
		$centralUser->attach( WikiMap::getCurrentWikiId(), 'new' );
		$this->databaseManager->getCentralPrimaryDB()->onTransactionCommitOrIdle(
			function () use ( $centralUser ) {
				$this->utilityService->scheduleCreationJobs( $centralUser );
			},
			__METHOD__
		);
		// if we're doing an SUL3 account creation, now that we have a user name,
		// set global pref for the user to participate in the rollout
		if ( $this->sharedDomainUtils->isSharedDomain() ) {
			$this->sharedDomainUtils->setSUL3RolloutGlobalPref( $user, true );
		}
		return null;
	}

	/** @inheritDoc */
	public function autoCreatedAccount( $user, $source ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		if ( !$centralUser->exists() ) {
			// For named accounts, this is a bug. beginPrimaryAccountCreation() should have created
			// the central account.
			// For temp accounts, it is normal. The central account gets created by
			// UserCreationHookHandler, but this method gets called first.
			if ( $user->isNamed() ) {
				$this->logger->warning(
					'Not centralizing auto-created user {username}, central account doesn\'t exist',
					[
						'user' => $user->getName(),
					]
				);
			}
		} elseif ( !$this->isAutoCreatedByCentralAuth( $user, $source )
			&& $centralUser->listUnattached()
		) {
			$this->logger->warning(
				'Not centralizing auto-created user {username}, unattached accounts exist',
				[
					'user' => $user->getName(),
					'source' => $source,
				]
			);
		} else {
			$this->logger->info(
				'Centralizing auto-created user {username}',
				[
					'user' => $user->getName(),
				]
			);
			$centralUser->attach( WikiMap::getCurrentWikiId(), 'login' );
			$centralUser->addLocalName( WikiMap::getCurrentWikiId() );

			if ( $centralUser->getEmail() != $user->getEmail() ) {
				$user->setEmail( $centralUser->getEmail() );
				$user->mEmailAuthenticated = $centralUser->getEmailAuthenticationTimestamp();
			}
		}
	}

	/**
	 * @param User $user
	 * @param string $source Autocreation source - the $autocreate parameter passed to
	 *   testUserForCreation(), or the $source parameter passed to autoCreatedAccount().
	 * @return bool
	 */
	private function isAutoCreatedByCentralAuth( User $user, string $source ): bool {
		if ( $source === AuthManager::AUTOCREATE_SOURCE_SESSION ) {
			// True if the autocreating session provider belongs to CentralAuth.
			// There isn't a clean way to obtain the session, but since we are autocreating
			// from the session, $user should be the session user.
			$sessionProvider = $user->getRequest()->getSession()->getProvider();
			return $sessionProvider instanceof CentralAuthSessionProvider
				|| $sessionProvider instanceof CentralAuthTokenSessionProvider;
		} elseif ( $source ) {
			// True if the autocreating authentication provider belongs to CentralAuth.
			$centralAuthPrimaryProviderIds = [
				$this->getUniqueId(),
				CentralAuthRedirectingPrimaryAuthenticationProvider::class,
				CentralAuthTemporaryPasswordPrimaryAuthenticationProvider::class,
			];
			return in_array( $source, $centralAuthPrimaryProviderIds, true );
		} else {
			// Not an autocreation at all.
			return false;
		}
	}
}
