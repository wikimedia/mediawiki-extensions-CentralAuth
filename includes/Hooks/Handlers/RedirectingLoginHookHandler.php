<?php
/**
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
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use ErrorPageError;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\MediaWikiServices;
use RuntimeException;

class RedirectingLoginHookHandler implements
	PostLoginRedirectHook,
	AuthPreserveQueryParamsHook
{
	/**
	 * @internal For use by CentralAuth only.
	 * @var string Storage key prefix for the token when continuing the login
	 *    in the local wiki.
	 */
	public const LOGIN_CONTINUE_USERNAME_KEY_PREFIX = 'centralauth-post-login-redirect-username';

	private CentralAuthSessionManager $sessionManager;
	private CentralAuthUtilityService $caUtilityService;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		CentralAuthSessionManager $sessionManager,
		CentralAuthUtilityService $utility,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->sessionManager = $sessionManager;
		$this->caUtilityService = $utility;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/** @inheritDoc */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		if ( !$this->sharedDomainUtils->isSharedDomain() ) {
			// We're not on the central login wiki, so do nothing.
			return;
		}

		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$url = $request->getRawVal( 'returnUrlToken' );

		if ( !$url ) {
			throw new ErrorPageError(
				'centralauth-error-badtoken', 'centralauth-error-badtoken'
			);
		}

		if ( !$context->getUser()->isRegistered() ) {
			throw new \LogicException( 'This account is not a registered user' );
		}

		$returnUrlToken = $request->getRawVal( 'returnUrlToken' );

		if ( $this->sharedDomainUtils->isSul3Enabled( $request ) ) {
			$token = $this->caUtilityService->tokenize(
				$context->getUser()->getName(),
				self::LOGIN_CONTINUE_USERNAME_KEY_PREFIX,
				$this->sessionManager
			);
			$url = wfAppendQuery(
				$this->caUtilityService->detokenize(
					$returnUrlToken,
					CentralAuthRedirectingPrimaryAuthenticationProvider::RETURN_URL_TOKEN_KEY_PREFIX,
					MediaWikiServices::getInstance()->getService(
						'CentralAuth.CentralAuthSessionManager'
					)
				),
				[ 'token' => $token ]
			);

			// We have a return URL from the login wiki, we're fine.
			$context->getOutput()->redirect( $url );
			$type = 'success';
		} else {
			// This should not happen as the authentication workflow should know where
			// to go back to in order to complete the login process.
			throw new RuntimeException( 'Invalid return URL, login aborted' );
		}

		return true;
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		$params += [
			'returnUrlToken' => $request->getRawVal( 'returnUrlToken' ),
			'wikiid' => $request->getRawVal( 'wikiid' ),
			'usesul3' => $request->getRawVal( 'usesul3' ),
		];
	}
}
