<?php
/**
 * Created on Oct 21, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Alex Monk (krenair@gmail.com)
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to delete a global account.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiDeleteGlobalAccount extends ApiBase {

	public function execute() {
		// Heavily based on code from SpecialCentralAuth::doSubmit
		$this->checkUserRightsAny( 'centralauth-unmerge' );

		$params = $this->extractRequestParams();

		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $params['user'] );
		if ( !$globalUser->exists() ||
			( $globalUser->isSuppressed() && !$this->getAuthority()->isAllowed( 'centralauth-suppress' ) )
		) {
			$this->dieWithError( [ 'nosuchusershort', wfEscapeWikitext( $globalUser->getName() ) ] );

		}

		$status = $globalUser->adminDelete( $params['reason'], $this->getUser() );
		if ( $status->isGood() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'user' => $globalUser->getName(),
				'reason' => $params['reason']
			] );
		} else {
			$error = $this->getErrorFormatter()->arrayFromStatus( $status );
			$this->getResult()->addValue( 'error', null, $error );
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=deleteglobalaccount&user=Example&reason=Because+I+can'
				=> 'apihelp-deleteglobalaccount-example-1',
		];
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'deleteglobalaccount';
	}
}
