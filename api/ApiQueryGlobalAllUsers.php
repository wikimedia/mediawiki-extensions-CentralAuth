<?php
/**
 * Api module for CentralAuth extension to list all global users
 *
 * Copyright (C) 2013 Marius Hoch < hoo@online.de >
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
		parent::__construct( $query, $moduleName, 'agu' );
	}

	/**
	 * Get the Query database connection (read-only)
	 *
	 * @see ApiQueryBase::getDB
	 * @return DatabaseBase
	 */
	protected function getDB() {
		if ( is_null( $this->mDb ) ) {
			$this->mDb = CentralAuthUser::getCentralSlaveDB();
		}
		return $this->mDb;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array) $params['prop'] );

		$dir = ( $params['dir'] == 'descending' ? 'older' : 'newer' );

		$db = $this->getDB();
		$this->addTables( 'globaluser' );
		$this->addFields( array( 'gu_id', 'gu_name' ) );

		$this->addOption( 'LIMIT', intval( $params['limit'] ) + 1 );

		$this->addWhereRange(
			'gu_name',
			$dir,
			isset( $params['from'] ) ? $params['from'] : null,
			isset( $params['to'] ) ? $params['to'] : null
		);

		if ( !is_null( $params['group'] ) && !is_null( $params['excludegroup'] ) ) {
			$this->dieUsage( 'group and excludegroup cannot be used together', 'group-excludegroup' );
		}

		if ( !empty( $params['group'] ) ) {
			$this->addTables( 'global_user_groups' );

			$this->addJoinConds( array(
				'global_user_groups' =>
				array( 'INNER JOIN', 'gug_user = gu_id' )
			) );

			$this->addWhere( array( 'gug_group' => $params['group'] ) );
		}

		if ( !empty( $params['excludegroup'] ) ) {
			$this->addTables( 'global_user_groups', 'gug2' );

			$in = $db->makeList( array( 'gug2.gug_group' => $params['excludegroup'] ), LIST_OR );

			$this->addJoinConds( array(
				'gug2' =>
				array( 'LEFT OUTER JOIN', array( 'gug2.gug_user = gu_id', $in ) )
			) );

			$this->addWhere( 'gug2.gug_user IS NULL' );
		}

		$this->addWhere( array( 'gu_hidden' => CentralAuthUser::HIDDEN_NONE ) );

		if ( isset( $prop['lockinfo'] ) ) {
			$this->addFields( 'gu_locked' );
		}

		if ( isset( $prop['existslocally'] ) ) {
			$this->addTables( 'localuser' );
			$this->addFields( 'lu_wiki' );
			$this->addJoinConds( array(
				'localuser' =>
				array( 'LEFT OUTER JOIN', array( 'gu_name=lu_name', 'lu_wiki' => wfWikiID() ) )
			) );
		}

		$result = $this->select( __METHOD__ );

		$groupsOfUser = array();
		if ( isset( $prop['groups'] ) && count( $result ) ) {
			$groupsOfUser = $this->getGlobalGroups( $result, $dir );
		}

		$data = array(); $i = 1;
		foreach ( $result as $row ) {
			if ( $i > $params['limit'] ) {
				$this->setContinueEnumParameter( 'from', $row->gu_name );
				break;
			}
			$i++;

			$entry = array();
			$entry['id'] = $row->gu_id;
			$entry['name'] = $row->gu_name;

			$user = null;

			if ( isset( $prop['groups'] ) ) {
				if ( count( $groupsOfUser[$row->gu_id] ) ) {
					$entry['groups'] = $groupsOfUser[$row->gu_id];
				} else {
					$entry['groups'] = array();
				}
				$this->getResult()->setIndexedTagName( $entry['groups'], 'group' );
			}

			if ( isset( $prop['existslocally'] ) ) {
				$entry['existslocally'] = ( $row->lu_wiki != null );
			}

			if ( isset( $prop['lockinfo']  ) ) {
				$entry['locked'] = $row->gu_locked;
			}

			$data[] = $entry;
		}

		$this->getResult()->setIndexedTagName( $data, 'globaluser' );

		$this->getResult()->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * Get the global groups for the given global user result set.
	 *
	 * @param array $result Result of a globaluser table select
	 * @param string $dir Sorting directory
	 *
	 * @return array
	 */
	protected function getGlobalGroups( $result, $dir ) {
		$this->resetQueryParams();

		// Get all global groups now. We do this by using the same
		// WHERE as above with some changes
		$groupsOfUser = array();

		$this->addTables( array( 'globaluser', 'global_user_groups' ) );
		$this->addFields( array( 'gug_user', 'gug_group' ) );

		$firstUser = $result->seek( 0 )->gu_name;
		$lastUser = $result->seek( $result->numRows() -1 )->gu_name;

		$this->addWhereRange(
			'gu_name',
			$dir,
			$firstUser,
			$lastUser
		);

		// Use an INNER JOIN to only get users with global groups
		$this->addJoinConds( array(
			'global_user_groups' =>
			array( 'INNER JOIN', 'gug_user = gu_id' )
		) );

		$groupResult = $this->select( __METHOD__ );

		foreach ( $groupResult as $groupRow ) {
			if ( !isset( $groupsOfUser[$groupRow->gug_user] ) ) {
				$groupsOfUser[$groupRow->gug_user] = array();
			}

			$groupsOfUser[$groupRow->gug_user][] = $groupRow->gug_group;
		}

		return $groupsOfUser;
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
			'excludegroup' => array(
				ApiBase::PARAM_TYPE => $globalGroups,
				ApiBase::PARAM_ISMULTI => true,
			),
			'prop' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array(
					'lockinfo',
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
				' lockinfo       - Whether the user account is locked',
				' groups         - Lists global groups that the user is in. This uses more server resources and may return fewer results than the limit',
				' existslocally  - Adds the information if the user exists locally',
				),
			'limit' => 'How many total user names to return'
		);
	}

	public function getDescription() {
		return 'Enumerate all global users';
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'group-excludegroup', 'info' => 'group and excludegroup cannot be used together' ),
		) );
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=globalallusers',
			'api.php?action=query&list=globalallusers&agufrom=ABC&aguprop=lockinfo|groups|existslocally'
		);
	}
}
