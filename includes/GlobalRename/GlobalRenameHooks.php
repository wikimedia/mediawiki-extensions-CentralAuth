<?php

class GlobalRenameHooks implements
	RenameUserWarningHook,
	RenameUserPreRenameHook,
	RenameUserRenameCompleteHook
{
	/**
	 * Warn bureaucrat about possible conflicts with unified accounts
	 * @param string $oldUsername The old username as a page title.
	 * @param string $newUsername The new username as a page title.
	 * @param array &$warnings An array with 1 or more message keys, and 1 or more parameters
	 * for the warnings to be shown
	 * @throws ErrorPageError
	 */
	public function onRenameUserWarning( string $oldUsername, string $newUsername, array &$warnings ): void {
		$oldCentral = CentralAuthUser::getMasterInstanceByName( $oldUsername );
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$warnings[] = [ 'centralauth-renameuser-merged', $oldUsername, $newUsername ];
		}
		if ( $oldCentral->renameInProgress() ) {
			$warnings[] = [ 'centralauth-renameuser-global-inprogress', $oldUsername ];
		}

		$newCentral = CentralAuthUser::getMasterInstanceByName( $newUsername );
		if ( $newCentral->exists() && !$newCentral->isAttached() ) {
			$warnings[] = [ 'centralauth-renameuser-reserved', $oldUsername, $newUsername ];
		}

		if ( $newCentral->renameInProgress() ) {
			$warnings[] = [ 'centralauth-renameuser-global-inprogress', $newUsername ];
			// Can potentially be renaming two accounts into the same name, so throw an error
			throw new ErrorPageError(
				'error', 'centralauth-renameuser-global-inprogress', [ $newUsername ]
			);
		}
	}

	/**
	 * @param int $uid
	 * @param string $old
	 * @param string $new
	 */
	public function onRenameUserPreRename( int $uid, string $old, string $new ): void {
		$oldCentral = CentralAuthUser::getMasterInstanceByName( $old );
		// If we're doing a global rename, the account will not get unattached
		// because the old account no longer exists
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$oldCentral->adminUnattach( [ wfWikiID() ] );
		}
	}

	/**
	 * When renaming an account, ensure that the presence records are updated.
	 * @param int $uid The user ID
	 * @param string $old The old username
	 * @param string $new The new username
	 */
	public function onRenameUserRenameComplete( int $uid, string $old, string $new ) : void {
		$oldCentral = CentralAuthUser::getMasterInstanceByName( $old );
		$newCentral = CentralAuthUser::getMasterInstanceByName( $new );

		if ( $newCentral->exists() && $oldCentral->renameInProgressOn( wfWikiID() ) ) {
			// This is a global rename, just update the row.
			$oldCentral->updateLocalName( wfWikiID(), $new );
		} else {
			$oldCentral->removeLocalName( wfWikiID() );
			$newCentral->addLocalName( wfWikiID() );
		}
	}
}
