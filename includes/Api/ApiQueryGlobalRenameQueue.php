<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API list module for querying the global rename request queue.
 */
class ApiQueryGlobalRenameQueue extends ApiQueryBase {

	private GlobalRenameRequestStore $globalRenameRequestStore;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		GlobalRenameRequestStore $globalRenameRequestStore
	) {
		parent::__construct( $queryModule, $moduleName, 'grq' );
		$this->globalRenameRequestStore = $globalRenameRequestStore;
	}

	/** @inheritDoc */
	public function execute(): void {
		$this->checkUserRightsAny( 'centralauth-rename' );

		$params = $this->extractRequestParams();

		$continueId = null;
		if ( $params['continue'] !== null ) {
			$continueId = (int)$params['continue'];
			$this->dieContinueUsageIf( $continueId <= 0 );
		}

		if ( $params['queue'] === 'open' ) {
			$statusFilter = GlobalRenameRequest::PENDING;
		} elseif ( $params['status'] !== 'all' ) {
			$statusFilter = $params['status'];
		} else {
			$statusFilter = [ GlobalRenameRequest::APPROVED, GlobalRenameRequest::REJECTED ];
		}

		$typeFilter = null;
		if ( $params['type'] === 'rename' ) {
			$typeFilter = GlobalRenameRequest::RENAME;
		} elseif ( $params['type'] === 'vanish' ) {
			$typeFilter = GlobalRenameRequest::VANISH;
		}

		$requests = $this->globalRenameRequestStore->queryRequestQueue(
			$params['user'] ?? null,
			$params['newname'] ?? null,
			$params['performer'] ?? null,
			$statusFilter,
			$typeFilter,
			$params['limit'] + 1,
			$continueId
		);

		$result = $this->getResult();
		$count = 0;
		foreach ( $requests as $request ) {
			if ( ++$count > $params['limit'] ) {
				$this->setContinueEnumParameter( 'continue', (string)$request->getId() );
				break;
			}

			$type = $request->getType() ?? GlobalRenameRequest::RENAME;
			$entry = [
				'id'        => $request->getId(),
				'name'      => $request->getName(),
				'newname'   => $request->getNewName(),
				'wiki'      => $request->getWiki(),
				'status'    => $request->getStatus(),
				'type'      => $type === GlobalRenameRequest::VANISH ? 'vanish' : 'rename',
				'requested' => wfTimestamp( TS_ISO_8601, $request->getRequested() ),
			];

			if ( $request->getCompleted() !== null ) {
				$entry['completed'] = wfTimestamp( TS_ISO_8601, $request->getCompleted() );
			}

			if ( $request->getComments() !== null && $request->getComments() !== '' ) {
				$entry['comments'] = $request->getComments();
			}

			if ( $request->getPerformer() !== null ) {
				$performer = CentralAuthUser::newFromId( $request->getPerformer() );
				if ( $performer !== false ) {
					$entry['performer'] = $performer->getName();
				}
			}

			$result->addValue( [ 'query', $this->getModuleName() ], null, $entry );
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'request' );
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'queue' => [
				ParamValidator::PARAM_TYPE => [ 'open', 'closed' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'status' => [
				ParamValidator::PARAM_TYPE => [
					'all',
					GlobalRenameRequest::APPROVED,
					GlobalRenameRequest::REJECTED,
				],
				ParamValidator::PARAM_DEFAULT => 'all',
			],
			'type' => [
				ParamValidator::PARAM_TYPE => [ 'all', 'rename', 'vanish' ],
				ParamValidator::PARAM_DEFAULT => 'all',
			],
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'newname' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'performer' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiQueryBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiQueryBase::LIMIT_BIG2,
			],
			'continue' => [
				ParamValidator::PARAM_TYPE => 'string',
				self::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=globalrenamequeue&grqqueue=open'
				=> 'apihelp-query+globalrenamequeue-example-open',
			'action=query&list=globalrenamequeue&grqqueue=closed&grquser=Example'
				=> 'apihelp-query+globalrenamequeue-example-closed-user',
			'action=query&list=globalrenamequeue&grqqueue=closed&grqstatus=approved'
				=> 'apihelp-query+globalrenamequeue-example-approved',
			'action=query&list=globalrenamequeue&grqqueue=closed&grqtype=vanish'
				=> 'apihelp-query+globalrenamequeue-example-vanish',
		];
	}

}
