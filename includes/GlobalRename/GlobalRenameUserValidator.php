<?php

/**
 * Verify whether a potential global rename is valid
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserValidator {
	/**
	 * Check that we can perform the rename
	 *
	 * @param User $oldUser
	 * @param User $newUser
	 *
	 * @return Status
	 */
	public function validate( User $oldUser, User $newUser ) {
		$status = new Status();

		if ( !User::isCreatableName( $newUser->getName() ) ) {
			$status->fatal( 'centralauth-rename-badusername' );
		}

		$caOldUser = CentralAuthUser::getInstance( $oldUser );
		if ( !$caOldUser->exists() ) {
			$status->fatal( 'centralauth-rename-doesnotexist' );
		}
		$caNewUser = CentralAuthUser::getInstance( $newUser );
		if ( $caNewUser->exists() ) {
			$status->fatal( 'centralauth-rename-alreadyexists' );
		}

		$unattached = $caNewUser->listUnattached();
		if ( $unattached ) {
			$status->fatal( 'centralauth-rename-unattached-intheway' );
		}

		// Check we're not currently renaming the user
		$renameState = $caOldUser->renameInProgress();
		if ( $renameState ) {
			$status->fatal( 'centralauth-rename-alreadyinprogress', $renameState[1] );
		}

		return $status;
	}
}
