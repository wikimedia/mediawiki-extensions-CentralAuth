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

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use ApiMain;
use CentralAuthSessionProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Session\SessionManager;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;

/**
 * Module to fetch the centralauthtoken for cross-wiki queries.
 *
 * @ingroup API
 * @ingroup Extensions
 * @see \MediaWiki\Extension\CentralAuth\Hooks\Handlers\ApiHookHandler::onAPIGetAllowedParams
 * @see \CentralAuthApiSessionProvider
 * @see \CentralAuthHeaderSessionProvider
 */
class ApiCentralAuthToken extends ApiBase {
	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @param CentralAuthSessionManager $sessionManager
	 */
	public function __construct(
		ApiMain $main,
		$moduleName,
		CentralAuthSessionManager $sessionManager
	) {
		parent::__construct( $main, $moduleName );
		$this->sessionManager = $sessionManager;
	}

	public function execute() {
		$user = $this->getUser();

		// If we're in JSON callback mode, no tokens can be obtained
		if ( $this->lacksSameOriginSecurity() ) {
			$this->dieWithError( 'apiwarn-tokens-origin', 'hascallback' );
		}

		if ( !$user->isRegistered() ) {
			$this->dieWithError( 'apierror-centralauth-notloggedin', 'notloggedin' );
		}

		$session = SessionManager::getGlobalSession();
		if ( !$session->getProvider() instanceof CentralAuthSessionProvider ) {
			$this->dieWithError( 'apierror-centralauth-badsession', 'badsession' );
		}
		$id = $session->getId();

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			$this->dieWithError( 'apierror-centralauth-notattached', 'notattached' );
		}

		$data = [
			'userName' => $user->getName(),
			'token' => $centralUser->getAuthToken(),
			'origin' => WikiMap::getCurrentWikiId(),
			'originSessionId' => $id,
		];

		$loginToken = MWCryptRand::generateHex( 32 ) . dechex( $centralUser->getId() );

		$key = $this->sessionManager->makeTokenKey( 'api-token', $loginToken );
		$this->sessionManager->getTokenStore()->set(
			$key, $data, $this->sessionManager->getTokenStore()::TTL_MINUTE
		);

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'centralauthtoken' => $loginToken
		] );
	}

	public function getAllowedParams() {
		return [
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=centralauthtoken'
				=> 'apihelp-centralauthtoken-example-1',
		];
	}
}
