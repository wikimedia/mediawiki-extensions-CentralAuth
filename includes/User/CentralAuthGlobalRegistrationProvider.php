<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\User\Registration\IUserRegistrationProvider;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;

class CentralAuthGlobalRegistrationProvider implements IUserRegistrationProvider {

	public const TYPE = 'centralauth';

	private GlobalUserSelectQueryBuilderFactory $globalUserSelectQueryBuilderFactory;
	private CentralAuthDatabaseManager $centralAuthDatabaseManager;
	private UserNameUtils $userNameUtils;

	public function __construct(
		GlobalUserSelectQueryBuilderFactory $globalUserSelectQueryBuilderFactory,
		CentralAuthDatabaseManager $centralAuthDatabaseManager,
		UserNameUtils $userNameUtils
	) {
		$this->globalUserSelectQueryBuilderFactory = $globalUserSelectQueryBuilderFactory;
		$this->centralAuthDatabaseManager = $centralAuthDatabaseManager;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchRegistration( UserIdentity $user ) {
		if ( !$user->isRegistered() ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists() && $centralUser->isAttached() ) {
			return $centralUser->getRegistration();
		}
		return null;
	}

	/**
	 * Get user registration timestamps for a batch of users.
	 *
	 * @param iterable<UserIdentity> $users
	 * @return string[]|null[] Map of registration timestamps in MediaWiki format
	 * (or `null` if not available) keyed by local user ID.
	 */
	public function fetchRegistrationBatch( iterable $users ): array {
		$localIdsByName = [];
		$timestampsByLocalId = [];

		foreach ( $users as $user ) {
			$timestampsByLocalId[$user->getId()] = null;

			// Canonicalize user names before mapping them to local IDs
			// to ensure they will match global user names, since whereUserNames()
			// canonicalizes input user names before querying.
			// This also takes care of filtering anonymous users from the input.
			$canonicalName = $this->userNameUtils->getCanonical( $user->getName() );
			if ( $canonicalName !== false ) {
				$localIdsByName[$canonicalName] = $user->getId();
			}
		}

		$batches = array_chunk( array_keys( $localIdsByName ), 1_000 );

		$dbr = $this->centralAuthDatabaseManager->getCentralReplicaDB();

		foreach ( $batches as $userNameBatch ) {
			$centralUsers = $this->globalUserSelectQueryBuilderFactory
				->newGlobalUserSelectQueryBuilder()
				->whereUserNames( $userNameBatch )
				// Only use the global registration timestamp if the global user is attached.
				->andWhere( $dbr->expr( 'lu_wiki', '!=', null ) )
				->caller( __METHOD__ )
				->fetchCentralAuthUsers();

			foreach ( $centralUsers as $centralUser ) {
				$localId = $localIdsByName[$centralUser->getName()];
				$timestampsByLocalId[$localId] = $centralUser->getRegistration();
			}
		}

		return $timestampsByLocalId;
	}
}
