<?php
/**
 * @section LICENSE
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

/**
 * Display a warning and instructions to a user who has logged in with
 * a username that was affected by single user login (SUL) finalization.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 */
class SpecialSulRenameWarning extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'SulRenameWarning' );
	}

	/**
	 * Checks if the given user (identified by an object) can execute this
	 * special page (as defined by $mRestriction).
	 *
	 * @param User $user The user to check
	 * @return bool Does the user have permission to view the page?
	 */
	public function userCanExecute( User $user ) {
		// Only allow users who have been force renamed
		return $user->getRequest()->getSessionData( 'CentralAuthForcedRename' ) === true;
	}

	/**
	 * @param string|null $subPage Ignored
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->outputHeader();

		$username = $this->getUser()->getName();
		$oldname = explode( '~', $username, 2 )[0];

		$output = $this->getOutput();
		$output->setPageTitle( $this->msg( 'sulrenamewarning-title', $username ) );
		$output->addWikiMsg( 'sulrenamewarning-renamed', $oldname, $username );
		$output->addWikiMsg( 'sulrenamewarning-usenew', $username );

		$request = $this->getUser()->getRequest();
		$prior = $request->getSessionData( 'SulRenameWarning' );
		if ( $prior ) {
			// Create a return to link for their original destination
			$returnToTitle = Title::newFromText( $prior['returnTo'] );
			if ( !$returnToTitle ) {
				$returnToTitle = Title::newMainPage();
			}
			$output->addReturnTo( $returnToTitle, $prior['returnToQuery'] );

			// Clear stored data from session
			$request->setSessionData( 'SulRenameWarning', null );
		}
	}
}
