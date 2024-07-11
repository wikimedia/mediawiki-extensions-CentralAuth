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
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;
use Profiler;
use Psr\Log\LoggerInterface;
use RuntimeException;
use StatusValue;
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

	/** @var JobFactory */
	private $jobFactory;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		Config $config,
		AuthManager $authManager,
		TitleFactory $titleFactory,
		JobQueueGroupFactory $jobQueueGroupFactory,
		JobFactory $jobFactory,
		LoggerInterface $logger
	) {
		$this->config = $config;
		$this->authManager = $authManager;
		$this->titleFactory = $titleFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->jobFactory = $jobFactory;
		$this->logger = $logger;
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
				$watchPoint = $store->watchErrors();
				$value = $store->get( $key );
				$error = $store->getLastError( $watchPoint );
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

		if ( $result === WaitConditionLoop::CONDITION_REACHED ) {
			$this->logger->info( "Expected key {key} found.", [ 'key' => $key ] );
		} elseif ( $result === WaitConditionLoop::CONDITION_TIMED_OUT ) {
			$this->logger->error( "Expected key {key} not found due to timeout.", [ 'key' => $key ] );
		} else {
			$this->logger->error( "Expected key {key} not found due to I/O error.", [ 'key' => $key ] );
		}

		return $value;
	}

	/**
	 * Store a value for a short time via the shared token store, and return the random key it's
	 * stored under. This can be used to replace an URL parameter (used to pass information between
	 * wikis via redirect chains) with a random placeholder, to avoid sniffing or tampering.
	 * @param string $value The value to store.
	 * @param string $keyPrefix Namespace in the token store.
	 * @param CentralAuthSessionManager $sessionManager
	 * @return string The random key (without the prefix).
	 */
	public function tokenize(
		string $value,
		string $keyPrefix,
		CentralAuthSessionManager $sessionManager
	): string {
		$tokenStore = $sessionManager->getTokenStore();
		$token = MWCryptRand::generateHex( 16 );
		$key = $sessionManager->makeTokenKey( $keyPrefix, $token );

		$tokenStore->set( $key, $value, $tokenStore::TTL_MINUTE );
		return $token;
	}

	/**
	 * Recover the value concealed with tokenize().
	 * @param string $token The random key returned by tokenize().
	 * @param string $keyPrefix Namespace in the token store.
	 * @param CentralAuthSessionManager $sessionManager
	 * @return string|false The value, or false if it was not found.
	 */
	public function detokenize(
		string $token,
		string $keyPrefix,
		CentralAuthSessionManager $sessionManager
	) {
		$key = $sessionManager->makeTokenKey( $keyPrefix, $token );

		return $this->getKeyValueUponExistence( $sessionManager->getTokenStore(), $key );
	}

	/**
	 * Auto-create an account
	 *
	 * @param User $user User to auto-create
	 * @param bool $log Whether to generate a user creation log entry
	 * @param Authority|null $performer The user performing the creation
	 * @return StatusValue a status value
	 */
	public function autoCreateUser( User $user, $log = true,
		?Authority $performer = null
	): StatusValue {
		// Ignore warnings about primary database connections/writes...hard to avoid here

		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$source = CentralAuthPrimaryAuthenticationProvider::ID;
		if ( !$this->authManager->getAuthenticationProvider( $source ) ) {
			$source = AuthManager::AUTOCREATE_SOURCE_SESSION;
		}
		$sv = $this->authManager->autoCreateUser( $user, $source, false, $log, $performer );

		LoggerFactory::getInstance( 'authevents' )->info( 'Central autocreation attempt', [
			'event' => 'autocreate',
			'successful' => $sv->isGood(),
			'status' => ( $sv->getErrorsArray() ?: $sv->getWarningsArray() )[0][0] ?? '-',
			'extension' => 'CentralAuth',
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
			throw new RuntimeException( "Failed to create title for user page of $name" );
		}

		foreach ( $this->config->get( 'CentralAuthAutoCreateWikis' ) as $wiki ) {
			if ( $wiki === $thisWiki ) {
				continue;
			}

			$job = $this->jobFactory->newJob(
				'CentralAuthCreateLocalAccountJob',
				[ 'name' => $name, 'from' => $thisWiki, 'session' => $session ]
			);
			$this->jobQueueGroupFactory->makeJobQueueGroup( $wiki )->lazyPush( $job );
		}
	}
}
