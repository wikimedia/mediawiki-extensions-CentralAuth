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
	/* Heavily based on code from SpecialCentralAuth::doSubmit */
	public function execute() {
		if ( !$this->getUser()->isAllowed( 'centralauth-unmerge' ) ) {
			$this->dieUsageMsg( array( 'badaccess-groups' ) );
		}

		$params = $this->extractRequestParams();
		$globalUser = new CentralAuthUser( $params['user'] );
		if ( !$globalUser->exists() ) {
			$this->dieUsageMsg( array( 'nosuchuser', $globalUser->getName() ) );
		}

		$status = $globalUser->adminDelete();
		if ( $status->isGood() ) {
			$sca = new SpecialCentralAuth;
			$sca->logAction( 'delete', $globalUser->getName(), $params['reason'] );
			$this->getResult()->addValue( null, $this->getModuleName(), array(
				'user' => $globalUser->getName(),
				'reason' => $params['reason']
			) );
		} else {
			$this->getResult()->convertStatusToArray( $status );
		}
	}

	public function getAllowedParams() {
		return array(
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'reason' => 'string',
			'token' => 'string'
		);
	}

	public function getParamDescription() {
		return array(
			'user' => 'User to delete.',
			'reason' => 'Reason for deleting the user.',
			'token' => 'Your edit token.'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'badaccess-groups' ),
			array( 'nosuchuser' )
		) );
	}

	public function getDescription() {
		return 'Delete a global user.';
	}

	public function getExamples() {
		return array(
			'api.php?action=deleteglobaluser&user=Name&reason=Because+I+can',
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'user' => 'string',
				'reason' => array(
					ApiBase::PROP_TYPE => 'string',
					ApiBase::PROP_NULLABLE => true
				)
			)
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
