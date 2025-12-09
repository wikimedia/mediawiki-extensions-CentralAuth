<?php
/**
 * Created on Sep 06, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Jan Luca Naumann jan AT toolserver DOT org
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;
use MediaWiki\Extension\CentralAuth\WikiSet;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Query module to list the wiki sets
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryWikiSets extends ApiQueryBase {

	private CentralAuthWikiListService $wikiListService;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		CentralAuthWikiListService $wikiListService
	) {
		parent::__construct( $query, $moduleName, 'ws' );
		$this->wikiListService = $wikiListService;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );
		$from = $params['from'] ?? null;

		$APIResult = $this->getResult();
		$data = [];

		$wikiList = $this->wikiListService->getWikiList();

		foreach (
			WikiSet::getAllWikiSets( $from, $params['limit'], $params['orderbyname'] ) as $wikiSet
		) {
			$entry = [];
			$entry['id'] = $wikiSet->getId();
			$entry['name'] = $wikiSet->getName();

			if ( isset( $prop['type'] ) ) {
				$entry['type'] = $wikiSet->getType();
			}

			if ( isset( $prop['wikisincluded'] ) ) {
				$entry['wikisincluded'] = $wikiSet->getWikis();
				if ( count( $entry['wikisincluded'] ) ) {
					$APIResult->setIndexedTagName( $entry['wikisincluded'], 'wiki' );
				}
			}

			if ( isset( $prop['wikisnotincluded'] ) ) {
				$entry['wikisnotincluded'] = array_diff(
					$wikiList, $wikiSet->getWikis() );
				if ( count( $entry['wikisnotincluded'] ) ) {
					$APIResult->setIndexedTagName( $entry['wikisnotincluded'], 'wiki' );
				}
			}

			$data[] = $entry;
		}

		$APIResult->setIndexedTagName( $data, 'wikiset' );

		$APIResult->addValue( 'query', $this->getModuleName(), $data );
	}

	/** @inheritDoc */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'from' => null,
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'type',
					'wikisincluded',
					'wikisnotincluded'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'orderbyname' => [
				ParamValidator::PARAM_DEFAULT => false,
				ParamValidator::PARAM_TYPE => 'boolean'
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=wikisets'
				=> 'apihelp-query+wikisets-example-1',
			'action=query&list=wikisets&wsprop=type&wslimit=200'
				=> 'apihelp-query+wikisets-example-2',
		];
	}
}
