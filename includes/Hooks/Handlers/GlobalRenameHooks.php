<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use CentralAuthUser;
use ErrorPageError;
use RenameUserCompleteHook;
use RenameUserPreRenameHook;
use RenameUserWarningHook;

class GlobalRenameHooks implements
	RenameUserCompleteHook,
	RenameUserPreRenameHook,
	RenameUserWarningHook
{

	/**
	 * Warn bureaucrat about possible conflicts with unified accounts
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @param array[] &$warnings
	 * @throws ErrorPageError
	 */
	public function onRenameUserWarning( string $oldUsername, string $newUsername, array &$warnings ): void {
		$oldCentral = CentralAuthUser::getPrimaryInstanceByName( $oldUsername );
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$warnings[] = [ 'centralauth-renameuser-merged', $oldUsername, $newUsername ];
		}
		if ( $oldCentral->renameInProgress() ) {
			$warnings[] = [ 'centralauth-renameuser-global-inprogress', $oldUsername ];
		}

		$newCentral = CentralAuthUser::getPrimaryInstanceByName( $newUsername );
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
		$oldCentral = CentralAuthUser::getPrimaryInstanceByName( $old );
		// If we're doing a global rename, the account will not get unattached
		// because the old account no longer exists
		if ( $oldCentral->exists() && $oldCentral->isAttached() ) {
			$oldCentral->adminUnattach( [ wfWikiID() ] );
		}
	}

	/**
	 * When renaming an account, ensure that the presence records are updated.
	 * @param int $uid
	 * @param string $old
	 * @param string $new
	 */
	public function onRenameUserComplete( int $uid, string $old, string $new ): void {
		$oldCentral = CentralAuthUser::getPrimaryInstanceByName( $old );
		$newCentral = CentralAuthUser::getPrimaryInstanceByName( $new );

		if ( $newCentral->exists() && $oldCentral->renameInProgressOn( wfWikiID() ) ) {
			// This is a global rename, just update the row.
			$oldCentral->updateLocalName( wfWikiID(), $new );
		} else {
			$oldCentral->removeLocalName( wfWikiID() );
			$newCentral->addLocalName( wfWikiID() );
		}
	}
}
