<?php

namespace MediaWiki\Extension\CentralAuth\User;

use EmptyIterator;
use Iterator;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @stable
 */
class GlobalUserSelectQueryBuilder extends SelectQueryBuilder {

	/** @var ActorStore for the local wiki */
	private ActorStore $actorStore;

	private UserNameUtils $userNameUtils;
	private TempUserConfig $tempUserConfig;

	private bool $initRan = false;

	/**
	 * @param IReadableDatabase $db
	 * @param ActorStore $actorStore
	 * @param UserNameUtils $userNameUtils
	 * @param TempUserConfig $tempUserConfig
	 */
	public function __construct(
		IReadableDatabase $db,
		ActorStore $actorStore,
		UserNameUtils $userNameUtils,
		TempUserConfig $tempUserConfig
	) {
		parent::__construct( $db );
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
		$this->tempUserConfig = $tempUserConfig;
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

		$this->queryInfo( CentralAuthUser::selectQueryInfo() );

		$this->initRan = true;
	}

	/**
	 * Find by provided global user IDs
	 *
	 * @param int|int[] $globalUserIds
	 * @return $this
	 */
	public function whereGlobalUserIds( $globalUserIds ): self {
		Assert::parameterType( [ 'integer', 'array' ], $globalUserIds, '$globalUserIds' );

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
		Assert::parameterType( [ 'string', 'array' ], $userNames, '$userIds' );

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
	 * Return users registered before/after $timestamp
	 *
	 * @param string $timestamp
	 * @param bool $before Direction flag (if true, user_registration must be before $timestamp)
	 * @return self
	 */
	public function whereRegisteredTimestamp( string $timestamp, bool $before ): self {
		$this->conds(
			$this->db->expr( 'gu_registration', $before ? '<' : '>',
				$this->db->timestamp( $timestamp ) )
		);
		return $this;
	}

	/**
	 * Only return named accounts
	 *
	 * @return $this
	 */
	public function named(): self {
		if ( !$this->tempUserConfig->isKnown() ) {
			// nothing to do: getMatchCondition throws if temp accounts aren't known
			return $this;
		}
		$this->conds( $this->tempUserConfig->getMatchCondition( $this->db, 'gu_name', IExpression::NOT_LIKE ) );
		return $this;
	}

	/**
	 * Only return temporary accounts
	 *
	 * @return $this
	 */
	public function temp(): self {
		if ( !$this->tempUserConfig->isKnown() ) {
			$this->conds( '1=0' );
			return $this;
		}
		$this->conds( $this->tempUserConfig->getMatchCondition( $this->db, 'gu_name', IExpression::LIKE ) );
		return $this;
	}

	/**
	 * Fetch CentralAuthUsers for the specified query
	 *
	 * @return Iterator<CentralAuthUser>
	 */
	public function fetchCentralAuthUsers(): Iterator {
		$this->init();

		$result = $this->fetchResultSet();
		foreach ( $result as $row ) {
			yield CentralAuthUser::newFromRow( $row, [] );
		}
		$result->free();
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

		if ( $localUserIds === [] ) {
			return new EmptyIterator();
		}

		return $this->actorStore->newSelectQueryBuilder()
			->whereUserIds( $localUserIds )
			->caller( $this->getCaller() )
			->fetchUserIdentities();
	}
}
