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
use Title;
use UserGroupMembership;

/**
 * Cut-down copy of User interface for local-interwiki-database
 * user rights manipulation.
 */
class CentralAuthGroupMembershipProxy {
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var CentralAuthUser
	 */
	private $mGlobalUser;

	/**
	 * @param CentralAuthUser $user
	 */
	private function __construct( CentralAuthUser $user ) {
		$this->name = $user->getName();
		$this->mGlobalUser = $user;
	}

	/**
	 * @param string $wikiID Unused
	 * @param int $id
	 * @return string|false
	 */
	public static function whoIs( $wikiID, $id ) {
		$user = self::newFromId( $id );
		if ( $user ) {
			return $user->name;
		} else {
			return false;
		}
	}

	/**
	 * @param string $name
	 * @return CentralAuthGroupMembershipProxy|null
	 */
	public static function newFromName( $name ) {
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$name = $userNameUtils->getCanonical( $name );
		if ( $name === false ) {
			return null;
		}
		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $name );
		return $globalUser->exists() ? new CentralAuthGroupMembershipProxy( $globalUser ) : null;
	}

	/**
	 * @param int $id
	 * @return CentralAuthGroupMembershipProxy|null
	 */
	public static function newFromId( $id ) {
		$globalUser = CentralAuthUser::newPrimaryInstanceFromId( $id );
		return $globalUser ? new CentralAuthGroupMembershipProxy( $globalUser ) : null;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->mGlobalUser->getId();
	}

	/**
	 * @return bool
	 */
	public function isAnon() {
		return $this->getId() == 0;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Title
	 */
	public function getUserPage() {
		return Title::makeTitle( NS_USER, $this->getName() );
	}

	/**
	 * Replaces getUserGroups()
	 * @return mixed
	 */
	public function getGroups() {
		return $this->mGlobalUser->getGlobalGroups();
	}

	/**
	 * Replaces User::getGroupMemberships()
	 * @return UserGroupMembership[] Associative array of (group name => UserGroupMembership object)
	 */
	public function getGroupMemberships() {
		$memberships = [];

		foreach ( $this->mGlobalUser->getGlobalGroupsWithExpiration() as $groupName => $expiry ) {
			$memberships[$groupName] = new UserGroupMembership( $this->getId(), $groupName, $expiry );
		}

		return $memberships;
	}

	/**
	 * replaces addUserGroup
	 * @param string $group
	 * @param string|null $expiry
	 *
	 * @return bool
	 */
	public function addGroup( string $group, string $expiry = null ) {
		$this->mGlobalUser->addToGlobalGroup( $group, $expiry );
		return true;
	}

	/**
	 * replaces removeUserGroup
	 * @param string $group
	 *
	 * @return bool
	 */
	public function removeGroup( $group ) {
		$this->mGlobalUser->removeFromGlobalGroups( $group );
		return true;
	}

	/**
	 * replaces touchUser
	 */
	public function invalidateCache() {
		$this->mGlobalUser->invalidateCache();
	}

	/**
	 * @param string $wiki
	 * @return bool
	 */
	public function attachedOn( $wiki ) {
		return $this->mGlobalUser->attachedOn( $wiki );
	}
}
