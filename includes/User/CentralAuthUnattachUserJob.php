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

namespace MediaWiki\Extension\CentralAuth\User;

use CentralAuthUser;
use Job;
use Title;
use User;

/**
 * A job to unattach a user.
 *
 * @copyright © 2016 Wikimedia Foundation and contributors.
 */
class CentralAuthUnattachUserJob extends Job {

	/**
	 * @param Title $title Associated title
	 * @param array $params Job parameters
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'CentralAuthUnattachUserJob', $title, $params );
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
