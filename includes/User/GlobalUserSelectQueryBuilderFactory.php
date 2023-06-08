<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IReadableDatabase;

class GlobalUserSelectQueryBuilderFactory {

	/** @var IReadableDatabase */
	private $db;

	/** @var ActorStore */
	private $actorStore;

	/** @var UserNameUtils */
	private $userNameUtils;

	/**
	 * @param IReadableDatabase $db
	 * @param ActorStore $actorStore
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		IReadableDatabase $db,
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
