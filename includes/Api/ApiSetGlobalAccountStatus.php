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
	/** @var array<string, int> Mapping for string hidden values to the new int "level" values. */
	private const HIDDEN_LEVEL_MAPPING = [
		''           => CentralAuthUser::HIDDEN_LEVEL_NONE,
		'lists'      => CentralAuthUser::HIDDEN_LEVEL_LISTS,
		'suppressed' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
	];

	/** @var array<int, string> Mapping for new int "level" hidden values to the string values. */
	private const HIDDEN_LEVEL_MAPPING_REVERSE = [
		CentralAuthUser::HIDDEN_LEVEL_NONE       => '',
		CentralAuthUser::HIDDEN_LEVEL_LISTS      => 'lists',
		CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED => 'suppressed',
	];

	public function execute() {
		// Heavily based on code from SpecialCentralAuth::doSubmit
		$params = $this->extractRequestParams();
		$this->requireAtLeastOneParameter( $params, 'locked', 'hidden' );

		$this->checkUserRightsAny( 'centralauth-lock' );

		$globalUser = CentralAuthUser::getPrimaryInstanceByName( $params['user'] );
		if ( !$globalUser->exists() ||
			( $globalUser->isSuppressed() && !$this->getAuthority()->isAllowed( 'centralauth-suppress' ) )
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

		if ( $params['hidden'] == null ) {
			$setHidden = null;
		} else {
			$setHidden = self::HIDDEN_LEVEL_MAPPING[$params['hidden']];
		}

		$reason = $params['reason'];
		$stateCheck = $params['statecheck'];

		if ( $stateCheck && $stateCheck !== $this->getStateHash( $globalUser ) ) {
			$this->dieWithError( 'apierror-centralauth-editconflict', 'editconflict' );
		}

		$status = $globalUser->adminLockHide(
			$setLocked,
			$setHidden,
			$reason,
			$this->getContext()
		);

		$hidden = self::HIDDEN_LEVEL_MAPPING_REVERSE[$globalUser->getHiddenLevelInt()];

		// Logging etc
		if ( $status->isGood() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $hidden,
				'reason' => $reason
			] );
		} else {
			$error = $this->getErrorFormatter()->arrayFromStatus( $status );
			$this->getResult()->addValue( 'error', null, $error );
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'user' => $globalUser->getName(),
				'locked' => $globalUser->isLocked(),
				'hidden' => $hidden,
			] );
		}
	}

	/**
	 * Calculates a state hash for edit conflict detection. This is separate
	 * from {@link CentralAuthUser::getStateHash()} as it uses the 'old'
	 * (non-normalized string) values for the hidden level value.
	 *
	 * @param CentralAuthUser $user
	 * @return string
	 */
	private function getStateHash( CentralAuthUser $user ): string {
		$parts = [
			(string)$user->getId(),
			$user->getName(),
			self::HIDDEN_LEVEL_MAPPING_REVERSE[$user->getHiddenLevelInt()],
			$user->isLocked() ? '1' : '0'
		];

		return md5( implode( ':', $parts ) );
	}

	/** @inheritDoc */
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
					// Unset - basically means 'do not modify lock status'
					''
				]
			],
			'hidden' => [
				ParamValidator::PARAM_TYPE => [
					'',
					'lists',
					'suppressed'
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
		return 'setglobalaccountstatus';
	}
}
