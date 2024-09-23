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

use ExtensionRegistry;
use IDBAccessObject;
use MailAddress;
use MediaWiki\Auth\AbstractTemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\TemporaryPasswordAuthenticationRequest;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Mail\Emailer;
use MediaWiki\MainConfigNames;
use MediaWiki\Password\Password;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * A primary authentication provider that uses the temporary password field in
 * the 'globaluser' table. Adapted from core TemporaryPasswordPrimaryAuthenticationProvider.
 *
 * A successful login will force a password reset.
 *
 * @note For proper operation, this should generally come before any other
 *  password-based authentication providers
 *  (especially the core TemporaryPasswordPrimaryAuthenticationProvider).
 */
class CentralAuthTemporaryPasswordPrimaryAuthenticationProvider
	extends AbstractTemporaryPasswordPrimaryAuthenticationProvider
{

	private Emailer $emailer;
	private LanguageNameUtils $languageNameUtils;
	private UserIdentityLookup $userIdentityLookup;
	private CentralAuthDatabaseManager $databaseManager;
	private CentralAuthUtilityService $utilityService;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		IConnectionProvider $dbProvider,
		Emailer $emailer,
		LanguageNameUtils $languageNameUtils,
		UserIdentityLookup $userIdentityLookup,
		UserOptionsLookup $userOptionsLookup,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUtilityService $utilityService,
		SharedDomainUtils $sharedDomainUtils,
		array $params = []
	) {
		parent::__construct( $dbProvider, $userOptionsLookup, $params );
		$this->emailer = $emailer;
		$this->languageNameUtils = $languageNameUtils;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->databaseManager = $databaseManager;
		$this->utilityService = $utilityService;
		$this->sharedDomainUtils = $sharedDomainUtils;
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
	public function getAuthenticationRequests( $action, array $options ) {
		if ( $this->sharedDomainUtils->isSul3Enabled( $this->manager->getRequest() )
			&& !$this->sharedDomainUtils->isSharedDomain()
			&& in_array( $action, [ AuthManager::ACTION_LOGIN, AuthManager::ACTION_CREATE ], true )
		) {
			return [];
		}

		return parent::getAuthenticationRequests( $action, $options );
	}

	/** @inheritDoc */
	protected function getTemporaryPassword( string $username, $flags = IDBAccessObject::READ_NORMAL ): array {
		// Only allow central accounts with nothing weird going on.
		$centralUser = CentralAuthUser::getInstanceByName( $username );
		if ( !$centralUser->exists() || $centralUser->canAuthenticate() !== true ) {
			return [ null, null ];
		}
		$localUser = $this->userIdentityLookup->getUserIdentityByName( $username );
		if ( !$centralUser->isAttached() && $localUser && $localUser->isRegistered() ) {
			return [ null, null ];
		}

		$db = $this->databaseManager->getCentralDBFromRecency( $flags );
		$row = $db->newSelectQueryBuilder()
			->select( [ 'gu_password_reset_key', 'gu_password_reset_expiration' ] )
			->from( 'globaluser' )
			->where( [ 'gu_name' => $username ] )
			->recency( $flags )
			->caller( __METHOD__ )->fetchRow();

		if ( !$row ) {
			return [ null, null ];
		}

		return [
			$this->getPassword( $row->gu_password_reset_key ),
			$row->gu_password_reset_expiration,
		];
	}

	/** @inheritDoc */
	protected function setTemporaryPassword( string $username, Password $tempPassHash, $tempPassTime ): void {
		$db = $this->databaseManager->getCentralPrimaryDB();
		$db->newUpdateQueryBuilder()
			->update( 'globaluser' )
			->set( [
				'gu_password_reset_key' => $tempPassHash->toString(),
				'gu_password_reset_expiration' => $db->timestampOrNull( $tempPassTime ),
			] )
			->where( [ 'gu_name' => $username ] )
			->caller( __METHOD__ )->execute();
	}

	/** @inheritDoc */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		/** @var TemporaryPasswordAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, TemporaryPasswordAuthenticationRequest::class
		);
		if ( $req && $req->username !== null && $req->password !== null ) {
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
		}
		return parent::beginPrimaryAccountCreation( $user, $creator, $reqs );
	}

	/** @inheritDoc */
	public function finishAccountCreation( $user, $creator, AuthenticationResponse $res ) {
		$ret = parent::finishAccountCreation( $user, $creator, $res );

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

		return $ret;
	}

	/** @inheritDoc */
	protected function maybeSendPasswordResetEmail( TemporaryPasswordAuthenticationRequest $req ): void {
		// Send email after DB commit (the callback does not run in case of DB rollback)
		$this->databaseManager->getCentralPrimaryDB()->onTransactionCommitOrIdle(
			function () use ( $req ) {
				$this->sendPasswordResetEmail( $req );
			},
			__METHOD__
		);
	}

	/** @inheritDoc */
	protected function sendPasswordResetEmail( TemporaryPasswordAuthenticationRequest $req ): void {
		global $wgConf;
		$user = User::newFromName( $req->username );
		if ( !$user ) {
			return;
		}
		if ( $user->isRegistered() ) {
			parent::sendPasswordResetEmail( $req );
			return;
		}

		// Hint that the user can choose to require email address to request a temporary password.
		// Since the local user doesn't exist, customize the email to refer to GlobalPreferences.
		$centralUser = CentralAuthUser::getInstanceByName( $req->username );
		$homewiki = $centralUser->getHomeWiki();
		[ , $userLanguage ] = $wgConf->siteFromDB( $homewiki );
		if (
			!$userLanguage ||
			!$this->languageNameUtils->isSupportedLanguage( $userLanguage )
		) {
			$userLanguage = 'en';
		}

		$callerIsAnon = IPUtils::isValid( $req->caller );
		$callerName = $callerIsAnon ? $req->caller : User::newFromName( $req->caller )->getName();
		$passwordMessage = wfMessage( 'passwordreset-emailelement', $user->getName(),
			$req->password )->inLanguage( $userLanguage );
		$emailMessage = wfMessage( $callerIsAnon ? 'passwordreset-emailtext-ip'
			: 'passwordreset-emailtext-user' )->inLanguage( $userLanguage );
		$body = $emailMessage->params( $callerName, $passwordMessage->text(), 1,
			'<' . Title::newMainPage()->getCanonicalURL() . '>',
			round( $this->newPasswordExpiry / 86400 ) )->text();

		if (
			!$this->userOptionsLookup->getBoolOption( $user, 'requireemail' )
		) {
			if ( ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' ) ) {
				// Hint about global preferences if the local user doesn't exist
				$centralUser = CentralAuthUser::getInstanceByName( $req->username );
				$homewiki = $centralUser->getHomeWiki();
				if ( $homewiki ) {
					$url = WikiMap::getForeignURL( $homewiki,
						'Special:GlobalPreferences', 'mw-prefsection-personal-email' );
					if ( $url ) {
						$body .= "\n\n" . wfMessage( 'passwordreset-emailtext-require-email' )
								->inLanguage( $userLanguage )
								->params( "<$url>" )
								->text();
					}
				}
			}
		}

		$subject = wfMessage( 'passwordreset-emailtitle' )->inLanguage( $userLanguage )->text();

		$passwordSender = $this->config->get( MainConfigNames::PasswordSender );
		$sender = new MailAddress( $passwordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text() );

		$to = new MailAddress(
			$centralUser->getEmail(),
			$centralUser->getName(),
			// No getRealName() / user_real_name equivalent for CentralUser
			null
		);

		$this->emailer->send(
			$to,
			$sender,
			$subject,
			$body,
		);
	}
}
