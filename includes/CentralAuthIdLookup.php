<?php

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\User\UserIdentity;

/**
 * Look up central IDs using CentralAuth
 */
class CentralAuthIdLookup extends CentralIdLookup {

	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/**
	 * @param CentralAuthDatabaseManager $databaseManager
	 */
	public function __construct( CentralAuthDatabaseManager $databaseManager ) {
		$this->databaseManager = $databaseManager;
	}

	public function lookupCentralIds(
		array $idToName, $audience = self::AUDIENCE_PUBLIC, $flags = self::READ_NORMAL
	): array {
		if ( !$idToName ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromPrimaryDb = ( $flags & self::READ_LATEST ) === self::READ_LATEST;
		$db = $this->databaseManager->getCentralDB(
			$fromPrimaryDb ? DB_PRIMARY : DB_REPLICA
		);

		$queryInfo = CentralAuthUser::selectQueryInfo();

		$res = $db->select(
			$queryInfo['tables'],
			$queryInfo['fields'],
			[ 'gu_id' => array_map( 'intval', array_keys( $idToName ) ) ] + $queryInfo['where'],
			__METHOD__,
			$queryInfo['options'],
			$queryInfo['joinConds']
		);
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromPrimaryDb );
			if ( $centralUser->getHiddenLevel() === CentralAuthUser::HIDDEN_NONE
				|| $audience === null || $audience->isAllowed( 'centralauth-oversight' )
			) {
				$idToName[$centralUser->getId()] = $centralUser->getName();
			} else {
				$idToName[$centralUser->getId()] = '';
			}
		}

		return $idToName;
	}

	public function lookupUserNames(
		array $nameToId, $audience = self::AUDIENCE_PUBLIC, $flags = self::READ_NORMAL
	): array {
		if ( !$nameToId ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromPrimaryDb = ( $flags & self::READ_LATEST ) === self::READ_LATEST;
		$db = $this->databaseManager->getCentralDB(
			$fromPrimaryDb ? DB_PRIMARY : DB_REPLICA
		);

		$queryInfo = CentralAuthUser::selectQueryInfo();

		$res = $db->select(
			$queryInfo['tables'],
			$queryInfo['fields'],
			[ 'gu_name' => array_map( 'strval', array_keys( $nameToId ) ) ] + $queryInfo['where'],
			__METHOD__,
			$queryInfo['options'],
			$queryInfo['joinConds']
		);
		foreach ( $res as $row ) {
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromPrimaryDb );
			if ( $centralUser->getHiddenLevel() === CentralAuthUser::HIDDEN_NONE
				|| $audience === null || $audience->isAllowed( 'centralauth-oversight' )
			) {
				$nameToId[$centralUser->getName()] = $centralUser->getId();
			}
		}

		return $nameToId;
	}

	public function isAttached( $user, $wikiId = UserIdentity::LOCAL ): bool {
		$wikiId = $wikiId ?: WikiMap::getCurrentWikiId();
		$centralUser = CentralAuthUser::getInstance( $user );
		return $centralUser->getId() != 0 && $centralUser->attachedOn( $wikiId );
	}

	public function centralIdFromLocalUser(
		$user, $audience = self::AUDIENCE_PUBLIC, $flags = self::READ_NORMAL
	): int {
		return $this->isAttached( $user ) ? CentralAuthUser::getInstance( $user )->getId() : 0;
	}

}
