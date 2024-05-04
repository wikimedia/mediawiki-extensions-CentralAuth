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

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;

class EmailableUser extends User {

	/**
	 * Static factory method for creation from username.
	 *
	 * @param string $name Username, validated by Title::newFromText()
	 * @param string|bool $validate Type of validation to use
	 *   Takes the same parameters as UserNameUtils::getCanonical(),
	 *   except that true is accepted as an alias for RIGOR_VALID
	 *   Use of UserNameUtils' class public constants RIGOR_* is preferred
	 *   - RIGOR_NONE        No validation
	 *   - RIGOR_VALID       Valid for batch processes
	 *   - RIGOR_USABLE      Valid for batch processes and login
	 *   - RIGOR_CREATABLE   Valid for batch processes, login and account creation
	 *
	 * @return EmailableUser|false EmailableUser object, or false if the
	 *    username is invalid (e.g. if it contains illegal characters or is an IP address).
	 *    If the username is not present in the database, the result will be a EmailableUser
	 *    object with a name, zero user ID and default settings.
	 */
	public static function newFromName( $name, $validate = UserNameUtils::RIGOR_VALID ) {
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		if ( $validate === true ) {
			$validate = UserNameUtils::RIGOR_VALID;
		}
		$name = $userNameUtils->getCanonical( $name, $validate );
		if ( $name === false ) {
			return false;
		} else {
			# Create unloaded user object
			$u = new EmailableUser;
			$u->mName = $name;
			$u->mFrom = 'name';
			$u->setItemLoaded( 'name' );
			return $u;
		}
	}

	/**
	 * Generate a new e-mail confirmation token and send a confirmation/invalidation
	 * mail to the user's given address.
	 *
	 * @return Status
	 */
	public function sendConfirmAndMigrateMail() {
		// 14 days
		$tokenLife = 14 * 24 * 60 * 60;

		$token = $this->confirmationToken( $expiration );

		// we want this token to last a little bit longer since we are cold-emailing
		// users and we really want as many responses as possible
		$now = time();
		$expires = $now + $tokenLife;
		$expiration = wfTimestamp( TS_MW, $expires );
		$this->mEmailTokenExpires = $expiration;

		if ( $this->isEmailConfirmed() ) {
			// Hack to bypass localization of 'Special:'
			// @see User::getTokenUrl
			$mergeAccountUrl = Title::makeTitle( NS_MAIN, 'Special:MergeAccount' )->getCanonicalURL();
		} else {
			// create a "token url" for MergeAccount since we have added email
			// confirmation there
			$mergeAccountUrl = $this->getTokenUrl( 'MergeAccount', $token );
		}

		$invalidateURL = $this->invalidationTokenUrl( $token );
		$this->saveSettings();

		return $this->sendMail(
			wfMessage( 'centralauth-finishglobaliseemail_subject' )->text(),
			wfMessage( "centralauth-finishglobaliseemail_body",
				$this->getRequest()->getIP(),
				$this->getName(),
				$mergeAccountUrl
				)->dateTimeParams(
					$expiration
				)->params(
					$invalidateURL
				)->dateParams(
					$expiration
				)->timeParams(
					$expiration
				)->text()
		);
	}
}
