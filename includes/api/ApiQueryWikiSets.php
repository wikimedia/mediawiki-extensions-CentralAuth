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

/**
 * Query module to list the wiki sets
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryWikiSets extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ws' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );
		$from = $params['from'] ?? null;

		$APIResult = $this->getResult();
		$data = [];

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
					CentralAuthUser::getWikiList(), $wikiSet->getWikis() );
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
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'type',
					'wikisincluded',
					'wikisnotincluded'
				]
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'orderbyname' => [
				ApiBase::PARAM_DFLT => false,
				ApiBase::PARAM_TYPE => 'boolean'
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
