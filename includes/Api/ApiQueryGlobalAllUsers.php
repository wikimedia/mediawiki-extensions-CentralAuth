<?php

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;

/**
 * Api module for CentralAuth extension to list all global users.
 * Partly based on ApiQueryAllUsers.
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
 *
 * @ingroup API
 * @ingroup Extensions
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class ApiQueryGlobalAllUsers extends ApiQueryBase {

	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param GlobalGroupLookup $globalGroupLookup
	 */
	public function __construct(
		$query,
		$moduleName,
		CentralAuthDatabaseManager $databaseManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( $query, $moduleName, 'agu' );
		$this->databaseManager = $databaseManager;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	/**
	 * Get the Query database connection (read-only)
	 *
	 * @see ApiQueryBase::getDB
	 * @return IReadableDatabase
	 */
	protected function getDB() {
		static $db = null;

		if ( $db === null ) {
			$db = $this->databaseManager->getCentralReplicaDB();
		}
		return $db;
	}

	/**
	 * This function converts the user name to a canonical form
	 * which is stored in the database.
	 * @param string $name
	 * @return string
	 */
	private function getCanonicalUserName( $name ) {
		return str_replace( '_', ' ', $name );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$prop = array_flip( (array)$params['prop'] );

		$dir = ( $params['dir'] == 'descending' ? 'older' : 'newer' );

		$db = $this->getDB();
		$this->addTables( 'globaluser' );
		$this->addFields( [ 'gu_id', 'gu_name' ] );
		$limit = intval( $params['limit'] ) + 1;

		$this->addWhereRange(
			'gu_name',
			$dir,
			isset( $params['from'] ) ? $this->getCanonicalUserName( $params['from'] ) : null,
			isset( $params['to'] ) ? $this->getCanonicalUserName( $params['to'] ) : null
		);

		if ( $params['prefix'] !== null ) {
			$this->addWhere(
				$db->expr( 'gu_name', IExpression::LIKE,
					new LikeValue( $this->getCanonicalUserName( $params['prefix'] ), $db->anyString() ) )
			);
		}

		$this->requireMaxOneParameter( $params, 'group', 'excludegroup' );

		if ( !empty( $params['group'] ) ) {
			$this->addTables( 'global_user_groups' );
			// Request more rows than needed to avoid not getting all rows
			// that belong to one user, because a user might be in multiple groups
			$limit += count( $params['group'] ) + 1;

			$this->addJoinConds( [
				'global_user_groups' =>
				[ 'INNER JOIN', 'gug_user = gu_id' ]
			] );

			$this->addWhere( [ 'gug_group' => $params['group'] ] );
			$this->addWhere( [
				$this->getDB()->expr( 'gug_expiry', '=', null )
					->or( 'gug_expiry', '>=', $this->getDB()->timestamp() )
			] );
		}

		if ( !empty( $params['excludegroup'] ) ) {
			$this->addTables( 'global_user_groups', 'gug2' );

			$this->addJoinConds( [
				'gug2' =>
				[ 'LEFT OUTER JOIN', [ 'gug2.gug_user = gu_id', 'gug2.gug_group' => $params['excludegroup'] ] ]
			] );

			$this->addWhere( 'gug2.gug_user IS NULL' );
			$this->addWhere( [
				$this->getDB()->expr( 'gug2.gug_expiry', '=', null )
					->or( 'gug2.gug_expiry', '>=', $this->getDB()->timestamp() )
			] );
		}

		$this->addWhere( [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE ] );

		if ( isset( $prop['lockinfo'] ) ) {
			$this->addFields( 'gu_locked' );
		}

		if ( isset( $prop['existslocally'] ) ) {
			$this->addTables( 'localuser' );
			$this->addFields( 'lu_wiki' );
			$this->addJoinConds( [
				'localuser' =>
				[ 'LEFT OUTER JOIN', [ 'gu_name=lu_name', 'lu_wiki' => WikiMap::getCurrentWikiId() ] ]
			] );
		}

		$this->addOption( 'LIMIT', $limit );

		$result = $this->select( __METHOD__ );

		$groupsOfUser = [];
		if ( isset( $prop['groups'] ) && $result->numRows() ) {
			$groupsOfUser = $this->getGlobalGroups( $result, $dir );
		}

		$data = [];
		$previousName = '';
		$i = 1;
		foreach ( $result as $row ) {
			if ( $row->gu_name === $previousName ) {
				continue;
			}
			$previousName = $row->gu_name;
			if ( $i > $params['limit'] ) {
				$this->setContinueEnumParameter( 'from', $row->gu_name );
				break;
			}
			$i++;

			$entry = [];
			$entry['id'] = (int)$row->gu_id;
			$entry['name'] = $row->gu_name;

			if ( isset( $prop['groups'] ) ) {
				if ( !empty( $groupsOfUser[$row->gu_id] ) ) {
					$entry['groups'] = $groupsOfUser[$row->gu_id];
				} else {
					$entry['groups'] = [];
				}
				$this->getResult()->setIndexedTagName( $entry['groups'], 'group' );
			}

			if ( isset( $prop['existslocally'] ) && $row->lu_wiki != null ) {
				$entry['existslocally'] = '';
			}

			if ( isset( $prop['lockinfo'] ) && $row->gu_locked ) {
				$entry['locked'] = '';
			}

			$data[] = $entry;
		}

		$this->getResult()->setIndexedTagName( $data, 'globaluser' );

		$this->getResult()->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * Get the global groups for the given global user result set.
	 *
	 * @param IResultWrapper $result Result of a globaluser table select
	 * @param string $dir Sorting directory
	 *
	 * @return string[][]
	 */
	protected function getGlobalGroups( IResultWrapper $result, $dir ) {
		$this->resetQueryParams();

		// Get all global groups now. We do this by using a WHERE
		// range build from the given results
		$groupsOfUser = [];

		$this->addTables( [ 'globaluser', 'global_user_groups' ] );
		$this->addFields( [ 'gug_user', 'gug_group' ] );

		$result->seek( 0 );
		$firstUser = $result->fetchObject()->gu_name;
		$result->seek( $result->numRows() - 1 );
		$lastUser = $result->fetchObject()->gu_name;

		$this->addWhereRange(
			'gu_name',
			$dir,
			$firstUser,
			$lastUser,
			false
		);

		// Use an INNER JOIN to only get users with global groups
		$this->addJoinConds( [
			'global_user_groups' =>
			[ 'INNER JOIN', 'gug_user = gu_id' ]
		] );

		$this->addWhere( [
			$this->getDB()->expr( 'gug_expiry', '=', null )
				->or( 'gug_expiry', '>=', $this->getDB()->timestamp() )
		] );

		$groupResult = $this->select( __METHOD__ );

		foreach ( $groupResult as $groupRow ) {
			$groupsOfUser[$groupRow->gug_user][] = $groupRow->gug_group;
		}

		return $groupsOfUser;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		$globalGroups = $this->globalGroupLookup->getDefinedGroups();
		return [
			'from' => null,
			'to' => null,
			'prefix' => null,
			'dir' => [
				ParamValidator::PARAM_DEFAULT => 'ascending',
				ParamValidator::PARAM_TYPE => [
					'ascending',
					'descending'
				],
			],
			'group' => [
				ParamValidator::PARAM_TYPE => $globalGroups,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'excludegroup' => [
				ParamValidator::PARAM_TYPE => $globalGroups,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'lockinfo',
					'groups',
					'existslocally'
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=globalallusers'
				=> 'apihelp-query+globalallusers-example-1',
			'action=query&list=globalallusers&agufrom=ABC&aguprop=lockinfo|groups|existslocally'
				=> 'apihelp-query+globalallusers-example-2',
		];
	}
}
