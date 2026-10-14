<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\CentralAuth\CentralAuthConnectionProvider;
use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;

class GlobalUserSelectQueryBuilderFactory {

	public function __construct(
		private readonly CentralAuthConnectionProvider $caConnectionProvider,
		private readonly ActorStore $actorStore,
		private readonly UserNameUtils $userNameUtils,
		private readonly TempUserConfig $tempUserConfig,
	) {
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
