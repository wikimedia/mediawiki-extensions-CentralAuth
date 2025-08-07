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

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralLogin;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\TempUserCreatedRedirectHook;
use MediaWiki\Hook\UserLoginCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Session\Session;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;

class LoginCompleteHookHandler implements
	UserLoginCompleteHook,
	TempUserCreatedRedirectHook
{

	private Config $config;
	private CentralAuthTokenManager $tokenManager;
	private CentralAuthHookRunner $caHookRunner;
	private CentralDomainUtils $centralDomainUtils;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		HookContainer $hookContainer,
		Config $config,
		CentralAuthTokenManager $tokenManager,
		CentralDomainUtils $centralDomainUtils,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->caHookRunner = new CentralAuthHookRunner( $hookContainer );
		$this->config = $config;
		$this->tokenManager = $tokenManager;
		$this->centralDomainUtils = $centralDomainUtils;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/**
	 * Start a central login redirect when it seems safe to do so.
	 * Otherwise, trigger edge login on the next request.
	 *
	 * @param User $user
	 * @param string &$inject_html
	 * @param bool|null $direct Was this directly after a login? (see T140853)
	 * @return bool
	 *
	 * @see SpecialCentralLogin
	 */
	public function onUserLoginComplete( $user, &$inject_html, $direct = null ) {
		if ( !$this->config->get( CAMainConfigNames::CentralAuthCookies )
			|| !$this->centralDomainUtils->centralDomainExists( $user->getRequest() )
			|| $this->sharedDomainUtils->isSul3Enabled( RequestContext::getMain()->getRequest() )
			|| $this->sharedDomainUtils->isSharedDomain()
		) {
			// Use local sessions only.
			return true;
		}

		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		$centralUser = CentralAuthUser::getInstance( $user );

		$context = RequestContext::getMain();
		$request = $context->getRequest();

		// Check that this is actually for a special login page view
		$title = $context->getTitle();
		if ( $title && ( $title->isSpecial( 'Userlogin' ) ||
			$title->isSpecial( 'CreateAccount' ) )
		) {
			$logger->debug( 'CentralLogin triggered in UserLoginComplete' );
			$redirectUrl = $this->getRedirectUrl(
				$request->getSession(),
				$centralUser,
				[
					'returnto' => $request->getVal( 'returnto', '' ),
					'returntoquery' => $request->getVal( 'returntoquery', '' ),
					'returntoanchor' => '',
					'loginType' => $title->isSpecial( 'CreateAccount' ) ? 'signup' : '',
				]
			);
			$context->getOutput()->redirect( $redirectUrl );
			// Set $inject_html to some text to bypass the LoginForm redirection
			$inject_html .= '<!-- do CentralAuth redirect -->';
		}

		return true;
	}

	/**
	 * Initiate a central login redirect that sets up the central session for the temp user,
	 * then returns.
	 *
	 * @param Session $session
	 * @param UserIdentity $user
	 * @param string $returnTo
	 * @param string $returnToQuery
	 * @param string $returnToAnchor
	 * @param string &$redirectUrl
	 * @return bool
	 *
	 * @see SpecialCentralLogin
	 */
	public function onTempUserCreatedRedirect(
		Session $session,
		UserIdentity $user,
		string $returnTo,
		string $returnToQuery,
		string $returnToAnchor,
		&$redirectUrl
	) {
		if ( !$this->centralDomainUtils->centralDomainExists( $session->getRequest() ) ) {
			return true;
		}

		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );

		$logger->debug( 'CentralLogin triggered in TempUserCreatedRedirect' );
		$redirectUrl = $this->getRedirectUrl(
			$session,
			$centralUser,
			[
				'returnto' => $returnTo,
				'returntoquery' => $returnToQuery,
				'returntoanchor' => $returnToAnchor,
				'loginType' => 'signup',
			],
			false,
		);
		return false;
	}

	/**
	 * Sets up central login so the caller can start it.
	 * - Stores a random-generated login secret, along with generic information about the
	 *   user and the returnTo target, in the local session.
	 * - Composes an URL to the next step of the central login, Special:CentralLogin/start, and
	 *   uses the token store and a query parameter in the URL to pass the secret, and information
	 *   about the user and the session, in a secure way.
	 * - Returns the redirect URL.
	 *
	 * @param Session $session
	 * @param CentralAuthUser $centralUser
	 * @param array $queryParams Various parameters to use in the redirect URL like
	 *     - (string) returnto: where to return to after the redirect
	 *     - (string) returntoquery: other URL query parameters
	 *     - (string) returntoanchor
	 *     - (string) loginType: authentication type e.g. 'signup' or the empty
	 *       string for normal login
	 * @param bool $excludeApiRequest
	 *
	 * @return string
	 *
	 * @see SpecialCentralLogin
	 */
	private function getRedirectUrl(
		Session $session,
		CentralAuthUser $centralUser,
		array $queryParams,
		bool $excludeApiRequest = true
	) {
		// User will be redirected to Special:CentralLogin/start (central wiki),
		// then redirected back to Special:CentralLogin/complete (this wiki).
		// Sanity check that "returnto" is not one of the central login pages. If it
		// is, then clear the "returnto" options (LoginForm will use the main page).
		$returnToTitle = Title::newFromText( $queryParams['returnto'] );
		if ( $returnToTitle && $returnToTitle->isSpecial( 'CentralLogin' ) ) {
			$queryParams['returnto'] = '';
			$queryParams['returntoquery'] = '';
		}

		$remember = $session->shouldRememberUser();

		// When POSTs triggered from Special:CentralLogin/start are sent back to
		// this wiki, the token will be checked to see if it was signed with this.
		// This is needed as Special:CentralLogin/start only takes a token argument
		// and we need to make sure an agent requesting such a URL actually initiated
		// the login request that spawned that token server-side.
		$secret = MWCryptRand::generateHex( 32 );
		$session->set( 'CentralAuth:autologin:current-attempt', [
			'secret' => $secret,
			'remember' => $remember,
			'returnTo' => $queryParams['returnto'],
			'returnToQuery' => $queryParams['returntoquery'],
			'returnToAnchor' => $queryParams['returntoanchor'],
			'type' => $queryParams['loginType']
		] );

		// Create a new token to pass to Special:CentralLogin/start (central wiki)
		$data = [
			'secret' => $secret,
			'name' => $centralUser->getName(),
			'guid' => $centralUser->getId(),
			'wikiId' => WikiMap::getCurrentWikiId(),
		];
		$this->caHookRunner->onCentralAuthLoginRedirectData( $centralUser, $data );
		$token = $this->tokenManager->tokenize( $data, 'central-login-start-token' );

		$query = [
			'token' => $token,
			'usesul3' => $this->sharedDomainUtils->isSul3Enabled( $session->getRequest(), $excludeApiRequest ) ? 1 : 0,
		];
		$url = $this->centralDomainUtils->getUrl(
			CentralDomainUtils::CENTRAL_DOMAIN_ID,
			'Special:CentralLogin/start',
			$session->getRequest()
		);

		return wfAppendQuery( $url, $query );
	}
}
