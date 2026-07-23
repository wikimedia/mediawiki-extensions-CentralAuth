<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider;
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
	private CentralAuthConnectionProvider $caConnectionProvider;

	public function __construct(
		JobQueueGroupFactory $jobQueueGroupFactory,
		UserFactory $userFactory,
		CentralAuthAntiSpoofManager $caAntiSpoofManager,
		CentralAuthConnectionProvider $caConnectionProvider
	) {
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->userFactory = $userFactory;
		$this->caAntiSpoofManager = $caAntiSpoofManager;
		$this->caConnectionProvider = $caConnectionProvider;
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

		// Avoid repeats of T343958. Some forms of creating a CentralAuthUser
		// object do not canonicalize the username.
		if ( $userOld->getName() !== $userToRename->getName() ) {
			throw new InvalidArgumentException( 'Name of the global user is not in canonical form' );
		}

		$userNew = $this->userFactory->newFromName( $newName, UserRigorOptions::RIGOR_CREATABLE );
		if ( !$userNew ) {
			throw new InvalidArgumentException( 'Name of the new user is not creatable' );
		}

		return new GlobalRenameUser(
			$performer,
			$userOld,
			$userToRename,
			$userNew,
			CentralAuthUser::getPrimaryInstance( $userNew ),
			$this->newGlobalRenameUserStatus( $userNew->getName() ),
			$this->jobQueueGroupFactory,
			new GlobalRenameUserDatabaseUpdates( $this->caConnectionProvider ),
			new GlobalRenameUserLogger( $performer ),
			$this->caAntiSpoofManager
		);
	}

	public function newGlobalRenameUserStatus( string $username ): GlobalRenameUserStatus {
		return new GlobalRenameUserStatus(
			$this->caConnectionProvider,
			$username
		);
	}
}
