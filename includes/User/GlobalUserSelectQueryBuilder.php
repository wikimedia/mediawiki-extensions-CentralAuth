<?php

namespace MediaWiki\Extension\CentralAuth\User;

use Iterator;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @stable
 */
class GlobalUserSelectQueryBuilder extends SelectQueryBuilder {

	/** @var ActorStore for the local wiki */
	private $actorStore;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var bool */
	private $initRan = false;

	/**
	 * @param IDatabase $db
	 * @param ActorStore $actorStore
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		IDatabase $db,
		ActorStore $actorStore,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( $db );
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * Init the SelectQueryBuilder
	 *
	 * Should be called by ::fetch* methods.
	 */
	private function init(): void {
		if ( $this->initRan ) {
			return;
		}

		// HACK: SelectQueryBuilder::queryInfo expects join conditions to be at the join_conds
		// key, but CentralAuthUser::selectQueryInfo exposes them as joinConds
		$queryInfo = CentralAuthUser::selectQueryInfo();
		$queryInfo['join_conds'] = $queryInfo['joinConds'];
		$this->queryInfo( $queryInfo );

		$this->initRan = true;
	}

	/**
	 * Find by provided global user IDs
	 *
	 * @param int|int[] $globalUserIds
	 * @return $this
	 */
	public function whereGlobalUserIds( $globalUserIds ): self {
		Assert::parameterType( 'integer|array', $globalUserIds, '$globalUserIds' );

		$this->conds( [ 'gu_id' => $globalUserIds ] );
		return $this;
	}

	/**
	 * Find by provided usernames
	 *
	 * @param string|string[] $userNames
	 * @return $this
	 */
	public function whereUserNames( $userNames ): self {
		Assert::parameterType( 'string|array', $userNames, '$userIds' );

		$userNames = array_map( function ( $name ) {
			return $this->userNameUtils->getCanonical( (string)$name );
		}, (array)$userNames );

		$this->conds( [ 'gu_name' => $userNames ] );
		return $this;
	}

	/**
	 * @param bool $isLocked
	 * @return $this
	 */
	public function whereLocked( bool $isLocked ): self {
		$this->conds( [ 'gu_locked' => $isLocked ] );
		return $this;
	}

	/**
	 * Fetch CentralAuthUsers for the specified query
	 *
	 * @return Iterator<CentralAuthUser>
	 */
	public function fetchCentralAuthUsers(): Iterator {
		$this->init();

		return call_user_func( function () {
			$result = $this->fetchResultSet();
			foreach ( $result as $row ) {
				yield CentralAuthUser::newFromRow( $row, [] );
			}
			$result->free();
		} );
	}

	/**
	 * Fetch UserIdentities for the current wiki
	 *
	 * @return Iterator<UserIdentity>
	 */
	public function fetchLocalUserIdentitites(): Iterator {
		$this->init();
		$this->field( 'lu_local_id' );

		$result = $this->fetchResultSet();
		$localUserIds = [];
		foreach ( $result as $row ) {
			$localUserIds[] = $row->lu_local_id;
		}

		return $this->actorStore->newSelectQueryBuilder()
			->whereUserIds( $localUserIds )
			->fetchUserIdentities();
	}
}
