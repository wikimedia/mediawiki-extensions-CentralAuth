<?php
/**
 * Created on Sep 20, 2012
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
 * Query module to list all global groups
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryGlobalGroups extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ggp' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );

		$APIResult = $this->getResult();
		$data = [];

		$dbr = CentralAuthUtils::getCentralReplicaDB();

		$fields = [ 'ggp_group' ];

		if ( isset( $prop['rights'] ) ) {
			$fields[] = 'ggp_permission';
		}

		$result = $dbr->select(
			'global_group_permissions',
			$fields,
			[],
			__METHOD__,
			[ 'DISTINCT' ]
		);

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

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
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
