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
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

class RedirectingLoginHookHandler implements
	PostLoginRedirectHook,
	AuthPreserveQueryParamsHook,
	AuthChangeFormFieldsHook
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

	/**
	 * After a SUL3 login on the shared login domain, redirect the user back to the
	 * original wiki and indicate the result of the login.
	 * @inheritDoc
	 */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();
		if (
			!$this->sharedDomainUtils->isSharedDomain()
			|| !$this->sharedDomainUtils->isSul3Enabled( $request )
		) {
			return;
		}

		$returnUrlToken = $request->getRawVal( 'returnUrlToken' );
		if ( $returnUrlToken ) {
			$returnUrl = $this->caUtilityService->detokenize(
				$returnUrlToken,
				CentralAuthRedirectingPrimaryAuthenticationProvider::RETURN_URL_TOKEN_KEY_PREFIX,
				$this->sessionManager
			);
		} else {
			$returnUrl = false;
		}
		if ( !$returnUrl ) {
			throw new ErrorPageError( 'centralauth-error-badtoken', 'centralauth-error-badtoken' );
		}

		if ( !$context->getUser()->isRegistered() ) {
			throw new \LogicException( 'This account is not a registered user' );
		}

		$userToken = $this->caUtilityService->tokenize(
			$context->getUser()->getName(),
			self::LOGIN_CONTINUE_USERNAME_KEY_PREFIX,
			$this->sessionManager
		);
		$url = wfAppendQuery( $returnUrl, [ 'token' => $userToken ] );

		// We have a return URL from the login wiki, we're fine.
		$context->getOutput()->redirect( $url );
		$type = 'success';

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

	/**
	 * Move the redirect button below the normal login button. Displaying the normal login form
	 * is prevented elsewhere, but just in case, if that fails, avoid this button being the
	 * default form control that gets submitted when the user types into the username/password
	 * field and presses Enter.
	 * @inheritDoc
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$formFieldName = CentralAuthRedirectingPrimaryAuthenticationProvider::NON_LOGIN_WIKI_BUTTONREQUEST_NAME;
		if ( isset( $formDescriptor[$formFieldName] ) ) {
			$formDescriptor[$formFieldName]['weight'] = 101;
		}
	}
}
