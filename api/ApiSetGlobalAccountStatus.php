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
		$globalUser = new CentralAuthUser( $this->getParameter( 'user' ) );
		if ( !$this->getUser()->isAllowed( 'centralauth-lock' ) ) {
			$this->dieUsageMsg( array( 'badaccess-groups' ) );
		} elseif ( !$globalUser->exists() ) {
			$this->dieUsageMsg( array( 'nosuchuser', $globalUser->getName() ) );
		} elseif ( !$this->getRequest()->getCheck( 'locked' ) && $this->getParameter( 'hidden' ) === null ) {
			$this->dieUsage( "At least one of the parameters locked, hidden is required", "missingparam" );
		}

		$setLocked = $this->getParameter( 'locked' ) == 'lock';
		$setHidden = $this->getParameter( 'hidden' );
		$reason = $this->getParameter( 'reason' );
		$isLocked = $globalUser->isLocked();
		$oldHiddenLevel = $globalUser->getHiddenLevel();

		if (
			$this->getParameter( 'hidden' ) !== null && // hidden is set
			$oldHiddenLevel != $setHidden && // it's not the same as the old hidden level
			( $setHidden == CentralAuthUser::HIDDEN_OVERSIGHT || $oldHiddenLevel == CentralAuthUser::HIDDEN_OVERSIGHT ) && // it involves suppressing/unsuppressing
			!$this->getUser()->isAllowed( 'centralauth-oversight' ) // but the user doesn't have the right - oops!
		) {
			$this->dieUsageMsg( array( 'badaccess-groups' ) );
		}

		$lockStatus = $hideStatus = null;
		$added = array();
		$removed = array();

		if ( !$isLocked && $setLocked ) {
			$lockStatus = $globalUser->adminLock();
			$added[] = $this->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		} elseif ( $this->getRequest()->getCheck( 'locked' ) && $isLocked && !$setLocked ) { // Check that 'locked' is actually set, so that hiding a locked user (without setting the locked parameter) doesn't unlock them.
			$lockStatus = $globalUser->adminUnlock();
			$removed[] = $this->msg( 'centralauth-log-status-locked' )->inContentLanguage()->text();
		}

		if ( $this->getParameter( 'hidden' ) !== null && $oldHiddenLevel != $setHidden ) {
			$hideStatus = $globalUser->adminSetHidden( $setHidden );
			switch ( $setHidden ) {
				case CentralAuthUser::HIDDEN_NONE:
					$removed[] = $oldHiddenLevel == CentralAuthUser::HIDDEN_OVERSIGHT ?
						$this->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text() :
						$this->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					break;
				case CentralAuthUser::HIDDEN_LISTS:
					$added[] = $this->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					if ( $oldHiddenLevel == CentralAuthUser::HIDDEN_OVERSIGHT )
						$removed[] = $this->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text();
					break;
				case CentralAuthUser::HIDDEN_OVERSIGHT:
					$added[] = $this->msg( 'centralauth-log-status-oversighted' )->inContentLanguage()->text();
					if ( $oldHiddenLevel == CentralAuthUser::HIDDEN_LISTS )
						$removed[] = $this->msg( 'centralauth-log-status-hidden' )->inContentLanguage()->text();
					break;
			}

			if ( $setHidden == CentralAuthUser::HIDDEN_OVERSIGHT ) {
				$globalUser->suppress( $reason );
			} elseif ( $oldHiddenLevel == CentralAuthUser::HIDDEN_OVERSIGHT ) {
				$globalUser->unsuppress( $reason );
			}
		}

		$good =
			( is_null( $lockStatus ) || $lockStatus->isGood() ) &&
			( is_null( $hideStatus ) || $hideStatus->isGood() );

		// Logging etc
		if ( $good && ( count( $added ) || count( $removed ) ) ) {
			$added = count( $added ) ?
				implode( ', ', $added ) : $this->msg( 'centralauth-log-status-none' )->inContentLanguage()->text();
			$removed = count( $removed ) ?
				implode( ', ', $removed ) : $this->msg( 'centralauth-log-status-none' )->inContentLanguage()->text();

			$sca = new SpecialCentralAuth;
			$sca->logAction(
				'setstatus',
				$this->getParameter( 'user' ),
				$reason,
				array( $added, $removed ),
				$setHidden == CentralAuthUser::HIDDEN_OVERSIGHT
			);
			$this->getResult()->addValue( null, $this->getModuleName(), array(
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $globalUser->getHiddenLevel(),
				'reason' => $reason
			) );
		} else {
			if ( !is_null( $lockStatus ) && !$lockStatus->isGood() ) {
				$this->getResult()->addValue(
					'error',
					null,
					$this->getResult()->convertStatusToArray( $lockStatus )
				);
			}
			if ( !is_null( $hideStatus ) && !$hideStatus->isGood() ) {
				$this->getResult()->addValue(
					'error',
					null,
					$this->getResult()->convertStatusToArray( $hideStatus )
				);
			}
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
					null // Unset - basically means 'do not modify lock status'
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
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}

	public function getParamDescription() {
		return array(
			'user' => 'User to change the status of.',
			'locked' => 'Change whether this user is locked or not.',
			'hidden' => 'Change whether this user is not hidden, hidden from lists, or suppressed.',
			'reason' => "Reason for changing the user's status.",
			'token' => 'Your edit token.'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'badaccess-groups' ),
			array( 'nosuchuser' ),
			array( 'missingparam' )
		) );
	}

	public function getDescription() {
		return "Set a global user's status.";
	}

	public function getExamples() {
		return array(
			'api.php?action=setglobalaccountstatus&user=Spammer&locked=true&hidden=&reason=Spam',
			'api.php?action=setglobalaccountstatus&user=Jimbo_Wales&locked=true&hidden=suppressed&reason=I%20can',
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'user' => array(
					ApiBase::PROP_TYPE => 'string'
				),
				'locked' => array(
					ApiBase::PROP_TYPE => 'boolean'
				),
				'hidden' => array(
					ApiBase::PROP_TYPE => array(
						CentralAuthUser::HIDDEN_NONE,
						CentralAuthUser::HIDDEN_LISTS,
						CentralAuthUser::HIDDEN_OVERSIGHT
					)
				),
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
