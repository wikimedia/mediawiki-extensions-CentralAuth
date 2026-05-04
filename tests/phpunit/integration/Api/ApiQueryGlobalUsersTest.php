<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Api;

use CentralAuthTestUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\MockWikiMapTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\TimestampFormat as TS;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Api\ApiQueryGlobalUsers
 * @group Database
 * @group API
 */
class ApiQueryGlobalUsersTest extends ApiTestCase {
	use MockAuthorityTrait;
	use MockWikiMapTrait;

	private const MODULE_NAME = 'globalusers';
	private const PASSWORD = 'GUP@ssword';

	/** @var CentralAuthUser[] */
	private static array $centralAuthUsers = [];

	public function addDBDataOnce(): void {
		$currentWikiId = WikiMap::getCurrentWikiId();
		$userInfo = [
			0 => [
				'username' => 'Steward',
				'password' => self::PASSWORD,
				'attrs' => [],
				'wikis' => [],
				'createLocal' => true,
				'callback' => function ( CentralAuthUser $centralAuthUser ) {
					$status = $centralAuthUser->addToGlobalGroup( 'steward' );
					$this->assertStatusGood( $status );
				},
			],
			1 => [
				'username' => 'Attached user',
				'password' => self::PASSWORD,
				'attrs' => [],
				'wikis' => [],
				'createLocal' => true,
				'callback' => static function ( CentralAuthUser $centralAuthUser ) use ( $currentWikiId ) {
					$centralAuthUser->attach( $currentWikiId );
				},
			],
			2 => [
				'username' => 'Unattached user',
				'password' => self::PASSWORD,
				'attrs' => [],
				'wikis' => [],
				'createLocal' => false,
			],
			3 => [
				'username' => 'Hidden user',
				'password' => self::PASSWORD,
				'attrs' => [
					'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_LISTS,
				],
				'wikis' => [],
				'createLocal' => true,
			],
			4 => [
				'username' => 'Suppressed user',
				'password' => self::PASSWORD,
				'attrs' => [
					'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
				],
				'wikis' => [],
				'createLocal' => true,
			],
			5 => [
				'username' => 'Locked user',
				'password' => self::PASSWORD,
				'attrs' => [],
				'wikis' => [],
				'createLocal' => true,
				'callback' => function ( CentralAuthUser $centralAuthUser ) {
					$context = RequestContext::getMain();
					$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

					$status = $centralAuthUser->adminLockHide( true, null, 'A very good lock reason', $context );

					RequestContext::resetMain();
					$this->assertStatusGood( $status );
				},
			],
			6 => [
				'username' => 'Global sysop (not opted in)',
				'password' => self::PASSWORD,
				'attrs' => [],
				'wikis' => [],
				'createLocal' => true,
				'callback' => function ( CentralAuthUser $centralAuthUser ) {
					$globalGroupManager = CentralAuthServices::getGlobalGroupManager( $this->getServiceContainer() );

					// Add the user to a global group that's not enabled on this wiki
					$status = $globalGroupManager->addRightsToGroup( 'global-sysop', [ 'dummy-right' ] );
					$this->assertStatusGood( $status );

					$status = $centralAuthUser->addToGlobalGroup( 'global-sysop' );
					$this->assertStatusGood( $status );

					$set = new WikiSet(
						'gswikis',
						WikiSet::OPTIN,
						[ 'someotherwiki' ],
						1
					);
					$set->saveToDB();

					$status = $globalGroupManager->setWikiSet( 'global-sysop', $set->getId() );
					$this->assertStatusGood( $status );
				},
			],
		];

		$dbw = $this->getCentralDB();
		$id = 0;
		/**
		 * @var list<array{cb:callable(CentralAuthUser),user:CentralAuthUser}>
		 */
		$callbacks = [];

		foreach ( $userInfo as $args ) {
			$callback = $args['callback'] ?? null;
			$params = array_diff_key( $args, [ 'callback' => true ] );

			$id++;
			if ( !isset( $params['attrs']['gu_id'] ) ) {
				$params['attrs']['gu_id'] = $id;
			}

			$user = new CentralAuthTestUser( ...$params );
			$user->save( $dbw );

			$centralAuthUser = $user->getCentralUser();
			self::$centralAuthUsers[] = $centralAuthUser;

			if ( is_callable( $callback ) ) {
				$callbacks[] = [
					'cb' => $callback,
					'user' => $centralAuthUser,
				];
			}
		}

		// Run callbacks after populating self::$centralAuthUsers;
		// otherwise, CI may emit noisy "Index not found" errors on failure
		foreach ( $callbacks as [ 'cb' => $cb, 'user' => $user ] ) {
			$cb( $user );
		}
	}

