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

use LogicException;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Auth\Hook\SecuritySensitiveOperationStatusHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\User\User;

/**
 * Hook handler for hooks related to SUL3 login.
 *
 * @see CentralAuthRedirectingPrimaryAuthenticationProvider
 */
class RedirectingLoginHookHandler implements
	PostLoginRedirectHook,
	SecuritySensitiveOperationStatusHook,
	AuthPreserveQueryParamsHook,
	AuthChangeFormFieldsHook
{

	public const SUPPRESS_HOOK_SESSION_FLAG = 'CentralAuth-suppressAuthManagerLoginAuthenticateAudit';
	public const SECURITY_OP = 'local';

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
			// There are two kinds of security reauthentications:
			// 1. the user goes to a credential change page, gets redirected to the shared domain
			//    and then to security reauthentication there. After successful login, they need
			//    to return to the credential change special page on the shared domain, ie. normal
			//    core 'returnto' handling.
			// 2. The user goes to some other page requiring elevated security, gets redirected to
			//    local login and then the redirecting primary provider sends them to the shared
			//    domain. They do need to be sent back by this hook. We still need to force the
			//    login page to appear in this case (because of T389010) so we have
			//    CentralAuthRedirectingPrimaryAuthenticationProvider set 'force' to a special
			//    value which we can ignore here.
			|| (
				$request->getBool( 'force' )
				&& $request->getRawVal( 'force' ) !== self::SECURITY_OP
			)
		) {
			return true;
		}

		$token = $request->getRawVal( 'centralauthLoginToken' );
		if ( !$token ) {
			return true;
		}

		$inputData = $this->tokenManager->detokenizeAndDelete(
			$token,
			CentralAuthRedirectingPrimaryAuthenticationProvider::START_TOKEN_KEY_PREFIX
		);
		if ( !$inputData ) {
			LoggerFactory::getInstance( 'authevents' )
				->warning( 'Authentication request with bad token', [
					'event' => ( $type === 'signup' ) ? 'accountcreation' : 'login',
					'successful' => false,
					'extension' => 'CentralAuth',
					'accountType' => $context->getUser()->isNamed() ? 'named' : 'temp',
					'status' => 'badtoken'
				] );
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

		$lastAuthTimestamp = $request->getSession()->get( 'AuthManager:lastAuthTimestamp', 0 );
		$outputData = $inputData + [
			'username' => $centralUser->getName(),
			'userId' => $centralUser->getId(),
			'rememberMe' => $request->getSession()->shouldRememberUser(),
			'isSignup' => $type === 'signup',
			// There isn't really a way to tell whether PostLoginRedirect was called after a
			// successful login or an already-logged-in visit to the login page, but checking for
			// a recent login is good enough.
			'loginWasInteractive' => ( time() - $lastAuthTimestamp ) < 10,
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

	/** @inheritDoc */
	public function onSecuritySensitiveOperationStatus( &$status, $operation, $session, $timeSinceAuth ) {
		if (
			$operation === self::SECURITY_OP
			&& $this->sharedDomainUtils->isSharedDomain()
		) {
			$status = AuthManager::SEC_REAUTH;
		}
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		$params += [
			// used to prevent hijacking attacks when switching between the domains,
			// needs to round-trip to be verified
			'centralauthLoginToken' => $request->getRawVal( 'centralauthLoginToken' ),
			// keep opt-in state consistent during an authentication flow
			'usesul3' => $request->getRawVal( 'usesul3' ),
			// keep choice of desktop/mobile view consistent during an authentication flow
			'useformat' => $request->getRawVal( 'useformat' ),
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

	/**
	 * Prevent other AuthManagerLoginAuthenticateAudit hooks from running twice.
	 *
	 * Called when we are continuing SUL3 authentication locally, after having returned from the
	 * central domain. The hook has already run on the central domain, it breaks the assumptions of
	 * some handlers if it gets called again.
	 *
	 * FIXME this is a messy hack, but there didn't seem to be a cleaner way.
	 *
	 * @param AuthenticationResponse $response Response in either a PASS or FAIL state
	 * @param User|null $user User being authenticated against, or null if authentication
	 *   failed before getting that far
	 * @param string|null $username A guess at the username being authenticated, or null if we can't
	 *   even determine that. When $user is not null, it can be in the form of
	 *   <username>@<more info> (e.g. for bot passwords).
	 * @param string[] $extraData Array (string => string) with extra information, intended to be
	 *   added to log contexts. Fields it might include:
	 *   - appId: application ID, only if the login was with a bot password
	 *   - performer: the user performing the login authentication request
	 * @return bool|void True or no return value to continue or false to abort
	 *
	 * @see CentralAuthHooks::onRegistration()
	 * @see CentralAuthRedirectingPrimaryAuthenticationProvider::continuePrimaryAuthentication()
	 */
	public static function onAuthManagerLoginAuthenticateAudit( $response, $user, $username, $extraData ) {
		// The session seems like the least bad way of communicating between the primary authentication
		// provider and this hook. The hook has to be a static method since that's the only way to
		// register it early enough; using a static variable would be troublesome for tests; the
		// authentication session (as in AuthManager::getAuthenticationSessionData()) is already
		// removed by this point; none of the parameters of this hook are easy to attach information to.
		// So we'll have to use the request context.
		$session = RequestContext::getMain()->getRequest()->getSession();
		if ( $session->get( self::SUPPRESS_HOOK_SESSION_FLAG ) ) {
			return false;
		}
	}
}
