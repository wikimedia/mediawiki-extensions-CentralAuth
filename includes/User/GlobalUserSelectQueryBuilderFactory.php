<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\User\ActorStore;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IReadableDatabase;

class GlobalUserSelectQueryBuilderFactory {

	/** @var IReadableDatabase */
	private IReadableDatabase $db;

	/** @var ActorStore */
	private ActorStore $actorStore;

	/** @var UserNameUtils */
	private UserNameUtils $userNameUtils;

	private TempUserConfig $tempUserConfig;

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
		$this->db = $db;
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * @return GlobalUserSelectQueryBuilder
	 */
	public function newGlobalUserSelectQueryBuilder(): GlobalUserSelectQueryBuilder {
		return new GlobalUserSelectQueryBuilder(
			$this->db,
			$this->actorStore,
			$this->userNameUtils,
			$this->tempUserConfig
		);
	}
}
