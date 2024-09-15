<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use ExpireTemporaryAccounts;
use Iterator;
use LogicException;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilder;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/expireTemporaryAccounts.php";

// @phpcs:ignore MediaWiki.Files.ClassMatchesFilename.NotMatch
class CentralAuthExpireTemporaryAccounts extends ExpireTemporaryAccounts {

	private GlobalUserSelectQueryBuilderFactory $globalUserSelectQueryBuilderFactory;

	/**
	 * @inheritDoc
	 */
	protected function initServices(): void {
		parent::initServices();

		$this->globalUserSelectQueryBuilderFactory = CentralAuthServices::getGlobalUserSelectQueryBuilderFactory();
	}

	/**
	 * @inheritDoc
	 */
	protected function getTempAccountsToExpireQueryBuilder(
		int $registeredBeforeUnix,
		int $frequencyDays
	): SelectQueryBuilder {
		return $this->globalUserSelectQueryBuilderFactory->newGlobalUserSelectQueryBuilder()
			->temp()
			->whereRegisteredTimestamp( wfTimestamp(
				TS_MW,
				$registeredBeforeUnix
			), true )
			->whereRegisteredTimestamp( wfTimestamp(
				TS_MW,
				$registeredBeforeUnix - ( $frequencyDays * 24 * 3600 )
			), false );
	}

	/**
	 * @inheritDoc
	 */
	protected function queryBuilderToUserIdentities( SelectQueryBuilder $queryBuilder ): Iterator {
		if ( $queryBuilder instanceof GlobalUserSelectQueryBuilder ) {
			return $queryBuilder->fetchLocalUserIdentities();
		}

		// not expected to happen; might be caused by an error in core's expireTemporaryAccounts.php?
		throw new LogicException( '$queryBuilder is not GlobalUserSelectQueryBuilder' );
	}
}

$maintClass = CentralAuthExpireTemporaryAccounts::class;
require_once RUN_MAINTENANCE_IF_MAIN;
