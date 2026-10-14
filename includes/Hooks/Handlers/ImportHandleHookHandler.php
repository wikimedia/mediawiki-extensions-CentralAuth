<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\ImportHandleUnknownUserHook;
use MediaWiki\User\UserFactory;

class ImportHandleHookHandler implements ImportHandleUnknownUserHook {

	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly CentralAuthUtilityService $utilityService,
	) {
	}

	/**
	 * Auto-create a user on import
	 * @param string $name
	 * @return bool|void
	 */
	public function onImportHandleUnknownUser( $name ) {
		$user = $this->userFactory->newFromName( $name );
		if ( $user ) {
			$centralUser = CentralAuthUser::getPrimaryInstance( $user );

			$status = $centralUser->exists() ?
				$this->utilityService->autoCreateUser( $user ) : false;

			if ( $status && $status->isGood() ) {
				$centralUser->invalidateCache();
				return false;
			}
		}
	}
}
