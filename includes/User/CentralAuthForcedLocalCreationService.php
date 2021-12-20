<?php
/**
 * @section LICENSE
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
 */

namespace MediaWiki\Extension\CentralAuth\User;

use CentralAuthUtilityService;
use ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserFactory;
use SiteStatsUpdate;
use Status;

/**
 * Service for forcing a local account to be created.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthForcedLocalCreationService {
	/** @var UserFactory */
	private $userFactory;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/**
	 * @param UserFactory $userFactory
	 * @param CentralAuthUtilityService $utilityService
	 */
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
	 * @return Status
	 */
	public function attemptAutoCreateLocalUserFromName(
		string $username,
		Authority $performer = null,
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

		$centralUser = CentralAuthUser::getInstance( $user );

		if ( !$centralUser->exists() ) {
			return Status::newFatal( 'centralauth-createlocal-no-global-account' );
		}

		if ( $centralUser->isOversighted() ) {
			$canOversight = $performer && $performer->isAllowed( 'centralauth-oversight' );

			return Status::newFatal( $canOversight
				? 'centralauth-createlocal-suppressed'
				: 'centralauth-createlocal-no-global-account' );
		}

		$status = $this->utilityService->autoCreateUser( $user, false );
		if ( !$status->isGood() ) {
			return Status::wrap( $status );
		}

		// Add log entry
		if ( $performer ) {
			$logEntry = new ManualLogEntry( 'newusers', 'forcecreatelocal' );
			$logEntry->setPerformer( $performer->getUser() );
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
