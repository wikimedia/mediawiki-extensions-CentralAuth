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

use Job;
use Title;

/**
 * A job to do crosswiki suppression in batches, rather than
 * in one request. Size of batch is changed by changing
 * $wgCentralAuthWikisPerSuppressJob.
 */
class CentralAuthSuppressUserJob extends Job {
	/**
	 * @param Title $title Associated title
	 * @param array $params Job parameters
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'crosswikiSuppressUser', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		$username = $this->params['username'];
		$by = $this->params['by'];
		$wikis = $this->params['wikis'];
		$suppress = $this->params['suppress'];
		$reason = $this->params['reason'];
		$user = CentralAuthUser::getPrimaryInstanceByName( $username );
		if ( !$user->exists() ) {
			wfDebugLog(
				'suppressjob', "Requested to suppress non-existent user {$username} by {$by}."
			);
		}

		foreach ( $wikis as $wiki ) {
			$user->doLocalSuppression( $suppress, $wiki, $by, $reason );
			wfDebugLog(
				'suppressjob',
				( $suppress ? 'S' : 'Uns' ) .
					"uppressed {$username} at {$wiki} by {$by} via job queue."
			);
		}
		return true;
	}
}
