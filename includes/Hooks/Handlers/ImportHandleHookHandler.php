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
use MediaWiki\Hook\ImportHandleUnknownUserHook;
use MediaWiki\User\UserFactory;

class ImportHandleHookHandler implements
	ImportHandleUnknownUserHook
{
	/** @var CentralAuthUtilityService */
	private $utilityService;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param CentralAuthUtilityService $utilityService
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		CentralAuthUtilityService $utilityService,
		UserFactory $userFactory
	) {
		$this->utilityService = $utilityService;
		$this->userFactory = $userFactory;
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

			if ( $centralUser->exists() && $this->utilityService->autoCreateUser( $user )->isGood() ) {
				$centralUser->invalidateCache();
				return false;
			}
		}
	}
}
