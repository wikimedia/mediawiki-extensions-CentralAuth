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
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class UserCreationHookHandler implements LocalUserCreatedHook {

	private CentralAuthAntiSpoofManager $caAntiSpoofManager;
	private CentralAuthDatabaseManager $databaseManager;
	private CentralAuthUtilityService $utilityService;

	public function __construct(
		CentralAuthAntiSpoofManager $caAntiSpoofManager,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUtilityService $utilityService
	) {
		$this->caAntiSpoofManager = $caAntiSpoofManager;
		$this->databaseManager = $databaseManager;
		$this->utilityService = $utilityService;
	}

	/**
	 * Populate localuser table and update AntiSpoof system
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @return void
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );

		// If some other AuthManager PrimaryAuthenticationProvider is creating
		// the user, we should still create a central user for them so
		// CentralAuthIdLookup can have an ID for this new user right away.
		if ( !$centralUser->exists() && !$centralUser->listUnattached() ) {
			if ( $centralUser->register( null, $user->getEmail() ) ) {
				$centralUser->attach( WikiMap::getCurrentWikiId(), 'new' );
				$this->databaseManager->getCentralPrimaryDB()->onTransactionCommitOrIdle(
					function () use ( $centralUser ) {
						$this->utilityService->scheduleCreationJobs( $centralUser );
					},
					__METHOD__
				);
			}
		}

		$centralUser->addLocalName( WikiMap::getCurrentWikiId() );

		// Record the username's thing-bob after a user account is created
		$spoof = $this->caAntiSpoofManager->getSpoofUser( $user->getName() );
		$spoof->record();
	}
}
