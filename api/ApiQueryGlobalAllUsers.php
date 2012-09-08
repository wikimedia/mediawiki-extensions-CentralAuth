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
 * Query module to list all global users
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryGlobalAllUsers extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'gau' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );

		$APIResult = $this->getResult();
		$data = array();

		$db = CentralAuthUser::getCentralSlaveDB();

		$tables = array( 'globaluser' );
		$fields = array( 'gu_id', 'gu_name' );
		$where = array();
		$options = array();
		$join = array();

		$options['LIMIT'] = (int)$db->strencode( $params['limit'] );

		if ( isset( $params['from'] ) ) {
			$where[] = "gu_name >= " . $db->addQuotes( $params['from'] );
		}

		if ( isset( $params['to'] ) ) {
			$where[] = "gu_name < " . $db->addQuotes( $params['to'] );
		}

		if ( $params['dir'] == 'descending' ) {
			$options['ORDER BY'] = 'gu_name DESC';
		} else {
			$options['ORDER BY'] = 'gu_name';
		}

		if ( isset( $params['group'] ) ) {
			if ( is_array( $params['group'] ) ) {
				$group = $params['group'];
			} else {
				$group = array( $params['group'] );
			}

			$tables[] = 'global_user_groups';

			$join['global_user_groups'] = array( 'INNER JOIN', 'gug_user = gu_id' );

			$whereGroup = '';
			$first = true;
			foreach ( $group as $g ) {
				if ( $first ) {
					$whereGroup .= 'gug_group = ' . $db->addQuotes( $g );
					$first = false;
				} else {
					$whereGroup .= 'OR gug_group = ' . $db->addQuotes( $g );
				}
			}

			$where[] = '(' . $whereGroup . ')';
		}

		if ( isset( $prop['blockinfo'] ) ) {
			$fields[] = 'gu_locked';
		}

		$localwiki = wfWikiID();

		$result = $db->select( $tables, $fields, $where, __METHOD__, $options, $join );

		foreach ( $result as $row ) {
			$entry = array();
			$entry['id'] = $row->gu_id;
			$entry['name'] = $row->gu_name;

			$user = null;

			if ( isset( $prop['groups'] ) ) {
				$user = new CentralAuthUser( $row->gu_name );
				$entry['groups'] = $user->getGlobalGroups();
				$APIResult->setIndexedTagName( $entry['groups'], 'group' );
			}

			if ( isset( $prop['existslocally'] ) ) {
				if ( $user == null ) $user = new CentralAuthUser( $row->gu_name );
				$entry['existslocally'] = $user->isAttached();
			}

			if ( isset( $prop['blockinfo']  ) ) {
				$entry['isblocked'] = $row->gu_locked;
			}

			$data[] = $entry;
		}

		$APIResult->setIndexedTagName( $data, 'globaluser' );

		$APIResult->addValue( 'query', $this->getModuleName(), $data );
	}

	public function getCacheMode( $params ) {
		return 'anon-public-user-private';
	}

	public function getAllowedParams() {
		$globalGroups = CentralAuthUser::availableGlobalGroups();
		return array(
			'from' => null,
			'to' => null,
			'dir' => array(
				ApiBase::PARAM_DFLT => 'ascending',
				ApiBase::PARAM_TYPE => array(
					'ascending',
					'descending'
				),
			),
			'group' => array(
				ApiBase::PARAM_TYPE => $globalGroups,
				ApiBase::PARAM_ISMULTI => true,
			),
			'prop' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array(
					'blockinfo',
					'groups',
					'existslocally'
				)
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			)
		);
	}

	public function getParamDescription() {
		return array(
			'from' => 'The user name to start enumerating from',
			'to' => 'The user name to stop enumerating at',
			'dir' => 'Direction to sort in',
			'group' => 'Limit users to given global group name(s)',
			'excludegroup' => 'Exclude users in given global group name(s)',
			'prop' => array(
				'What pieces of information to include.',
				' blockinfo      - Adds the information about a current block on the user',
				' groups         - Lists global groups that the user is in. This uses more server resources and may return fewer results than the limit',
				' existslocally  - Adds the information if the user exists locally',
				),
			'limit' => 'How many total user names to return'
		);
	}

	public function getDescription() {
		return 'Enumerate all global users';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=globalalluser',
			'api.php?action=query&list=globalalluser&from=ABC&guiprop=blockinfo|groups|existslocally'
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
