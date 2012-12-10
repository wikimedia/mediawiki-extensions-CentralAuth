<?php
/**
 * Created on Dec 10, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Chris Steipp
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
 * Module to lock and optionally hide/suppress global user accounts
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiLockGlobalUser extends ApiBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'gui' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$user = $this->getUser();

		// Special:CentralAuth converts _ to ' ', and ucfirsts. But if you're using the api,
		// we assume you have the correct name.
		$globalUser = new CentralAuthUser( $params['user'] );

		if ( !$globalUser->exists() ||
			( $globalUser->isOversighted() && !$user->isAllowed( 'centralauth-oversight' ) ) ) {
			$this->dieUsageMsg( 'centralauth-admin-nonexistent' );
		}

		if ( $params['lock'] == false && $params['hide'] == false ) {
			$this->dieUsageMsg( 'centralauth-api-no-action' );
		}

		// Copying permissions from Special:CentralAuth
		// * Allowed to lock, and hide from lists with 'centralauth-lock'
		// * Allowed to suppress with 'centralauth-oversight'
		if ( ( $params['lock'] != false && !$user->isAllowed( 'centralauth-lock' ) )
			|| ( $params['hide'] != false && !$user->isAllowed( 'centralauth-lock' ) )
			|| ( $params['hide'] == CentralAuthUser::HIDDEN_OVERSIGHT && !$user->isAllowed( 'centralauth-oversight' ) )
		) {
			$this->dieUsageMsg( 'centralauth-not-authorized' );
		}

		if ( $params['lock'] == false ) {
			$params['lock'] = ( $globalUser->isLocked() ? 'lock' : 'unlock' );
		}

		if ( $params['hide'] == false ) {
			$params['hide'] = $globalUser->getHiddenLevel();
		} elseif ( $params['hide'] == 'none' ) {
			$params['hide'] = CentralAuthUser::HIDDEN_NONE;
		}

		$setLocked = ( $params['lock'] == 'lock' );
		$setHidden = $params['hide'];
		$reason = $params['reason'];

		$status = $globalUser->adminLockHide( $setLocked, $setHidden, $reason, $this->getContext() );

		if ( $status->isGood() && $status->successCount > 0 ) {
			SpecialCentralAuth::logAction(
				'setstatus',
				$globalUser->getName(),
				$reason,
				$status->success,
				$setHidden == CentralAuthUser::HIDDEN_OVERSIGHT
			);

			$res = array(
				'added' => $status->success['added'],
				'removed' => $status->success['removed'],
			);

			$this->getResult()->addValue( null, $this->getModuleName(), $res );

		} elseif ( !$status->isGood() ) {
			$res = array( 'error' => $status->getWikiText() );
			$this->getResult()->addValue( null, $this->getModuleName(), $res );
		}

	}

	public function getAllowedParams() {
		return array(
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'lock' => array(
				ApiBase::PARAM_TYPE => array(
					'lock',
					'unlock',
					false,
				),
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_DFLT => false,
			),
			'hide' => array(
				ApiBase::PARAM_TYPE => array(
					'none', #Can't use CentralAuthUser::HIDDEN_NONE, since ''==false
					CentralAuthUser::HIDDEN_LISTS,
					CentralAuthUser::HIDDEN_OVERSIGHT,
					false,
				),
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_DFLT => false,
			),
			'reason' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'token' => null,
		);
	}

	public static function getToken() {
		global $wgUser;
		return $wgUser->getEditToken( 'globalblock' );
	}

	public static function injectTokenFunction( &$list ) {
		$list['globallock'] = array( __CLASS__, 'getToken' );
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return 'globalblock';
	}


	public function getParamDescription() {
		return array(
			'user' => 'CentralAuth Username to take action on. Cannot be the current user.',
			'lock' => 'Set the username\'s lock state to "lock" or "unlock"',
			'hide' => 'Set the level to hide this user',
			'reason' => 'Reason for taking this action',
		);
	}

	public function getDescription() {
		return 'Lock and/or Hide a global user.';
	}

	public function getExamples() {
		return array(
			'api.php?action=globallock&token=1234&guiuser=CSteipp&guilock=lock&guireason=My%20Reason',
			'api.php?action=globallock&token=1234&guiuser=CSteipp&guilock=lock&guihide=lists&guireason=My%20Reason',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

}
