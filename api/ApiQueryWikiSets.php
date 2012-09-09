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

		$prop = isset( $params['prop'] ) ? array_flip( (array)$params['prop'] ) : false;
		$limit = isset( $params['limit'] ) ? $params['limit'] : 10;
		$offset = isset( $params['offset'] ) ? $params['offset'] : 0;

		$APIResult = $this->getResult();
		$data = array();

		$i = 0;
		foreach ( WikiSet::getAllWikiSets() as $wikiSet ) {
			if ( $i < $offset ) {
				$i++;
				continue;
			}

			if ( $i >= ( $offset + $limit ) ) {
				break;
			}

			$entry = array();
			$entry['id'] = $wikiSet->getId();
			$entry['name'] = $wikiSet->getName();

			if ( isset( $prop['type'] ) ) {
				$entry['type'] = $wikiSet->getType();
			}

			if ( isset( $prop['wikisincluded'] ) ) {
				$entry['wikisincluded'] = $wikiSet->getWikis();
				$APIResult->setIndexedTagName( $entry['wikisincluded'], 'wiki' );
			}

			if ( isset( $prop['wikisnotincluded']  ) ) {
				$entry['wikisnotincluded'] = array_diff( CentralAuthUser::getWikiList(), $wikiSet->getWikis() );
				$APIResult->setIndexedTagName( $entry['wikisincluded'], 'wiki' );
			}

			$data[] = $entry;
			$i++;
		}

		$APIResult->setIndexedTagName( $data, 'wikiset' );

		$APIResult->addValue( 'query', $this->getModuleName(), $data );
	}

	public function getCacheMode( $params ) {
		return 'anon-public-user-private';
	}

	public function getAllowedParams() {
		$globalGroups = CentralAuthUser::availableGlobalGroups();
		return array(
			'prop' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array(
					'type',
					'wikisincluded',
					'wikisnotincluded'
				)
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
			'offset' => array(
				ApiBase::PARAM_DFLT => 0,
				ApiBase::PARAM_TYPE => 'integer',
			)
		);
	}

	public function getParamDescription() {
		return array(
			'prop' => array(
				'What pieces of information to include.',
				' type             - Opt-in based (includes only specified wikis) or Opt-out based (includes all wikis except specified)',
				' wikisincluded    - The wikis that are included in this wiki set',
				' wikisnotincluded - The wikis that are not included in this wiki set',
				),
			'limit' => 'How many total wiki sets to return',
			'offset' => 'Where to start the selection (first element = index 0)'
		);
	}

	public function getDescription() {
		return 'Enumerate all wiki sets';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=wikisets',
			'api.php?action=query&list=wikisets&wsprop=type&wslimit=200'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
