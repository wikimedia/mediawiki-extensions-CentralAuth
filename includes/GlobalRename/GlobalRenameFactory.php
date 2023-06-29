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

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserRigorOptions;

/**
 * Constructs global renaming related command objects.
 *
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class GlobalRenameFactory {

	private JobQueueGroupFactory $jobQueueGroupFactory;
	private UserFactory $userFactory;

	private CentralAuthAntiSpoofManager $caAntiSpoofManager;
	private CentralAuthDatabaseManager $databaseManager;

	/**
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param UserFactory $userFactory
	 * @param CentralAuthAntiSpoofManager $caAntiSpoofManager
	 * @param CentralAuthDatabaseManager $databaseManager
	 */
	public function __construct(
		JobQueueGroupFactory $jobQueueGroupFactory,
		UserFactory $userFactory,
		CentralAuthAntiSpoofManager $caAntiSpoofManager,
		CentralAuthDatabaseManager $databaseManager
	) {
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->userFactory = $userFactory;
		$this->caAntiSpoofManager = $caAntiSpoofManager;
		$this->databaseManager = $databaseManager;
	}

	public function newGlobalRenameUser(
		UserIdentity $performer,
		CentralAuthUser $userToRename,
		string $newName
	): GlobalRenameUser {
		$userOld = $this->userFactory->newFromName( $userToRename->getName() );
		if ( !$userOld ) {
			throw new InvalidArgumentException( 'Name of the old user is not valid' );
		}

		$userNew = $this->userFactory->newFromName( $newName, UserRigorOptions::RIGOR_CREATABLE );
		if ( !$userNew ) {
			throw new InvalidArgumentException( 'Name old the new user is not creatable' );
		}

		return new GlobalRenameUser(
			$performer,
			$userOld,
			$userToRename,
			$userNew,
			// TODO: this is only used for caching, maybe we can create a Factory service
			// for CAUsers and related caching and inject that instead
			CentralAuthUser::getInstance( $userNew ),
			new GlobalRenameUserStatus( $newName ),
			$this->jobQueueGroupFactory,
			new GlobalRenameUserDatabaseUpdates( $this->databaseManager ),
			new GlobalRenameUserLogger( $performer ),
			$this->caAntiSpoofManager
		);
	}

}
