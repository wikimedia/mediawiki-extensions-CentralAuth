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

use CentralAuthUser;
use CentralAuthUtilityService;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use User;
use WikiMap;

class UserCreationHookHandler implements
	LocalUserCreatedHook
{
	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var CentralAuthUtilityService */
	private $utilityService;

	/**
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param CentralAuthUtilityService $utilityService
	 */
	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUtilityService $utilityService
	) {
		$this->databaseManager = $databaseManager;
		$this->utilityService = $utilityService;
	}

	/**
	 * Make sure migration information in localuser table is populated
	 * on local account creation
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
				$this->databaseManager->getCentralDB( DB_PRIMARY )->onTransactionCommitOrIdle(
					function () use ( $centralUser ) {
						$this->utilityService->scheduleCreationJobs( $centralUser );
					},
					__METHOD__
				);
			}
		}

		$centralUser->addLocalName( WikiMap::getCurrentWikiId() );
	}
}