	private function getCentralDB(): IDatabase {
		return CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )->getCentralPrimaryDB();
	}

	/**
	 * @param int $index
	 * @return CentralAuthUser
	 * @see self::addDBDataOnce
	 */
	private static function getCentralAuthUser( int $index ): CentralAuthUser {
		if ( !isset( self::$centralAuthUsers[$index] ) ) {
			self::fail( "No CentralAuthUser found for index $index" );
		}
		return self::$centralAuthUsers[$index];
	}

	protected function setUp(): void {
		parent::setUp();

		$currentWikiId = WikiMap::getCurrentWikiId();
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthCentralWiki => $currentWikiId,
			MainConfigNames::LocalDatabases => [ $currentWikiId, 'someotherwiki' ],
		] );
		$this->mockWikiMap();
	}

	private function doQuery( array $params, ?Authority $performer = null ): array {
		$params = [
			'action' => 'query',
			'list' => self::MODULE_NAME,
		] + $params;

		[ $result ] = $this->doApiRequest( $params, null, false, $performer );

		$this->assertIsArray( $result['query'][self::MODULE_NAME] );

		return $result['query'][self::MODULE_NAME];
	}

	/**
	 * @param CentralAuthUser $user
	 * @return list<array{group:string,expiry:string}>
	 */
	private static function getGroupMemberships( CentralAuthUser $user ): array {
		$ret = [];
		foreach ( $user->getGlobalGroupsWithExpiration() as $group => $expiry ) {
			$ret[] = [
				'group' => $group,
				'expiry' => $expiry ?? 'infinity',
			];
		}
		return $ret;
	}

	public function testExecuteWithConflictingParams() {
		$this->expectApiErrorCode( 'invalidparammix' );
		$this->doQuery( [
			'gususers' => 'Someone',
			'guscentralids' => '9999',
		] );
	}

	public function testExecuteWithInvalidNames() {
		$data = $this->doQuery( [
			'gususers' => '{|}|1.2.3.4',
		] );
		$expected = [
			[ 'name' => '{', 'invalid' => true ],
			[ 'name' => '}', 'invalid' => true ],
			[ 'name' => '1.2.3.4', 'invalid' => true ],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithDuplicateNames() {
		$user = $this->getCentralAuthUser( 1 );
		$userName = $user->getName();

		$data = $this->doQuery( [
			'gususers' => implode( '|', [ $userName, lcfirst( $userName ) ] ),
		] );

		$expected = [
			[
				'centralid' => $user->getId(),
				'name' => $userName,
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithMissingNames() {
		$data = $this->doQuery( [
			'gususers' => 'Missing1|Missing2',
		] );
		$expected = [
			[ 'name' => 'Missing1', 'missing' => true ],
			[ 'name' => 'Missing2', 'missing' => true ],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	/**
	 * @dataProvider provideExecuteWithHiddenUsers
	 */
	public function testExecuteWithHiddenUsers( bool $canSuppress, bool $useNames ) {
		$visibleUser = $this->getCentralAuthUser( 2 );
		$hiddenUser = $this->getCentralAuthUser( 3 );
		$suppressedUser = $this->getCentralAuthUser( 4 );
		$performer = $this->mockRegisteredAuthorityWithPermissions(
			$canSuppress ? [ 'centralauth-suppress' ] : []
		);

		if ( $useNames ) {
			$params = [
				'gususers' => implode( '|', [
					$visibleUser->getName(),
					$hiddenUser->getName(),
					$suppressedUser->getName(),
				] ),
			];
		} else {
			$params = [
				'guscentralids' => implode( '|', [
					$visibleUser->getId(),
					$hiddenUser->getId(),
					$suppressedUser->getId(),
				] ),
			];
		}

		$data = $this->doQuery( $params, $performer );

		if ( $canSuppress ) {
			$expected = [
				[
					'centralid' => $visibleUser->getId(),
					'name' => $visibleUser->getName(),
				],
				[
					'centralid' => $hiddenUser->getId(),
					'name' => $hiddenUser->getName(),
					'hidden' => true,
				],
				[
					'centralid' => $suppressedUser->getId(),
					'name' => $suppressedUser->getName(),
					'suppressed' => true,
				],
			];
		} elseif ( $useNames ) {
			$expected = [
				[
					'centralid' => $visibleUser->getId(),
					'name' => $visibleUser->getName(),
				],
				[
					'name' => $hiddenUser->getName(),
					'missing' => true,
				],
				[
					'name' => $suppressedUser->getName(),
					'missing' => true,
				],
			];
		} else {
			$expected = [
				[
					'centralid' => $visibleUser->getId(),
					'name' => $visibleUser->getName(),
				],
				[
					'centralid' => $hiddenUser->getId(),
					'missing' => true,
				],
				[
					'centralid' => $suppressedUser->getId(),
					'missing' => true,
				],
			];
		}
		$this->assertArrayEquals( $expected, $data );
	}

	public static function provideExecuteWithHiddenUsers() {
		return [
			'Performer has centralauth-suppress, username-based' => [ true, true ],
			'Performer does not have centralauth-suppress, username-based' => [ false, true ],
			'Performer has centralauth-suppress, ID-based' => [ true, false ],
			'Performer does not have centralauth-suppress, ID-based' => [ false, false ],
		];
	}

	public function testExecuteWithMissingIds() {
		$data = $this->doQuery( [
			'guscentralids' => '2001|2002',
		] );
		$expected = [
			[ 'centralid' => '2001', 'missing' => true ],
			[ 'centralid' => '2002', 'missing' => true ],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithPropLocked() {
		$lockedUser = $this->getCentralAuthUser( 5 );
		$unlockedUser = $this->getCentralAuthUser( 1 );

		$data = $this->doQuery( [
			'gususers' => implode( '|', [
				$lockedUser->getName(),
				$unlockedUser->getName(),
			] ),
			'gusprop' => 'locked',
		] );

		$dbr = $this->getDb();
		$builder = $dbr->newSelectQueryBuilder();
		$logid = $dbr->newSelectQueryBuilder()
			->field( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'globalauth',
				'log_action' => 'setstatus',
				'log_namespace' => NS_USER,
				'log_title' => strtr( "{$lockedUser->getName()}@global", ' ', '_' ),
			] )
			->orderBy( 'log_id', $builder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $logid );

		$expected = [
			[
				'centralid' => $lockedUser->getId(),
				'name' => $lockedUser->getName(),
				'locked' => true,
				'locklogid' => (int)$logid,
			],
			[
				'centralid' => $unlockedUser->getId(),
				'name' => $unlockedUser->getName(),
				'locked' => false,
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithPropEditcount() {
		$attachedUser = $this->getCentralAuthUser( 1 );
		$unattachedUser = $this->getCentralAuthUser( 2 );

		$data = $this->doQuery( [
			'gususers' => implode( '|', [
				$attachedUser->getName(),
				$unattachedUser->getName(),
			] ),
			'gusprop' => 'editcount',
		] );

		$expected = [
			[
				'centralid' => $attachedUser->getId(),
				'name' => $attachedUser->getName(),
				'editcount' => $attachedUser->getGlobalEditCount(),
			],
			[
				'centralid' => $unattachedUser->getId(),
				'name' => $unattachedUser->getName(),
				'editcount' => $unattachedUser->getGlobalEditCount(),
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithPropRegistration() {
		$attachedUser = $this->getCentralAuthUser( 1 );
		$unattachedUser = $this->getCentralAuthUser( 2 );

		$data = $this->doQuery( [
			'guscentralids' => implode( '|', [
				$attachedUser->getId(),
				$unattachedUser->getId(),
			] ),
			'gusprop' => 'registration',
		] );

		$expected = [
			[
				'centralid' => $attachedUser->getId(),
				'name' => $attachedUser->getName(),
				'registration' => wfTimestamp( TS::ISO_8601, $attachedUser->getRegistration() ),
			],
			[
				'centralid' => $unattachedUser->getId(),
				'name' => $unattachedUser->getName(),
				'registration' => wfTimestamp( TS::ISO_8601, $unattachedUser->getRegistration() ),
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithPropLocalinfo() {
		$attachedUser = $this->getCentralAuthUser( 1 );
		$unattachedUser = $this->getCentralAuthUser( 2 );

		$data = $this->doQuery( [
			'guscentralids' => implode( '|', [
				$attachedUser->getId(),
				$unattachedUser->getId(),
			] ),
			'gusprop' => 'localinfo',
		] );

		$row = $this->getCentralDB()->newSelectQueryBuilder()
			->fields( [ 'lu_attached_timestamp', 'lu_local_id' ] )
			->from( 'localuser' )
			->where( [
				'lu_wiki' => WikiMap::getCurrentWikiId(),
				'lu_name' => $attachedUser->getName(),
			] )
			->caller( __METHOD__ )
			->fetchRow();
		$this->assertNotFalse( $row );

		$expected = [
			[
				'centralid' => $attachedUser->getId(),
				'name' => $attachedUser->getName(),
				'localinfo' => [
					'attached' => true,
					'localid' => (int)$row->lu_local_id,
					'timestamp' => wfTimestamp( TS::ISO_8601, $row->lu_attached_timestamp ),
				],
			],
			[
				'centralid' => $unattachedUser->getId(),
				'name' => $unattachedUser->getName(),
				'localinfo' => [
					'attached' => false,
				],
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

	public function testExecuteWithPropGroupsAndRights() {
		$steward = $this->getCentralAuthUser( 0 );
		$globalSysop = $this->getCentralAuthUser( 6 );

		$data = $this->doQuery( [
			'gususers' => implode( '|', [
				$steward->getName(),
				$globalSysop->getName(),
			] ),
			'gusprop' => 'groups|groupmemberships|rights',
			'guslocalgroups' => true,
		] );

		$expected = [
			[
				'centralid' => $steward->getId(),
				'name' => $steward->getName(),
				'groups' => $steward->getGlobalGroups(),
				'groupmemberships' => $this->getGroupMemberships( $steward ),
				'rights' => $steward->getGlobalRights(),
			],
			[
				'centralid' => $globalSysop->getId(),
				'name' => $globalSysop->getName(),
				'groups' => [],
				'groupmemberships' => [],
				'rights' => [],
			],
		];
		$this->assertArrayEquals( $expected, $data );
	}

}
