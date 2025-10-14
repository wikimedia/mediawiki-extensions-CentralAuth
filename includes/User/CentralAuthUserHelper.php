<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Permissions\Authority;
use MediaWiki\Status\Status;

/**
 * A thin wrapper around the static methods of CentralAuthUser for looking up users.
 * Apart from returning a user by name or ID, it also checks whether the viewer is allowed
 * to see the looked up user.
 *
 * @since 1.45
 */
class CentralAuthUserHelper {

	/**
	 * Looks up for a central user with the specified name. If authority is provided,
	 * checks if the user is visible to that authority. Uses the primary database.
	 * @param string $name The username, may be non-canonical
	 * @param Authority|null $viewer
	 * @return Status<CentralAuthUser>
	 */
	public function getCentralAuthUserByNameFromPrimary( string $name, ?Authority $viewer = null ): Status {
		return $this->getCentralAuthUserByNameInternal(
			CentralAuthUser::getPrimaryInstanceByName( ... ),
			$name,
			$viewer
		);
	}

	/**
	 * Looks up for a central user with the specified name. If authority is provided,
	 * checks if the user is visible to that authority.
	 * @param string $name The username, may be non-canonical
	 * @param Authority|null $viewer
	 * @return Status<CentralAuthUser>
	 */
	public function getCentralAuthUserByName( string $name, ?Authority $viewer = null ): Status {
		return $this->getCentralAuthUserByNameInternal(
			CentralAuthUser::getInstanceByName( ... ),
			$name,
			$viewer
		);
	}

	/**
	 * Looks up for a central user with the specified ID. If authority is provided,
	 * checks if the user is visible to that authority. Uses the primary database.
	 * @return Status<CentralAuthUser>
	 */
	public function getCentralAuthUserByIdFromPrimary( int $id, ?Authority $viewer = null ): Status {
		return $this->getCentralAuthUserByIdInternal(
			CentralAuthUser::newPrimaryInstanceFromId( ... ),
			$id,
			$viewer
		);
	}

	/**
	 * Looks up for a central user with the specified ID. If authority is provided,
	 * checks if the user is visible to that authority.
	 * @return Status<CentralAuthUser>
	 */
	public function getCentralAuthUserById( int $id, ?Authority $viewer = null ): Status {
		return $this->getCentralAuthUserByIdInternal(
			CentralAuthUser::newFromId( ... ),
			$id,
			$viewer
		);
	}

	private function getCentralAuthUserByNameInternal(
		callable $fetchUserByName,
		string $name,
		?Authority $viewer
	): Status {
		$name = trim( $name );
		if ( $name === '' ) {
			return Status::newFatal( 'nouserspecified' );
		}

		$globalUser = $fetchUserByName( $name );

		// If the user exists, but is hidden from the viewer, pretend that it does
		// not exist. - T285190
		if (
			!$globalUser->exists() ||
			( $viewer !== null && !$this->isUserVisibleToAuthority( $globalUser, $viewer ) )
		) {
			return Status::newFatal( 'nosuchusershort', $name );
		}

		return Status::newGood( $globalUser );
	}

	private function getCentralAuthUserByIdInternal(
		callable $fetchUserById,
		int $id,
		?Authority $viewer
	): Status {
		$globalUser = $fetchUserById( $id );

		// If the user exists, but is hidden from the viewer, pretend that it does
		// not exist. - T285190/T260863
		if (
			!$globalUser ||
			( $viewer !== null && !$this->isUserVisibleToAuthority( $globalUser, $viewer ) )
		) {
			return Status::newFatal( 'noname' );
		}

		return Status::newGood( $globalUser );
	}

	private function isUserVisibleToAuthority( CentralAuthUser $user, Authority $authority ): bool {
		if ( !$user->isHidden() && !$user->isSuppressed() ) {
			return true;
		}

		// User is hidden or suppressed, check if the authority has the right to see them
		return $authority->isAllowed( 'centralauth-suppress' );
	}
}
