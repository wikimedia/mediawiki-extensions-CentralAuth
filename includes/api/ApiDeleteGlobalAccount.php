<?php
/**
 * Created on Oct 21, 2012
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
 * API module to delete a global account.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiDeleteGlobalAccount extends ApiBase {
	public function execute() {
		// Heavily based on code from SpecialCentralAuth::doSubmit
		$this->checkUserRightsAny( 'centralauth-unmerge' );

		// T194232
		if ( !CentralAuthUtils::isPermittedGlobalActionWiki() ) {
			$this->dieWithError( 'centralauth-globalactiondenied' );
		}

		$params = $this->extractRequestParams();

		$globalUser = CentralAuthUser::getMasterInstanceByName( $params['user'] );
		if ( !$globalUser->exists() ||
			$globalUser->isOversighted() && !$this->getUser()->isAllowed( 'centralauth-oversight' )
		) {
			$this->dieWithError( [ 'nosuchusershort', wfEscapeWikitext( $globalUser->getName() ) ] );

		}

		$status = $globalUser->adminDelete( $params['reason'] );
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

	public function getAllowedParams() {
		return [
			'user' => [
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
			'action=deleteglobalaccount&user=Example&reason=Because+I+can'
				=> 'apihelp-deleteglobalaccount-example-1',
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'deleteglobalaccount';
	}

	public static function getToken() {
		global $wgUser;
		if ( !$wgUser->isAllowed( 'centralauth-unmerge' ) ) {
			return false;
		}

		return $wgUser->getEditToken( 'deleteglobalaccount' );
	}

	public static function injectTokenFunction( &$list ) {
		$list['deleteglobalaccount'] = [ __CLASS__, 'getToken' ];
		return true; // Hooks must return bool
	}
}
