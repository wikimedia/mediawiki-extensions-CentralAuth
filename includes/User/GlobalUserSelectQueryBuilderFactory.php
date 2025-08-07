<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IReadableDatabase;

class GlobalUserSelectQueryBuilderFactory {

	private IReadableDatabase $db;

	private ActorStore $actorStore;

	private UserNameUtils $userNameUtils;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		IReadableDatabase $db,
		ActorStore $actorStore,
		UserNameUtils $userNameUtils,
		TempUserConfig $tempUserConfig
	) {
		$this->db = $db;
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
		$this->tempUserConfig = $tempUserConfig;
	}

	public function newGlobalUserSelectQueryBuilder(): GlobalUserSelectQueryBuilder {
		return new GlobalUserSelectQueryBuilder(
			$this->db,
			$this->actorStore,
			$this->userNameUtils,
			$this->tempUserConfig
		);
	}
}
