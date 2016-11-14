<?php
/**
 * Created on Aug 09, 2014
 *
 * CentralAuth extension
 *
 * Copyright (C) 2014 Brad Jorsch bjorsch@wikimedia.org
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
 * Module to fetch the centralauthtoken for cross-wiki queries.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiCentralAuthToken extends ApiBase {
	public function __construct( ApiMain $main, $moduleName ) {
		parent::__construct( $main, $moduleName );
	}

	public function execute() {
		$user = $this->getUser();
		$params = $this->extractRequestParams();

		// If we're in JSON callback mode, no tokens can be obtained
		if ( $this->lacksSameOriginSecurity() ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apiwarn-tokens-origin', 'hascallback' );
			} else {
				$this->dieUsage( 'Cannot obtain a centralauthtoken when using a callback', 'hascallback' );
			}
		}

		if ( $user->isAnon() ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apierror-centralauth-notloggedin', 'notloggedin' );
			} else {
				$this->dieUsage( 'Anonymous users cannot obtain a centralauthtoken', 'notloggedin' );
			}
		}

		$session = MediaWiki\Session\SessionManager::getGlobalSession();
		if ( !$session->getProvider() instanceof CentralAuthSessionProvider ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apierror-centralauth-badsession', 'badsession' );
			} else {
				$this->dieUsage(
					'Can only obtain a centralauthtoken when using CentralAuth sessions', 'badsession'
				);
			}
		}
		$id = $session->getId();

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apierror-centralauth-notattached', 'notattached' );
			} else {
				$this->dieUsage(
					'Cannot obtain a centralauthtoken without an attached global account', 'notattached'
				);
			}
		}

		$data = array(
			'userName' => $user->getName(),
			'token' => $centralUser->getAuthToken(),
			'origin' => wfWikiId(),
			'originSessionId' => $id,
		);

		$loginToken = MWCryptRand::generateHex( 32 ) . dechex( $centralUser->getId() );
		$key = CentralAuthUtils::memcKey( 'api-token', $loginToken );
		CentralAuthUtils::getSessionCache()->set( $key, $data, 60 );

		$this->getResult()->addValue( null, $this->getModuleName(), array(
			'centralauthtoken' => $loginToken
		) );
	}

	public function getAllowedParams() {
		return array(
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=centralauthtoken'
				=> 'apihelp-centralauthtoken-example-1',
		);
	}
}
