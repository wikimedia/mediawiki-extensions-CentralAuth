<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Config\Config;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\TimestampFormat as TS;

/**
 * Query module to get information about a list of global users
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryGlobalUsers extends ApiQueryBase {

	private string $mCurrentWikiId;
	private IReadableDatabase $mCentralDB;
	/**
	 * @var array<int,array{group:string,expiry:string}[]> array<centralid, group memberships>
	 */
	private array $mGroups = [];
	/**
	 * @var array<int|string,string[]> array<group, rights>
	 */
	private array $mRights = [];
	/**
	 * @var array<int|string,int> array<username, logid>
	 */
	private array $mLockLogids = [];

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly Config $config,
		private readonly UserNameUtils $userNameUtils,
		private readonly CentralAuthDatabaseManager $databaseManager
	) {
		parent::__construct( $query, $moduleName, 'gus' );

		$this->mCurrentWikiId = Wikimap::getCurrentWikiId();
	}

	/**
	 * Get a connection to a CentralAuth database replica.
	 *
	 * @see ApiQueryBase::getDB
	 * @return IReadableDatabase
	 */
	protected function getDB() {
		$this->mCentralDB ??= $this->databaseManager->getCentralReplicaDB();
		return $this->mCentralDB;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$this->requireOnlyOneParameter( $params, 'centralids', 'users' );

		$props = array_fill_keys( $params['prop'] ?? [], true );
		$useNames = isset( $params['users'] );

		// Pre-process gususers: canonicalize all usernames, removing duplicates
		/** @var array<string|int,true> */
		$seenNames = [];
		/**
		 * Canonicalized input usernames, including invalid usernames but excluding duplicates
		 * @var string[]
		 */
		$users = [];
		/** @var array<string|int,array> */
		$data = [];
		foreach ( ( $params['users'] ?? [] ) as $name ) {
			$canonical = $this->userNameUtils->getCanonical( $name );
			if ( !$canonical ) {
				$data[$name] = [ 'name' => $name, 'invalid' => true ];
				$users[] = $name;
				continue;
			}
			// The same $canonical can appear multiple times when e.g. gususers=Foo|foo
			if ( !isset( $seenNames[$canonical] ) ) {
				$seenNames[$canonical] = true;
				$users[] = $canonical;
			}
		}
		// MediaWiki allows numeric usernames, and PHP converts such keys to integers
		$goodNames = self::getStringifiedKeys( $seenNames );

		if ( $useNames && $goodNames === [] ) {
			// gususers values are all invalid
			$this->buildResultFromData( $data, 'users', $users );
			return;
		}

		/** @var int[] */
		$centralIds = $params['centralids'] ?? [];

		$this->addTables( 'globaluser' );
		$this->addFields( [ 'gu_id', 'gu_name', 'gu_hidden_level' ] );

		if ( $useNames ) {
			$this->addWhere( [ 'gu_name' => $goodNames ] );
		} else {
			$this->addWhere( [ 'gu_id' => $centralIds ] );
		}

		// Filter out rows the client isn't allowed to see, and pretend that the corresponding users
		// don't exist. The logic below will mark such entries as `missing: true`.
		$canSuppress = $this->getAuthority()->isAllowed( 'centralauth-suppress' );
		$this->addWhereIf( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE ], !$canSuppress );

		$needsLockInfo = isset( $props['locked'] );
		$this->addFieldsIf( 'gu_locked', $needsLockInfo );

		$needsEditCount = isset( $props['editcount'] );
		if ( $needsEditCount ) {
			$this->addTables( 'global_edit_count' );
			$this->addFields( 'gec_count' );
			$this->addJoinConds( [
				'global_edit_count' => [
					'LEFT JOIN',
					'gec_user=gu_id'
				]
			] );
		}

		$this->addFieldsIf( 'gu_registration', isset( $props['registration'] ) );

		$needsLocalInfo = isset( $props['localinfo'] );
		if ( $needsLocalInfo ) {
			$this->addTables( 'localuser' );
			$this->addFields( [ 'lu_wiki', 'lu_attached_timestamp', 'lu_local_id' ] );
			$this->addJoinConds( [
				'localuser' => [
					'LEFT JOIN',
					[
						'lu_wiki' => $this->mCurrentWikiId,
						'lu_name=gu_name',
					]
				]
			] );
		}

		$rows = $this->select( __METHOD__ );
		$this->resetQueryParams();

		// Collect and process known central IDs
		/** @var array<int,string> */
		$idMap = [];
		/** @var array<string,string> */
		$lockedUserTitleMap = [];

		foreach ( $rows as $row ) {
			$idMap[$row->gu_id] = $row->gu_name;

			if ( $needsLockInfo && $row->gu_locked ) {
				$dbKey = strtr( "{$row->gu_name}@global", ' ', '_' );
				$lockedUserTitleMap[$dbKey] = $row->gu_name;
			}
		}

		$needsRights = isset( $props['rights'] );
		$needsGroupInfo = isset( $props['groups'] ) || isset( $props['groupmemberships'] ) || $needsRights;
		if ( $needsGroupInfo && $idMap !== [] ) {
			$this->loadGroups( array_keys( $idMap ), isset( $params['localgroups'] ), $needsRights );
		}

		if ( $lockedUserTitleMap !== [] ) {
			$this->loadLockLogids( $lockedUserTitleMap );
		}

		foreach ( $rows as $row ) {
			$entry = [
				'centralid' => (int)$row->gu_id,
				'name' => $row->gu_name,
			];

			$hiddenLevel = (int)$row->gu_hidden_level;
			if ( $hiddenLevel & CentralAuthUser::HIDDEN_LEVEL_LISTS ) {
				$entry['hidden'] = true;
			}
			if ( $hiddenLevel & CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED ) {
				$entry['suppressed'] = true;
			}

			if ( $needsLockInfo ) {
				$entry['locked'] = (bool)$row->gu_locked;

				if ( isset( $this->mLockLogids[$row->gu_name] ) ) {
					$entry['locklogid'] = $this->mLockLogids[$row->gu_name];
				}
			}

			if ( $needsEditCount ) {
				// gec_count may not always be initialized
				$entry['editcount'] = (int)( $row->gec_count ?? 0 );
			}

			if ( isset( $row->gu_registration ) ) {
				$entry['registration'] = wfTimestamp( TS::ISO_8601, $row->gu_registration );
			}

			if ( $needsLocalInfo ) {
				$vals = [ 'attached' => $row->lu_wiki !== null ];
				if ( $vals['attached'] ) {
					$vals['localid'] = $row->lu_local_id ? (int)$row->lu_local_id : null;
					$vals['timestamp'] = $row->lu_attached_timestamp
						? wfTimestamp( TS::ISO_8601, $row->lu_attached_timestamp )
						: null;
				}
				$entry['localinfo'] = $vals;
			}

			if ( $needsGroupInfo ) {
				$groups = array_column( $this->mGroups[$row->gu_id] ?? [], 'group' );
				if ( isset( $props['groups'] ) ) {
					$entry['groups'] = $groups;
					ApiResult::setArrayType( $entry['groups'], 'array' );
					ApiResult::setIndexedTagName( $entry['groups'], 'group' );
				}

				if ( isset( $props['groupmemberships'] ) ) {
					$entry['groupmemberships'] = $this->mGroups[$row->gu_id] ?? [];
					ApiResult::setArrayType( $entry['groupmemberships'], 'array' );
					ApiResult::setIndexedTagName( $entry['groupmemberships'], 'groupmembership' );
				}

				if ( $needsRights ) {
					$groupRights = array_values(
						array_intersect_key( $this->mRights, array_flip( $groups ) )
					);
					$entry['rights'] = $groupRights !== []
						? array_values( array_unique( array_merge( ...$groupRights ) ) )
						: [];
					ApiResult::setArrayType( $entry['rights'], 'array' );
					ApiResult::setIndexedTagName( $entry['rights'], 'right' );
				}
			}

			$key = $useNames ? $row->gu_name : $row->gu_id;
			$data[$key] = $entry;
		}

		if ( $useNames ) {
			/** @var array<int|string,int> */
			$nameMap = array_flip( $idMap );
			foreach ( $goodNames as $name ) {
				if ( isset( $nameMap[$name] ) ) {
					continue;
				}
				$data[$name] = [ 'name' => $name, 'missing' => true ];
			}
		} else {
			foreach ( $centralIds as $id ) {
				if ( isset( $idMap[$id] ) ) {
					continue;
				}
				$data[$id] = [ 'centralid' => $id, 'missing' => true ];
			}
		}

		// Sort the $data array in the order of gususers or guscentralids
		if ( $useNames ) {
			$paramName = 'users';
			$dataSource = $users;
			$sortBy = 'name';
		} else {
			$paramName = 'centralids';
			$dataSource = $centralIds;
			$sortBy = 'centralid';
		}
		/** @var array<int|string,int> $indexMap */
		$indexMap = array_flip( $dataSource );
		usort( $data, static function ( $a, $b ) use ( $sortBy, $indexMap ) {
			return $indexMap[$a[$sortBy]] - $indexMap[$b[$sortBy]];
		} );

		$this->buildResultFromData( $data, $paramName, $dataSource );
	}

	/**
	 * Returns the array keys cast to strings.
	 *
	 * This is useful when extracting usernames from array keys, since MediaWiki
	 * allows numeric usernames but PHP converts numeric string keys to integers.
	 *
	 * @return string[]
	 */
	private static function getStringifiedKeys( array $arr ): array {
		return array_map( static fn ( $key ) => (string)$key, array_keys( $arr ) );
	}

	/**
	 * @param array<string|int,array> $data
	 * @param string $paramName 'users' or 'centralids'
	 * @param string[]|int[] $dataSource The input gususers or guscentralids
	 */
	private function buildResultFromData( array $data, string $paramName, array $dataSource ): void {
		$result = $this->getResult();
		$path = [ 'query', $this->getModuleName() ];
		$done = [];
		$useNames = $paramName === 'users';
		foreach ( $data as $key => $value ) {
			$fit = $result->addValue( $path, null, $value );
			if ( !$fit ) {
				$this->setContinueEnumParameter(
					$paramName,
					implode( '|', array_diff( $dataSource, $done ) )
				);
				break;
			}
			$done[] = $useNames ? (string)$key : (int)$key;
		}
		$result->addIndexedTagName( $path, 'globaluser' );
	}

	/**
	 * @param int[] $centralIds
	 * @param bool $activeOnly
	 * @param bool $needsRights
	 */
	private function loadGroups( array $centralIds, bool $activeOnly, bool $needsRights ): void {
		$dbr = $this->getDB();

		$resGroups = $dbr->newSelectQueryBuilder()
			->fields( [ 'gug_user', 'gug_group', 'gug_expiry', 'ggr_set' ] )
			->from( 'global_user_groups' )
			->leftJoin( 'global_group_restrictions', null, 'ggr_group=gug_group' )
			->where( [
				'gug_user' => $centralIds,
				$dbr->expr( 'gug_expiry', '=', null )->or( 'gug_expiry', '>=', $dbr->timestamp() ),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$sets = [];
		$seenGroups = [];
		foreach ( $resGroups as $row ) {
			$setId = (int)$row->ggr_set;
			if ( $activeOnly && $setId ) {
				$sets[$setId] ??= WikiSet::newFromID( $setId );
				if ( !$sets[$setId]->inSet() ) {
					continue;
				}
			}

			$this->mGroups[$row->gug_user] ??= [];
			$this->mGroups[$row->gug_user][] = [
				'group' => $row->gug_group,
				'expiry' => $row->gug_expiry === null ? 'infinity' : wfTimestamp( TS::ISO_8601, $row->gug_expiry )
			];
			$seenGroups[$row->gug_group] = true;
		}

		if ( !$needsRights || $seenGroups === [] ) {
			return;
		}

		$resRights = $dbr->newSelectQueryBuilder()
			->fields( [ 'ggp_group', 'ggp_permission' ] )
			->from( 'global_group_permissions' )
			->where( [ 'ggp_group' => self::getStringifiedKeys( $seenGroups ) ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $resRights as $row ) {
			$this->mRights[$row->ggp_group] ??= [];
			$this->mRights[$row->ggp_group][] = $row->ggp_permission;
		}
	}

	/**
	 * @param array<string,string> $lockedUserTitleMap <log_title, username>
	 */
	private function loadLockLogids( array $lockedUserTitleMap ): void {
		// Allow clients to retrieve lock details via list=logevents&leids=... in bulk,
		// by returning the central wiki's log IDs for global locks.
		//
		// TODO: We shouldn't have to query the logging table for this. Consider storing
		// lock information in a dedicated table if global locks are never replaced with
		// global blocks (see T373388)
		// TODO: This may not work if the users were renamed after being locked.

		$centralWiki = $this->config->get( CAMainConfigNames::CentralAuthCentralWiki );
		$dbr = $this->databaseManager->getLocalDB( DB_REPLICA, $centralWiki ?? $this->mCurrentWikiId );
		$wikiId = $centralWiki === $this->mCurrentWikiId
			? WikiAwareEntity::LOCAL
			: ( $centralWiki ?? WikiAwareEntity::LOCAL );

		// Reuse DatabaseLogEntry's existing parsing/loading logic because old log
		// entries may use legacy log_params formats like "locked\n(none)"
		$builder = DatabaseLogEntry::newSelectQueryBuilder( $dbr );
		$result = $builder
			->where( [
				'log_type' => 'globalauth',
				'log_action' => 'setstatus',
				'log_namespace' => NS_USER,
				'log_title' => array_keys( $lockedUserTitleMap ),
			] )
			->orderBy( 'log_id', $builder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $result as $row ) {
			$targetUser = $lockedUserTitleMap[$row->log_title];
			if ( isset( $this->mLockLogids[$targetUser] ) ) {
				continue;
			}

			$params = DatabaseLogEntry::newFromRow( $row, $wikiId )->getParameters();
			$isLockLog =
				in_array( 'locked', $params['added'] ?? [], true ) ||
				// For old logs
				( $params[0] ?? null ) === 'locked';
			if ( !$isLockLog ) {
				continue;
			}

			$this->mLockLogids[$targetUser] = (int)$row->log_id;
		}
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'locked',
					'editcount',
					'registration',
					'localinfo',
					'groups',
					'groupmemberships',
					'rights',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'users' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'centralids' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'localgroups' => [
				ParamValidator::PARAM_TYPE => 'boolean'
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=globalusers&gususers=Example'
				=> 'apihelp-query+globalusers-example-1',
			'action=query&list=globalusers&guscentralids=1'
				=> 'apihelp-query+globalusers-example-2',
		];
	}

}
