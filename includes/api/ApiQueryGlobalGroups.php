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

		$prop = array_flip( (array) $params['prop'] );

		$APIResult = $this->getResult();
		$data = array();

		$dbr = CentralAuthUser::getCentralSlaveDB();

		$fields = array( 'ggp_group' );

		if ( isset( $prop['rights'] ) ) {
			$fields[] = 'ggp_permission';
		}

		$result = $dbr->select(
			'global_group_permissions',
			$fields,
			array(),
			__METHOD__,
			array( 'DISTINCT' )
		);

		$globalGroups = array();

		foreach ( $result as $row ) {
			if ( !isset( $globalGroups[$row->ggp_group] ) ) {
				$globalGroups[$row->ggp_group] = array( 'rights' => array() );
			}

			if ( isset( $prop['rights'] ) ) {
				$globalGroups[$row->ggp_group]['rights'][] = $row->ggp_permission;
			}
		}

		foreach ( $globalGroups as $name => $value ) {
			$entry = array( 'name' => $name );

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
		return array(
			'prop' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array(
					'rights',
				)
			)
		);
	}

	public function getParamDescription() {
		return array(
			'prop' => array(
				'What pieces of information to include.',
				)
		);
	}

	public function getDescription() {
		return 'Enumerate all global groups';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=globalgroups',
			'api.php?action=query&list=globalgroups&ggpprop=rights'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
