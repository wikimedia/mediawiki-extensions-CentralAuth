<?php

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;

/**
 * Service for forcing a local account to be created.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthForcedLocalCreationService {
	/** @var UserFactory */
	private $userFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/**
	 * @param UserFactory $userFactory
	 * @param PermissionManager $permissionManager
	 * @param CentralAuthUtilityService $utilityService
	 */
	public function __construct(
		UserFactory $userFactory,
		PermissionManager $permissionManager,
		CentralAuthUtilityService $utilityService
	) {
		$this->userFactory = $userFactory;
		$this->permissionManager = $permissionManager;
		$this->utilityService = $utilityService;
	}

	/**
	 * Attempt to create a local user for the specified username.
	 * @param string $username
	 * @param User|null $performer
	 * @param string|null $reason
	 * @return Status
	 */
	public function attemptAutoCreateLocalUserFromName(
		string $username,
		$performer = null,
		$reason = null
	) : Status {
		$user = $this->userFactory->newFromName( $username );

		if ( !$user ) {
			// invalid username
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $user->getId() ) {
			return Status::newFatal( 'centralauth-createlocal-already-exists' );
		}

		$centralUser = CentralAuthUser::getInstance( $user );

		if ( !$centralUser->exists() ) {
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $centralUser->isOversighted() ) {
			$canOversight = $performer && $this->permissionManager
					->userHasRight( $performer, 'centralauth-oversight' );

			return Status::newFatal( $canOversight
				? 'centralauth-createlocal-suppressed'
				: 'centralauth-createlocal-no-global-account' );
		}

		$status = $this->utilityService->autoCreateUser( $user );
		if ( !$status->isGood() ) {
			return Status::wrap( $status );
		}

		// Add log entry
		if ( $performer ) {
			$logEntry = new ManualLogEntry( 'newusers', 'forcecreatelocal' );
			$logEntry->setPerformer( $performer );
			$logEntry->setTarget( $user->getUserPage() );
			$logEntry->setComment( $reason );
			$logEntry->setParameters( [
				'4::userid' => $user->getId(),
			] );

			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}

		// Update user count
		SiteStatsUpdate::factory( [ 'users' => 1 ] )->doUpdate();

		return Status::newGood();
	}
}
