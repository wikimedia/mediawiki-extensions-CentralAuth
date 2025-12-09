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
