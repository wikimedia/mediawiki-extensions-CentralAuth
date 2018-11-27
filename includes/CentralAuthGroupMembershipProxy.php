<?php

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
	 * @return bool
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
		$name = User::getCanonicalName( $name );
		if ( $name === false ) {
			return null;
		}
		$globalUser = CentralAuthUser::getMasterInstanceByName( $name );
		return $globalUser->exists() ? new CentralAuthGroupMembershipProxy( $globalUser ) : null;
	}

	/**
	 * @param int $id
	 * @return CentralAuthGroupMembershipProxy|null
	 */
	public static function newFromId( $id ) {
		$globalUser = CentralAuthUser::newMasterInstanceFromId( $id );
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
	function getGroups() {
		return $this->mGlobalUser->getGlobalGroups();
	}

	/**
	 * Replaces User::getGroupMemberships()
	 * @return UserGroupMembership[] Associative array of (group name => UserGroupMembership object)
	 */
	function getGroupMemberships() {
		$groups = $this->getGroups();
		return array_combine( $groups, array_map( function ( $group ) {
			$expgr = $this->mGlobalUser->getGlobalexpGroups();
			return new UserGroupMembership( $this->getId(), $group, $expgr[$group] );
		}, $groups ) );
	}

	/**
	 * replaces addUserGroup
	 * @param string[]|string $group
	 * @param string|null $expiry
	 *
	 * @return bool
	 */
	function addGroup( $group, $expiry = null ) {
		$this->mGlobalUser->addToGlobalGroups( $group, $expiry );
		return true;
	}

	/**
	 * replaces removeUserGroup
	 * @param string $group
	 *
	 * @return bool
	 */
	function removeGroup( $group ) {
		$this->mGlobalUser->removeFromGlobalGroups( $group );
		return true;
	}

	/**
	 * replaces touchUser
	 */
	function invalidateCache() {
		$this->mGlobalUser->invalidateCache();
	}

	/**
	 * @param string $wiki
	 * @return bool
	 */
	function attachedOn( $wiki ) {
		return $this->mGlobalUser->attachedOn( $wiki );
	}
}
