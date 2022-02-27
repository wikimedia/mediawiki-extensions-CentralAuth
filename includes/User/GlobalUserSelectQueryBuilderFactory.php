<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IDatabase;

class GlobalUserSelectQueryBuilderFactory {

	/** @var IDatabase */
	private $db;

	/** @var ActorStore */
	private $actorStore;

	/** @var UserNameUtils */
	private $userNameUtils;

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
		$this->db = $db;
		$this->actorStore = $actorStore;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @return GlobalUserSelectQueryBuilder
	 */
	public function newGlobalUserSelectQueryBuilder(): GlobalUserSelectQueryBuilder {
		return new GlobalUserSelectQueryBuilder(
			$this->db,
			$this->actorStore,
			$this->userNameUtils
		);
	}
}
