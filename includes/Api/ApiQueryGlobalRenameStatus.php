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

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiQuery;
use ApiQueryBase;
use ApiResult;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module equivalent of Special:GlobalRenameStatus
 */
class ApiQueryGlobalRenameStatus extends ApiQueryBase {
	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, 'grs' );
	}

	/**
	 * If a 'user' parameter is provided, get the details for that
	 * user, otherwise output details for all current renames
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		if ( $params['user'] ) {
			$this->addUser( $params['user'] );
		} else {
			$renames = GlobalRenameUserStatus::getInProgressRenames( $this->getUser() );
			foreach ( $renames as $user ) {
				$this->addUser( $user );
			}
		}
	}

	/**
	 * Look up and add info for a rename
	 *
	 * @param string $name Username (old or new)
	 */
	private function addUser( $name ) {
		$statuses = new GlobalRenameUserStatus( $name );
		$names = $statuses->getNames();
		if ( !$names ) {
			return;
		}
		$info = [
			'from' => $names[0],
			'to' => $names[1],
			'status' => $statuses->getStatuses(),
		];
		ApiResult::setArrayType( $info['status'], 'assoc' );
		$this->getResult()->addValue( [ 'query', 'globalrenamestatus' ], $name, $info );
	}

	/**
	 * @see ApiBase::getAllowedParams()
	 * @return array[]
	 */
	public function getAllowedParams( /* $flags = 0 */ ) {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=globalrenamestatus'
				=> 'apihelp-query+globalrenamestatus-example-1',
		];
	}
}
