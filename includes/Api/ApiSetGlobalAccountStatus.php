<?php
/**
 * Created on Oct 17, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Alex Monk (krenair@gmail.com)
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

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to lock/hide a global account.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiSetGlobalAccountStatus extends ApiBase {
	public function execute() {
		// Heavily based on code from SpecialCentralAuth::doSubmit
		$params = $this->extractRequestParams();
		$this->requireAtLeastOneParameter( $params, 'locked', 'hidden' );

		$this->checkUserRightsAny( 'centralauth-lock' );

		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $params['user'] );
		if ( !$globalUser->exists() ||
			$globalUser->isSuppressed() && !$this->getAuthority()->isAllowed( 'centralauth-suppress' )
		) {
			$this->dieWithError(
				[ 'nosuchusershort', wfEscapeWikiText( $globalUser->getName() ) ], 'nosuchuser'
			);
		}

		if ( !$params['locked'] ) {
			// Don't lock or unlock
			$setLocked = null;
		} else {
			$setLocked = $params['locked'] === 'lock';
		}

		$setHidden = $params['hidden'];
		$reason = $params['reason'];
		$stateCheck = $params['statecheck'];

		if ( $stateCheck && $stateCheck !== $globalUser->getStateHash( true ) ) {
			if ( $stateCheck !== $globalUser->getStateHash( true, true ) ) {
				$this->dieWithError( 'apierror-centralauth-editconflict', 'editconflict' );
			}

			// TODO: throw a deprecation warning when this module supports new int hidden values
		}

		$status = $globalUser->adminLockHide(
			$setLocked,
			$setHidden,
			$reason,
			$this->getContext()
		);

		// Logging etc
		if ( $status->isGood() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $globalUser->getHiddenLevel(),
				'reason' => $reason
			] );
		} else {
			$error = $this->getErrorFormatter()->arrayFromStatus( $status );
			$this->getResult()->addValue( 'error', null, $error );
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $globalUser->getHiddenLevel(),
			] );
		}
	}

	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'locked' => [
				ParamValidator::PARAM_TYPE => [
					'lock',
					'unlock',
					'' // Unset - basically means 'do not modify lock status'
				]
			],
			'hidden' => [
				ParamValidator::PARAM_TYPE => [
					CentralAuthUser::HIDDEN_NONE,
					CentralAuthUser::HIDDEN_LISTS,
					CentralAuthUser::HIDDEN_OVERSIGHT
				]
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string'
			],
			'statecheck' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=setglobalaccountstatus&user=Example&locked=lock&hidden=&reason=Spam'
				=> 'apihelp-setglobalaccountstatus-example-1',
			'action=setglobalaccountstatus&user=Example&locked=unlock&hidden=suppressed&reason=I%20can'
				=> 'apihelp-setglobalaccountstatus-example-2',
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'setglobalaccountstatus';
	}
}
