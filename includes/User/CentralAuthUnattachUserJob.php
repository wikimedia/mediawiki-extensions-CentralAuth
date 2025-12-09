<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\JobQueue\Job;
use MediaWiki\User\User;

/**
 * A job to unattach a user.
 *
 * @copyright © 2016 Wikimedia Foundation and contributors.
 */
class CentralAuthUnattachUserJob extends Job {

	/**
	 * @param array $params Job parameters
	 */
	public function __construct( $params ) {
		parent::__construct( 'CentralAuthUnattachUserJob', $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		$username = $this->params['username'];
		$wiki = $this->params['wiki'];
		$user = User::newFromName( $username );
		if ( $user->getId() !== 0 ) {
			// User has been created since this job was queued.
			// Races are fun!
			return true;
		}
		$causer = CentralAuthUser::getPrimaryInstanceByName( $username );
		$causer->removeLocalName( $wiki );
		if ( $causer->exists() ) {
			$causer->adminUnattach( [ $wiki ] );
		}
		return true;
	}
}
