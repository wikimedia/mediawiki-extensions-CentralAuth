<?php


/**
 * Cut-down copy of User interface for local-interwiki-database
 * user rights manipulation.
 */
class CentralAuthGroupMembershipProxy {
	private function __construct( $user ) {
		$this->name = $user->getName();
		$this->mGlobalUser = $user;
	}

	public static function whoIs( $database, $id ) {
		$user = self::newFromId( $database, $id );
		if( $user ) {
			return $user->name;
		} else {
			return false;
		}
	}

	public static function newFromName( $name ) {
		$globalUser = new CentralAuthUser( $name );
		return $globalUser->exists() ? new CentralAuthGroupMembershipProxy( $globalUser ) : null;
	}

	public function getId() {
		return $this->mGlobalUser->getId();
	}

	public function isAnon() {
		return $this->getId() == 0;
	}

	public function getName() {
		return $this->name;
	}

	public function getUserPage() {
		return Title::makeTitle( NS_USER, $this->getName() );
	}

	// Replaces getUserGroups()
	function getGroups() {
		return $this->mGlobalUser->getGlobalGroups();
	}

	// replaces addUserGroup
	function addGroup( $group ) {
		$this->mGlobalUser->addToGlobalGroups( $group );
	}

	// replaces removeUserGroup
	function removeGroup( $group ) {
		$this->mGlobalUser->removeFromGlobalGroups( $group );
	}

	// replaces touchUser
	function invalidateCache() {
		$this->mGlobalUser->invalidateCache();
	}
}
