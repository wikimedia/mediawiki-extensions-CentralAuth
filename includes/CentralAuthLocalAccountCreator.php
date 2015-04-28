<?php

class CentralAuthLocalAccountCreator {
	public function __construct( $name ) {
		$this->name = $name;
	}

	public function create() {
		global $wgAuth;
		$this->name = User::getCanonicalName( $this->name );
		if ( $this->name === false ) {
			throw new Exception( "{$this->name} is not a valid username" );
		}
		$user = User::newFromName( $this->name );
		if ( $user->getId() ) {
			throw new Exception( "{$this->name} already exists" );
		}

		$central = CentralAuthuser::getInstance( $user );
		if ( !$central->exists() ) {
			throw new Exception( "No global user for {$this->name}" );
		}

		$user->loadDefaults( $this->name );
		$status = $user->addToDatabase();
		if ( !$status->isOK() ) {
			throw new Exception( "User {$this->name} already exists" );
		}

		$wgAuth->initUser( $user, true );
		$wgAuth->updateUser( $user );

		# Notify hooks (e.g. Newuserlog)
		Hooks::run( 'AuthPluginAutoCreate', array( $user ) );

		# Update user count
		DeferredUpdates::addUpdate(
			SiteStatsUpdate::factory( array( 'users' => 1 ) )
		);
	}
}
