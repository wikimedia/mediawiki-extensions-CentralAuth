<?php
/**
 * @license GPL-2.0-or-later
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
