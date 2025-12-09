<?php
/**
 * Created on Sep 20, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Jan Luca Naumann jan AT toolserver DOT org
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to list all global groups
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryGlobalGroups extends ApiQueryBase {

	private CentralAuthDatabaseManager $databaseManager;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		CentralAuthDatabaseManager $databaseManager
	) {
		parent::__construct( $query, $moduleName, 'ggp' );
		$this->databaseManager = $databaseManager;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );

		$APIResult = $this->getResult();
		$data = [];

		$dbr = $this->databaseManager->getCentralReplicaDB();

		$qb = $dbr->newSelectQueryBuilder()
			->select( 'ggp_group' )
			->distinct()
			->from( 'global_group_permissions' )
			->caller( __METHOD__ );

		if ( isset( $prop['rights'] ) ) {
			$qb->select( 'ggp_permission' );
		}

		$result = $qb->fetchResultSet();

		$globalGroups = [];

		foreach ( $result as $row ) {
			if ( !isset( $globalGroups[$row->ggp_group] ) ) {
				$globalGroups[$row->ggp_group] = [ 'rights' => [] ];
			}

			if ( isset( $prop['rights'] ) ) {
				$globalGroups[$row->ggp_group]['rights'][] = $row->ggp_permission;
			}
		}

		foreach ( $globalGroups as $name => $value ) {
			$entry = [ 'name' => $name ];

			if ( isset( $prop['rights'] ) && count( $value['rights'] ) ) {
				$entry['rights'] = $value['rights'];
				$APIResult->setIndexedTagName( $entry['rights'], 'right' );
			}

			$data[] = $entry;
		}

		$APIResult->setIndexedTagName( $data, 'globalgroup' );

		$APIResult->addValue( 'query', $this->getModuleName(), $data );
	}

	/** @inheritDoc */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'rights',
				]
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=globalgroups'
				=> 'apihelp-query+globalgroups-example-1',
			'action=query&list=globalgroups&ggpprop=rights'
				=> 'apihelp-query+globalgroups-example-2',
		];
	}
}
