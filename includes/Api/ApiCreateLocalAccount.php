<?php

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use ApiMain;
use MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService;

/**
 * API module that can be used to manually create a local account for a global account.
 *
 * @author Taavi "Majavah" Väänänen
 * @since 1.36
 */
class ApiCreateLocalAccount extends ApiBase {
	/** @var CentralAuthForcedLocalCreationService */
	private $forcedLocalCreationService;

	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		CentralAuthForcedLocalCreationService $forcedLocalCreationService
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->forcedLocalCreationService = $forcedLocalCreationService;
	}

	public function execute() {
		$this->checkUserRightsAny( 'centralauth-createlocal' );

		$params = $this->extractRequestParams();

		$username = $params['username'];
		$reason = $params['reason'];

		$status = $this->forcedLocalCreationService
			->attemptAutoCreateLocalUserFromName( $username, $this->getUser(), $reason );

		if ( $status->isGood() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'username' => $username,
				'reason' => $reason
			] );
		} else {
			$error = $this->getErrorFormatter()->arrayFromStatus( $status );
			$this->getResult()->addValue( 'error', null, $error );
		}
	}

	public function getAllowedParams() {
		return [
			'username' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'reason' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=createlocalaccount&username=Example&reason=Because+I+can' => 'apihelp-createlocalaccount-example-1',
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}
}
