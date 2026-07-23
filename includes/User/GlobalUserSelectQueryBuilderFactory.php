<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;

class GlobalUserSelectQueryBuilderFactory {

	private CentralAuthConnectionProvider $caConnectionProvider;

	private ActorStore $actorStore;

	private UserNameUtils $userNameUtils;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		CentralAuthConnectionProvider $caConnectionProvider,
		ActorStore $actorStore,
		UserNameUtils $userNameUtils,
		TempUserConfig $tempUserConfig
	) {
		$this->caConnectionProvider = $caConnectionProvider;
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
		$this->tempUserConfig = $tempUserConfig;
	}

	public function newGlobalUserSelectQueryBuilder(): GlobalUserSelectQueryBuilder {
		return new GlobalUserSelectQueryBuilder(
			$this->caConnectionProvider->getReplicaDatabase(),
			$this->actorStore,
			$this->userNameUtils,
			$this->tempUserConfig
		);
	}
}
