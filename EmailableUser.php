<?php

class EmailableUser extends User {

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Static factory method for creation from username.
	 *
	 * This is slightly less efficient than newFromId(), so use newFromId() if
	 * you have both an ID and a name handy.
	 *
	 * @param string $name Username, validated by Title::newFromText()
	 * @param string|Bool $validate Validate username. Takes the same parameters as
	 *    User::getCanonicalName(), except that true is accepted as an alias
	 *    for 'valid', for BC.
	 *
	 * @return ConfirmAndMigrateUser|bool ConfirmAndMigrateUser object, or false if the
	 *    username is invalid (e.g. if it contains illegal characters or is an IP address).
	 *    If the username is not present in the database, the result will be a user object
	 *    with a name, zero user ID and default settings.
	 */
	public static function newFromName( $name, $validate = 'valid' ) {
		if ( $validate === true ) {
			$validate = 'valid';
		}
		$name = self::getCanonicalName( $name, $validate );
		if ( $name === false ) {
			return false;
		} else {
			# Create unloaded user object
			$u = new ConfirmAndMigrateUser;
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
	 * @return Status object
	 */
	public function sendConfirmAndMigrateMail() {
		global $wgLang;
		$tokenLife = 28 * 24 * 60 * 60; // 28 days

		$expiration = null; // gets passed-by-ref and defined in next line.
		$token = $this->confirmationToken( $expiration );

		// we want this token to last a little bit longer since we are cold-emailing
		// users and we really want as many responses as possible
		$now = time();
		$expires = $now + $tokenLife;
		$expiration = wfTimestamp( TS_MW, $expires );
		$this->mEmailTokenExpires = $expiration;

		// create a "token url" for MergeAccount since we have added email
		// confirmation there
		$url = $this->getTokenUrl( 'MergeAccount', $token );
		$invalidateURL = $this->invalidationTokenUrl( $token );
		$this->saveSettings();

		return $this->sendMail(
			wfMessage( 'centralauth-finishglobaliseemail_subject' )->text(),
			wfMessage( "centralauth-finishglobaliseemail_body",
				$this->getRequest()->getIP(),
				$this->getName(),
				$url,
				$wgLang->timeanddate( $expiration, false ),
				$invalidateURL,
				$wgLang->date( $expiration, false ),
				$wgLang->time( $expiration, false ) )->text() );
	}
}