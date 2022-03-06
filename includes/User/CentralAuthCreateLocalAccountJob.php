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

use Exception;
use Job;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Logger\LoggerFactory;
use RequestContext;
use Title;
use User;
use WikiMap;
use Wikimedia\ScopedCallback;

/**
 * Creates a local account and connects it to the global account.
 * Used to ensure that all users have an attached local account on certain wikis which have some
 * special "central" role (such as $wgMWOAuthCentralWiki for the OAuth extension).
 * @see $wgCentralAuthAutoCreateWikis
 */
class CentralAuthCreateLocalAccountJob extends Job {
	/**
	 * @param Title $title Not used
	 * @param array $params name => user name, from => wiki where the job is created,
	 *   [session] => session data from RequestContext::exportSession()
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'CentralAuthCreateLocalAccountJob', $title, $params );
	}

	/**
	 * Try to create and attach the user.
	 * @throws Exception
	 * @return bool Success
	 */
	public function run() {
		$username = $this->params['name'];
		$from = $this->params['from'];
		$wiki = WikiMap::getCurrentWikiId();

		if ( isset( $this->params['session'] ) ) {
			// restore IP and other request data
			$this->params['session']['userId'] = 0;
			$this->params['session']['sessionId'] = '';
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( static function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}

		$user = User::newFromName( $username );
		$centralUser = CentralAuthUser::getInstance( $user );
		$logger = LoggerFactory::getInstance( 'CentralAuth' );

		if ( $user->getId() !== 0 ) {
			$logger->info(
				__CLASS__ . ': tried to create local account for {username} '
					. 'on {wiki} from {from} but one already exists',
				[
					'username' => $username,
					'wiki' => $wiki,
					'from' => $from,
				]
			);
			return true;
		} elseif ( !$centralUser->exists() ) {
			$logger->info(
				__CLASS__ . ': tried to create local account for {username} '
					. 'on {wiki} from {from} but no global account exists',
				[
					'username' => $username,
					'wiki' => $wiki,
					'from' => $from,
				]
			);
			return true;
		} elseif ( $centralUser->attachedOn( $wiki ) ) {
			$logger->info(
				__CLASS__ . ': tried to create local account for {username} '
					. 'on {wiki} from {from} but an attached local account already exists',
				[
					'username' => $username,
					'wiki' => $wiki,
					'from' => $from,
				]
			);
			return true;
		}

		$success = CentralAuthServices::getUtilityService()->autoCreateUser( $user )->isGood();
		if ( $success ) {
			$centralUser->invalidateCache();
		}

		return true;
	}
}
