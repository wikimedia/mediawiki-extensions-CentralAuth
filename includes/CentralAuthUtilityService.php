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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Profiler;
use RuntimeException;
use StatusValue;

/**
 * Utility services that are useful in many parts of CentralAuth.
 *
 * @since 1.36
 */
class CentralAuthUtilityService {

	private Config $config;
	private AuthManager $authManager;
	private TitleFactory $titleFactory;
	private JobQueueGroupFactory $jobQueueGroupFactory;
	private JobFactory $jobFactory;

	public function __construct(
		Config $config,
		AuthManager $authManager,
		TitleFactory $titleFactory,
		JobQueueGroupFactory $jobQueueGroupFactory,
		JobFactory $jobFactory
	) {
		$this->config = $config;
		$this->authManager = $authManager;
		$this->titleFactory = $titleFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->jobFactory = $jobFactory;
	}

	/**
	 * Auto-create an account
	 *
	 * @param User $user User to auto-create
	 * @param bool $log Whether to generate a user creation log entry
	 * @param Authority|null $performer The user performing the creation.
	 * 		NOTE: For callers passing the performer as NULL, the auto-created
	 *		user will be used as the performer.
	 * @return StatusValue a status value
	 */
	public function autoCreateUser( User $user, $log = true,
		?Authority $performer = null
	): StatusValue {
		$performer ??= $user;

		// Ignore warnings about primary database connections/writes...hard to avoid here
		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$source = CentralAuthPrimaryAuthenticationProvider::ID;
		if ( !$this->authManager->getAuthenticationProvider( $source ) ) {
			$source = AuthManager::AUTOCREATE_SOURCE_SESSION;
		}
		return $this->authManager->autoCreateUser(
			$user, $source, false, $log, $performer
		);
	}

	/**
	 * Sets up jobs to create and attach a local account for the given user on every wiki listed in
	 * $wgCentralAuthAutoCreateWikis.
	 */
	public function scheduleCreationJobs( CentralAuthUser $centralUser ) {
		$name = $centralUser->getName();
		$thisWiki = WikiMap::getCurrentWikiId();
		$session = RequestContext::getMain()->exportSession();

		$title = $this->titleFactory->makeTitleSafe( NS_USER, $name );

		if ( !$title ) {
			throw new RuntimeException( "Failed to create title for user page of $name" );
		}

		foreach ( $this->config->get( CAMainConfigNames::CentralAuthAutoCreateWikis ) as $wiki ) {
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
