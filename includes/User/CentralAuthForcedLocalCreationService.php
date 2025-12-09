<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Deferred\SiteStatsUpdate;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Status\Status;
use MediaWiki\User\UserFactory;

/**
 * Service for forcing a local account to be created.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthForcedLocalCreationService {

	private UserFactory $userFactory;
	private CentralAuthUtilityService $utilityService;

	public function __construct(
		UserFactory $userFactory,
		CentralAuthUtilityService $utilityService
	) {
		$this->userFactory = $userFactory;
		$this->utilityService = $utilityService;
	}

	/**
	 * Attempt to create a local user for the specified username.
	 * @param string $username
	 * @param Authority|null $performer
	 * @param string|null $reason
	 */
	public function attemptAutoCreateLocalUserFromName(
		string $username,
		?Authority $performer = null,
		$reason = null
	): Status {
		$user = $this->userFactory->newFromName( $username );

		if ( !$user ) {
			// invalid username
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $user->getId() ) {
			return Status::newFatal( 'centralauth-createlocal-already-exists' );
		}

		$performer ??= $user;
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( !$centralUser->exists() ) {
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $centralUser->isSuppressed() ) {
			$canSuppress = $performer->isAllowed( 'centralauth-suppress' );

			return Status::newFatal( $canSuppress
				? 'centralauth-createlocal-suppressed'
				: 'centralauth-createlocal-no-global-account' );
		}

		$status = $this->utilityService->autoCreateUser( $user, false, $performer );
		if ( !$status->isGood() ) {
			return Status::wrap( $status );
		}

		// Add log entry. The following message is generated here:
		// * logentry-newusers-forcecreatelocal
		$logEntry = new ManualLogEntry( 'newusers', 'forcecreatelocal' );
		$logEntry->setPerformer( $performer->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		if ( $reason !== null ) {
			$logEntry->setComment( $reason );
		}
		$logEntry->setParameters( [
			'4::userid' => $user->getId(),
		] );

		$logId = $logEntry->insert();
		$logEntry->publish( $logId );

		// Update user count
		SiteStatsUpdate::factory( [ 'users' => 1 ] )->doUpdate();

		return Status::newGood();
	}
}
