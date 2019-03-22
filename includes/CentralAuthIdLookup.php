<?php

/**
 * Look up central IDs using CentralAuth
 */
class CentralAuthIdLookup extends CentralIdLookup {
	public function lookupCentralIds(
		array $idToName, $audience = self::AUDIENCE_PUBLIC, $flags = self::READ_NORMAL
	) {
		if ( !$idToName ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromMaster = ( $flags & self::READ_LATEST ) === self::READ_LATEST;
		$db = $fromMaster
			? CentralAuthUtils::getCentralDB()
			: CentralAuthUtils::getCentralReplicaDB();

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
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromMaster );
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
	) {
		if ( !$nameToId ) {
			return [];
		}

		$audience = $this->checkAudience( $audience );
		$fromMaster = ( $flags & self::READ_LATEST ) === self::READ_LATEST;
		$db = $fromMaster
			? CentralAuthUtils::getCentralDB()
			: CentralAuthUtils::getCentralReplicaDB();

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
			$centralUser = CentralAuthUser::newFromRow( $row, [], $fromMaster );
			if ( $centralUser->getHiddenLevel() === CentralAuthUser::HIDDEN_NONE
				|| $audience === null || $audience->isAllowed( 'centralauth-oversight' )
			) {
				$nameToId[$centralUser->getName()] = $centralUser->getId();
			}
		}

		return $nameToId;
	}

	public function isAttached( User $user, $wikiId = null ) {
		if ( $wikiId === null ) {
			$wikiId = wfWikiId();
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		return $centralUser->getId() != 0 && $centralUser->attachedOn( $wikiId );
	}

	public function centralIdFromLocalUser(
		User $user, $audience = self::AUDIENCE_PUBLIC, $flags = self::READ_NORMAL
	) {
		return $this->isAttached( $user ) ? CentralAuthUser::getInstance( $user )->getId() : 0;
	}

}
