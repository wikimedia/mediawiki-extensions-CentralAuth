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
use LogicException;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

/**
 * Hook handler for hooks related to SUL3 login.
 *
 * @see CentralAuthRedirectingPrimaryAuthenticationProvider
 */
class RedirectingLoginHookHandler implements
	PostLoginRedirectHook,
	AuthPreserveQueryParamsHook,
	AuthChangeFormFieldsHook
{
	private CentralAuthTokenManager $tokenManager;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		CentralAuthTokenManager $tokenManager,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->tokenManager = $tokenManager;
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

		$token = $request->getRawVal( 'centralauthLoginToken' );
		$inputData = null;
		if ( $token ) {
			$inputData = $this->tokenManager->detokenizeAndDelete(
				$token,
				CentralAuthRedirectingPrimaryAuthenticationProvider::START_TOKEN_KEY_PREFIX
			);
		}
		if ( !$token || !$inputData ) {
			throw new ErrorPageError( 'centralauth-error-badtoken', 'centralauth-error-badtoken' );
		}
		$returnUrl = $inputData['returnUrl'];

		if ( !$context->getUser()->isRegistered() ) {
			throw new LogicException( 'Unregistered user at end of login' );
		}
		$centralUser = CentralAuthUser::getInstanceByName( $context->getUser()->getName() );
		if ( !$centralUser->exists() ) {
			$centralUser = CentralAuthUser::getPrimaryInstanceByName( $context->getUser()->getName() );
		}
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			throw new LogicException( 'Unattached user at end of login' );
		}

		$outputData = $inputData + [
			'username' => $centralUser->getName(),
			'userId' => $centralUser->getId(),
		];
		$token = $this->tokenManager->tokenize(
			$outputData,
			CentralAuthRedirectingPrimaryAuthenticationProvider::COMPLETE_TOKEN_KEY_PREFIX
		);

		// We have a return URL from the login wiki, we're fine.
		$url = wfAppendQuery( $returnUrl, [ 'centralauthLoginToken' => $token ] );
		$context->getOutput()->redirect( $url );
		$type = 'success';

		return true;
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		$params += [
			'centralauthLoginToken' => $request->getRawVal( 'centralauthLoginToken' ),
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
