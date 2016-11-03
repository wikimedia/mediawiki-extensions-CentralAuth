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

/**
 * API module to lock/hide a global account.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiSetGlobalAccountStatus extends ApiBase {
	/* Heavily based on code from SpecialCentralAuth::doSubmit */
	public function execute() {
		$params = $this->extractRequestParams();
		$this->requireAtLeastOneParameter( $params, 'locked', 'hidden' );

		if ( is_callable( [ $this, 'checkUserRightsAny' ] ) ) {
			$this->checkUserRightsAny( 'centralauth-lock' );
		} else {
			if ( !$this->getUser()->isAllowed( 'centralauth-lock' ) ) {
				$this->dieUsageMsg( [ 'badaccess-groups' ] );
			}
		}

		$globalUser = CentralAuthUser::getMasterInstanceByName( $params['user'] );
		if ( !$globalUser->exists() ||
			$globalUser->isOversighted() && !$this->getUser()->isAllowed( 'centralauth-oversight' )
		) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError(
					[ 'nosuchusershort', wfEscapeWikiText( $globalUser->getName() ) ], 'nosuchuser'
				);
			} else {
				$this->dieUsageMsg( [ 'nosuchuser', $globalUser->getName() ] );
			}
		}

		if ( !$params['locked'] ) {
			// Don't lock or unlock
			$setLocked = null;
		} else {
			$setLocked = $setLocked === 'lock';
		}

		$setHidden = $params['hidden'];
		$reason = $params['reason'];
		$stateCheck = $params['statecheck'];

		if ( $stateCheck && $stateCheck !== $globalUser->getStateHash( true ) ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apierror-centralauth-editconflict', 'editconflict' );
			} else {
				$this->dieUsage( 'Edit conflict detected, Aborting.', 'editconflict' );
			}
		}

		$status = $globalUser->adminLockHide(
			$setLocked,
			$setHidden,
			$reason,
			$this->getContext()
		);

		// Logging etc
		if ( $status->isGood() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), array(
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $globalUser->getHiddenLevel(),
				'reason' => $reason
			) );
		} else {
			$error = $this->getErrorFormatter()->arrayFromStatus( $status );
			$this->getResult()->addValue( 'error', null, $error );
			$this->getResult()->addValue( null, $this->getModuleName(), array(
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $globalUser->getHiddenLevel(),
			) );
		}
	}

	public function getAllowedParams() {
		return array(
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'locked' => array(
				ApiBase::PARAM_TYPE => array(
					'lock',
					'unlock',
					'' // Unset - basically means 'do not modify lock status'
				)
			),
			'hidden' => array(
				ApiBase::PARAM_TYPE => array(
					CentralAuthUser::HIDDEN_NONE,
					CentralAuthUser::HIDDEN_LISTS,
					CentralAuthUser::HIDDEN_OVERSIGHT
				)
			),
			'reason' => array(
				ApiBase::PARAM_TYPE => 'string'
			),
			'statecheck' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=setglobalaccountstatus&user=Example&locked=lock&hidden=&reason=Spam'
				=> 'apihelp-setglobalaccountstatus-example-1',
			'action=setglobalaccountstatus&user=Example&locked=unlock&hidden=suppressed&reason=I%20can'
				=> 'apihelp-setglobalaccountstatus-example-2',
		);
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

	public static function getToken() {
		global $wgUser;
		if ( !$wgUser->isAllowed( 'centralauth-lock' ) ) {
			return false;
		}

		return $wgUser->getEditToken( 'setglobalaccountstatus' );
	}

	public static function injectTokenFunction( &$list ) {
		$list['setglobalaccountstatus'] = array( __CLASS__, 'getToken' );
		return true; // Hooks must return bool
	}
}
