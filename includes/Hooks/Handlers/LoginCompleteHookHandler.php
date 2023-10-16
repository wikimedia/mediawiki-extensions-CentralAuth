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

use Config;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralLogin;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\TempUserCreatedRedirectHook;
use MediaWiki\Hook\UserLoginCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Session\Session;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\WikiMap\WikiMap;
use MWCryptRand;
use RequestContext;
use User;
use WebRequest;

class LoginCompleteHookHandler implements
	UserLoginCompleteHook,
	TempUserCreatedRedirectHook
{
	/** @var Config */
	private $config;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/** @var CentralAuthHookRunner */
	private $caHookRunner;

	/**
	 * @param HookContainer $hookContainer
	 * @param Config $config
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param CentralAuthSessionManager $sessionManager
	 */
	public function __construct(
		HookContainer $hookContainer,
		Config $config,
		UserOptionsLookup $userOptionsLookup,
		CentralAuthSessionManager $sessionManager
	) {
		$this->caHookRunner = new CentralAuthHookRunner( $hookContainer );
		$this->config = $config;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->sessionManager = $sessionManager;
	}

	/**
	 * @param User $user
	 * @param string &$inject_html
	 * @param bool|null $direct Was this directly after a login? (see T140853)
	 * @return bool
	 *
	 * @see SpecialCentralLogin
	 */
	public function onUserLoginComplete( $user, &$inject_html, $direct = null ) {
		if ( !$this->config->get( 'CentralAuthCookies' ) ) {
			// Use local sessions only.
			return true;
		}

		if ( $direct === null ) { // B/C
			$direct = RequestContext::getMain()->getRequest()->wasPosted();
		}

		// Redirect to the central wiki and back to complete login, if necessary
		$centralUser = CentralAuthUser::getInstance( $user );
		$this->doCentralLoginRedirect( $user, $centralUser, $inject_html, $direct );

		return true;
	}

	/**
	 * Helper method that starts a central login redirect when it seems safe to do so.
	 * Otherwise, it triggers edge login on the next request.
	 *
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @param string &$inject_html
	 * @param bool $direct Was this directly after a login? (see T140853)
	 * @return bool
	 */
	protected function doCentralLoginRedirect(
		User $user,
		CentralAuthUser $centralUser,
		&$inject_html,
		$direct
	) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		if ( !$this->config->get( 'CentralAuthLoginWiki' ) || defined( 'MW_API' ) ) {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
			return true;
		}

		// Check that this is actually for a special login page view
		$title = $context->getTitle();
		if ( $direct && $title && ( $title->isSpecial( 'Userlogin' ) ||
			$title->isSpecial( 'CreateAccount' ) )
		) {
			// Determine the final protocol of page, after login
			if ( $this->config->get( 'ForceHTTPS' ) ) {
				$finalProto = 'https';
				$secureCookies = true;
			} else {
				$finalProto = WebRequest::detectProtocol();
				$secureCookies = ( $finalProto === 'https' );

				if ( $this->config->get( 'SecureLogin' ) ) {
					$finalProto = 'http';

					if ( $request->getBool( 'wpForceHttps', false ) ||
						$request->getSession()->shouldForceHTTPS() ||
						$this->userOptionsLookup->getBoolOption( $user, 'prefershttps' )
					) {
						$finalProto = 'https';
					}

					$secureCookies = ( ( $finalProto === 'https' ) &&
						$this->userOptionsLookup->getBoolOption( $user, 'prefershttps' ) );
				}
			}

			$redirectUrl = $this->getRedirectUrl(
				$request->getSession(),
				$centralUser,
				$request->getVal( 'returnto', '' ),
				$request->getVal( 'returntoquery', '' ),
				'',
				$title->isSpecial( 'CreateAccount' ) ? 'signup' : '',
				$secureCookies,
				$finalProto
			);
			$context->getOutput()->redirect( $redirectUrl );
			// Set $inject_html to some text to bypass the LoginForm redirection
			$inject_html .= '<!-- do CentralAuth redirect -->';
		} else {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
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
		if ( !$this->config->get( 'CentralAuthLoginWiki' ) ) {
			return true;
		}
		if ( $this->config->get( 'ForceHTTPS' ) ) {
			$finalProto = 'https';
			$secureCookies = true;
		} else {
			$finalProto = WebRequest::detectProtocol();
			$secureCookies = ( $finalProto === 'https' );
			$prefersHttps = $this->userOptionsLookup->getBoolOption( $user, 'prefershttps' );

			if ( $this->config->get( 'SecureLogin' ) ) {
				$finalProto = 'http';

				if ( $session->shouldForceHTTPS() || $prefersHttps ) {
					$finalProto = 'https';
				}

				$secureCookies = ( ( $finalProto === 'https' ) && $prefersHttps );
			}
		}
		$centralUser = CentralAuthUser::getInstance( $user );

		$redirectUrl = $this->getRedirectUrl(
			$session,
			$centralUser,
			$returnTo,
			$returnToQuery,
			$returnToAnchor,
			'signup',
			$secureCookies,
			$finalProto
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
	 * @param string $returnTo
	 * @param string $returnToQuery
	 * @param string $returnToAnchor
	 * @param string $loginType 'signup' or the empty string for normal login
	 * @param bool $secureCookies
	 * @param string $finalProto
	 * @return string
	 *
	 * @see SpecialCentralLogin
	 */
	private function getRedirectUrl(
		Session $session,
		CentralAuthUser $centralUser,
		$returnTo,
		$returnToQuery,
		$returnToAnchor,
		$loginType,
		$secureCookies,
		$finalProto
	) {
		// User will be redirected to Special:CentralLogin/start (central wiki),
		// then redirected back to Special:CentralLogin/complete (this wiki).
		// Sanity check that "returnto" is not one of the central login pages. If it
		// is, then clear the "returnto" options (LoginForm will use the main page).
		$returnToTitle = Title::newFromText( $returnTo );
		if ( $returnToTitle && $returnToTitle->isSpecial( 'CentralLogin' ) ) {
			$returnTo = '';
			$returnToQuery = '';
		}

		$remember = $session->shouldRememberUser();

		// When POSTs triggered from Special:CentralLogin/start are sent back to
		// this wiki, the token will be checked to see if it was signed with this.
		// This is needed as Special:CentralLogin/start only takes a token argument
		// and we need to make sure an agent requesting such a URL actually initiated
		// the login request that spawned that token server-side.
		$secret = MWCryptRand::generateHex( 32 );
		$session->set( 'CentralAuth:autologin:current-attempt', [
			'secret'	=> $secret,
			'remember'      => $remember,
			'returnTo'      => $returnTo,
			'returnToQuery' => $returnToQuery,
			'returnToAnchor' => $returnToAnchor,
			// cookies set secure or not (local CentralAuth cookies)
			'stickHTTPS'    => $secureCookies,
			'type'	  => $loginType
		] );

		// Create a new token to pass to Special:CentralLogin/start (central wiki)
		$tokenStore = $this->sessionManager->getTokenStore();
		$token = MWCryptRand::generateHex( 32 );
		$key = $this->sessionManager->memcKey( 'central-login-start-token', $token );
		$data = [
			'secret'	=> $secret,
			'name'	  => $centralUser->getName(),
			'guid'	  => $centralUser->getId(),
			'wikiId'	=> WikiMap::getCurrentWikiId(),
			'secureCookies' => $secureCookies, // (bool) cookies secure or not
		];
		$this->caHookRunner->onCentralAuthLoginRedirectData( $centralUser, $data );
		$tokenStore->set( $key, $data, $tokenStore::TTL_MINUTE );

		$query = [ 'token' => $token ];

		$wiki = WikiMap::getWiki( $this->config->get( 'CentralAuthLoginWiki' ) );
		// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
		// OutputPage::redirect() will expand it to PROTO_CURRENT
		return wfAppendQuery( $wiki->getFullUrl( 'Special:CentralLogin/start' ), $query );
	}
}
