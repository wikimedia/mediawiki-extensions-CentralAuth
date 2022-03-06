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

use ErrorPageError;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\Renameuser\Hook\RenameUserCompleteHook;
use MediaWiki\Extension\Renameuser\Hook\RenameUserPreRenameHook;
use MediaWiki\Extension\Renameuser\Hook\RenameUserWarningHook;
use WikiMap;

class GlobalRenameHookHandler implements
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
			$oldCentral->adminUnattach( [ WikiMap::getCurrentWikiId() ] );
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

		if ( $newCentral->exists() && $oldCentral->renameInProgressOn( WikiMap::getCurrentWikiId() ) ) {
			// This is a global rename, just update the row.
			$oldCentral->updateLocalName( WikiMap::getCurrentWikiId(), $new );
		} else {
			$oldCentral->removeLocalName( WikiMap::getCurrentWikiId() );
			$newCentral->addLocalName( WikiMap::getCurrentWikiId() );
		}
	}
}
