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

namespace MediaWiki\Extension\CentralAuth;

use BagOStuff;
use Config;
use Exception;
use Job;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logger\LoggerFactory;
use Profiler;
use RequestContext;
use StatusValue;
use TitleFactory;
use User;
use WebRequest;
use WikiMap;
use Wikimedia\WaitConditionLoop;

/**
 * Utility services that are useful in many parts of CentralAuth.
 *
 * @since 1.36
 */
class CentralAuthUtilityService {

	/** @var Config */
	private $config;

	/** @var AuthManager */
	private $authManager;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	public function __construct(
		Config $config,
		AuthManager $authManager,
		TitleFactory $titleFactory,
		JobQueueGroupFactory $jobQueueGroupFactory
	) {
		$this->config = $config;
		$this->authManager = $authManager;
		$this->titleFactory = $titleFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	/**
	 * Sets the Platform for Privacy Preferences Project (P3P) policy header,
	 * if one is configured and the browser requests one.
	 *
	 * @param WebRequest|null $request
	 */
	public function setP3P( WebRequest $request = null ): void {
		if ( !$request ) {
			$request = RequestContext::getMain()->getRequest();
		}

		$response = $request->response();

		$sent = is_callable( [ $response, 'headersSent' ] )
			? $response->headersSent()
			: headers_sent();

		if ( !$sent && $response->getHeader( 'P3P' ) === null ) {
			// IE requires that a P3P header be provided for the cookies to be
			// visible to the auto-login check.
			$policy = $this->config->get( 'CentralAuthCookiesP3P' );

			if ( $policy === true ) {
				// Note this policy is not valid: it has no valid tokens, while
				// a valid policy would contain an "access" token and at least
				// one statement, which would contain either the NID token or
				// at least one "purpose" token, one "recipient" token, and one
				// "retention" token.
				$url = $this->titleFactory
					->makeTitle( NS_SPECIAL, 'CentralAutoLogin/P3P' )
					->getCanonicalURL();
				$response->header( "P3P: CP=\"This is not a P3P policy! See $url for more info.\"" );
			} elseif ( $policy ) {
				$response->header( "P3P: $policy" );
			}
		}
	}

	/**
	 * Wait for and return the value of a key which is expected to exist from a store
	 *
	 * @param BagOStuff $store
	 * @param string $key A key that will only have one value while it exists
	 * @param int $timeout
	 * @return mixed Key value; false if not found or on error
	 */
	public function getKeyValueUponExistence( BagOStuff $store, $key, $timeout = 3 ) {
		$value = false;

		$result = ( new WaitConditionLoop(
			static function () use ( $store, $key, &$value ) {
				$store->clearLastError();
				$value = $store->get( $key );
				$error = $store->getLastError();
				if ( $value !== false ) {
					return WaitConditionLoop::CONDITION_REACHED;
				} elseif ( $error === $store::ERR_NONE ) {
					return WaitConditionLoop::CONDITION_CONTINUE;
				} else {
					return WaitConditionLoop::CONDITION_ABORTED;
				}
			},
			$timeout
		) )->invoke();

		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		if ( $result === WaitConditionLoop::CONDITION_REACHED ) {
			$logger->info( "Expected key {key} found.", [ 'key' => $key ] );
		} elseif ( $result === WaitConditionLoop::CONDITION_TIMED_OUT ) {
			$logger->error( "Expected key {key} not found due to timeout.", [ 'key' => $key ] );
		} else {
			$logger->error( "Expected key {key} not found due to I/O error.", [ 'key' => $key ] );
		}

		return $value;
	}

	/**
	 * Auto-create an account
	 *
	 * @param User $user User to auto-create
	 * @param bool $log Whether to generate a user creation log entry
	 * @return StatusValue a status value
	 */
	public function autoCreateUser( User $user, $log = true ): StatusValue {
		// Ignore warnings about primary database connections/writes...hard to avoid here

		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$source = CentralAuthPrimaryAuthenticationProvider::class;
		if ( !$this->authManager->getAuthenticationProvider( $source ) ) {
			$source = AuthManager::AUTOCREATE_SOURCE_SESSION;
		}
		$sv = $this->authManager->autoCreateUser( $user, $source, false, $log );

		LoggerFactory::getInstance( 'authevents' )->info( 'Autocreation attempt', [
			'event' => 'autocreate',
			'status' => strval( $sv ),
		] );
		return $sv;
	}

	/**
	 * Sets up jobs to create and attach a local account for the given user on every wiki listed in
	 * $wgCentralAuthAutoCreateWikis.
	 * @param CentralAuthUser $centralUser
	 */
	public function scheduleCreationJobs( CentralAuthUser $centralUser ) {
		$name = $centralUser->getName();
		$thisWiki = WikiMap::getCurrentWikiId();
		$session = RequestContext::getMain()->exportSession();

		$title = $this->titleFactory->makeTitleSafe( NS_USER, $name );

		if ( !$title ) {
			throw new Exception( "Failed to create title for user page of $name" );
		}

		foreach ( $this->config->get( 'CentralAuthAutoCreateWikis' ) as $wiki ) {
			if ( $wiki === $thisWiki ) {
				continue;
			}

			$job = Job::factory(
				'CentralAuthCreateLocalAccountJob',
				$title,
				[ 'name' => $name, 'from' => $thisWiki, 'session' => $session ]
			);
			$this->jobQueueGroupFactory->makeJobQueueGroup( $wiki )->lazyPush( $job );
		}
	}
}
