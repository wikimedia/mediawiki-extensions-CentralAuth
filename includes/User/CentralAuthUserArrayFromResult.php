<?php
/**
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
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use CentralAuthUser;
use CentralAuthUtils;
use stdClass;
use UserArrayFromResult;
use WikiMap;
use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthUserArrayFromResult extends UserArrayFromResult {
	/** @var stdClass[] */
	private $globalData;

	/**
	 * @param IResultWrapper $res
	 */
	public function __construct( IResultWrapper $res ) {
		parent::__construct( $res );

		if ( $res->numRows() == 0 ) {
			return;
		}

		/**
		 * Load global user data
		 */
		$names = [];
		foreach ( $res as $row ) {
			$names[] = $row->user_name;
		}
		$res->rewind();

		$dbr = CentralAuthUtils::getCentralReplicaDB();
		$caRes = $dbr->select(
			[ 'localuser', 'globaluser', 'renameuser_status' ],
			'*',
			[
				'gu_name' => $names,
				'lu_name=gu_name',
				'lu_wiki' => WikiMap::getCurrentWikiId()
			],
			__METHOD__,
			[],
			[
				'renameuser_status' => [ 'LEFT OUTER JOIN', [ $dbr->makeList(
					[ 'ru_oldname=gu_name', 'ru_newname=gu_name' ],
					LIST_OR
				) ] ]
			]
		);
		$this->globalData = [];
		foreach ( $caRes as $row ) {
			$this->globalData[$row->gu_name] = $row;
		}
		wfDebug( __METHOD__ . ': got user data for ' . implode( ', ',
			array_keys( $this->globalData ) ) . "\n" );
	}

	/**
	 * @param stdClass|bool $row
	 */
	public function setCurrent( $row ) {
		parent::setCurrent( $row );

		if ( $row !== false ) {
			if ( isset( $this->globalData[$row->user_name] ) ) {
				$caRow = $this->globalData[$row->user_name];

				// Like taken from GlobalRenameUserStatus::getNames
				$renameUser = [];
				if ( $caRow->ru_oldname ) {
					$renameUser = [ $caRow->ru_oldname, $caRow->ru_newname ];
				}

				CentralAuthUser::setInstance(
					$this->current, CentralAuthUser::newFromRow( $caRow, $renameUser )
				);
			} else {
				CentralAuthUser::setInstance(
					$this->current, CentralAuthUser::newUnattached( $row->user_name )
				);
			}
		}
	}
}
