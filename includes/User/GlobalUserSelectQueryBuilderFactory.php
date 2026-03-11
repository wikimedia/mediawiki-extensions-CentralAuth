<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;

class GlobalUserSelectQueryBuilderFactory {

	private CentralAuthDatabaseManager $databaseManager;

	private ActorStore $actorStore;

	private UserNameUtils $userNameUtils;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		ActorStore $actorStore,
		UserNameUtils $userNameUtils,
		TempUserConfig $tempUserConfig
	) {
		$this->databaseManager = $databaseManager;
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
		$this->tempUserConfig = $tempUserConfig;
	}

	public function newGlobalUserSelectQueryBuilder(): GlobalUserSelectQueryBuilder {
		return new GlobalUserSelectQueryBuilder(
			$this->databaseManager->getCentralReplicaDB(),
			$this->actorStore,
			$this->userNameUtils,
			$this->tempUserConfig
		);
	}
}
