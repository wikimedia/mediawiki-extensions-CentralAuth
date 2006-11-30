<?php

/**
 * Quickie test implementation using local test database
 */
class CentralAuthPlugin extends AuthPlugin {
	
	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @return bool
	 * @public
	 */
	function userExists( $username ) {
		$user = new CentralAuthUser( $username );
		return $user->exists();
	}

	/**
	 * Check if a username+password pair is a valid login.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param $username String: username.
	 * @param $password String: user password.
	 * @return bool
	 * @public
	 */
	function authenticate( $username, $password ) {
		$user = new CentralAuthUser( $username );
		return $user->authenticate( $password ) == "ok";
	}

	/**
	 * When a user logs in, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param User $user
	 * @public
	 */
	function updateUser( &$user ) {
		$global = new CentralAuthUser( $user->getName() );
		$user->setEmail( $global->getEmail() );
		return true;
	}


	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * If you don't automatically create accounts, you must still create
	 * accounts in some way. It's not possible to authenticate without
	 * a local account.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	function autoCreate() {
		return true;
	}

	/**
	 * Set the given password in the authentication database.
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @param $password String: password.
	 * @return bool
	 * @public
	 */
	function setPassword( $user, $password ) {
		// Fixme: password changes should happen through central interface.
		$global = new CentralAuthUser( $user->getName() );
		return $global->setPassword( $password );
	}

	/**
	 * Update user information in the external authentication database.
	 * Return true if successful.
	 *
	 * @param $user User object.
	 * @return bool
	 * @public
	 */
	function updateExternalDB( $user ) {
		return true;
	}

	/**
	 * Check to see if external accounts can be created.
	 * Return true if external accounts can be created.
	 * @return bool
	 * @public
	 */
	function canCreateAccounts() {
		// Require accounts to be created through the central login interface?
		return true;
	}

	/**
	 * Add a user to the external authentication database.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 * @public
	 */
	function addUser( $user, $password ) {
		$global = new CentralAuthUser( $user->getName() );
		return $global->register( $password );
	}


	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 * @public
	 */
	function strict() {
		return true;
	}

	/**
	 * When creating a user account, optionally fill in preferences and such.
	 * For instance, you might pull the email address or real name from the
	 * external user database.
	 *
	 * The User object is passed by reference so it can be modified; don't
	 * forget the & on your function declaration.
	 *
	 * @param $user User object.
	 * @public
	 */
	function initUser( &$user ) {
		global $wgDBname;
		$global = new CentralAuthUser( $user->getName() );
		$global->attach( $wgDBname, $user->getId() );
		
		$this->updateUser( $user );
	}
}

?>