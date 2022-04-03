<?php
/**
 * Created on Sep 06, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Jan Luca Naumann jan AT toolserver DOT org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use ApiQueryBase;
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
	/** @var CentralAuthWikiListService */
	private $wikiListService;

	public function __construct( $query, $moduleName, CentralAuthWikiListService $wikiListService ) {
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

		/**
		 * @var $wikiSet WikiSet
		 */
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

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return [
			'from' => null,
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'type',
					'wikisincluded',
					'wikisnotincluded'
				]
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
