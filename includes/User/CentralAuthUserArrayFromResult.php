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

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\User\UserArrayFromResult;
use MediaWiki\WikiMap\WikiMap;
use stdClass;
use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthUserArrayFromResult extends UserArrayFromResult {

	/** @var array<string,stdClass> */
	private array $globalData = [];

	public function __construct( IResultWrapper $res ) {
		parent::__construct( $res );

		if ( !$res->numRows() ) {
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

		$dbr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();
		$caRes = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'localuser' )
			->join( 'globaluser', null, 'lu_name=gu_name' )
			->leftJoin( 'renameuser_status', null, 'ru_oldname=gu_name OR ru_newname=gu_name' )
			->where( [
				'gu_name' => $names,
				'lu_wiki' => WikiMap::getCurrentWikiId()
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $caRes as $row ) {
			$this->globalData[$row->gu_name] = $row;
		}
		wfDebug( __METHOD__ . ': got user data for ' . implode( ', ',
			array_keys( $this->globalData ) ) . "\n" );
	}

	/**
	 * @param stdClass|null|false $row
	 */
	public function setCurrent( $row ) {
		parent::setCurrent( $row );

		if ( $this->valid() ) {
			$user = $this->current();
			if ( isset( $this->globalData[$user->getName()] ) ) {
				$caRow = $this->globalData[$user->getName()];

				// Like taken from GlobalRenameUserStatus::getNames
				$renameUser = [];
				if ( $caRow->ru_oldname ) {
					$renameUser = [ $caRow->ru_oldname, $caRow->ru_newname ];
				}

				CentralAuthServices::getUserCache()->set(
					CentralAuthUser::newFromRow( $caRow, $renameUser )
				);
			} else {
				CentralAuthServices::getUserCache()->set(
					CentralAuthUser::newUnattached( $user->getName() )
				);
			}
		}
	}
}
