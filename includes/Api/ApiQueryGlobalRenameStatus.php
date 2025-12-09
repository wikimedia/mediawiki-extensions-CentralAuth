<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module equivalent of Special:GlobalRenameStatus
 */
class ApiQueryGlobalRenameStatus extends ApiQueryBase {

	private GlobalRenameFactory $globalRenameFactory;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		GlobalRenameFactory $globalRenameFactory
	) {
		parent::__construct( $queryModule, $moduleName, 'grs' );
		$this->globalRenameFactory = $globalRenameFactory;
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
		$statuses = $this->globalRenameFactory->newGlobalRenameUserStatus( $name );
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
	public function getAllowedParams() {
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
