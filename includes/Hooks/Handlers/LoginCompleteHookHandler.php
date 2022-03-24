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
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\UserLoginCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\UserOptionsLookup;
use MWCryptRand;
use RequestContext;
use Title;
use User;
use WebRequest;
use WikiMap;

class LoginCompleteHookHandler implements
	UserLoginCompleteHook
{
	/** @var Config */
	private $config;

	/** @var CentralAuthSessionManager */
	private $sessionManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var CentralAuthHookRunner */
	private $caHookRunner;

	/**
	 * @param Config $config
	 * @param CentralAuthSessionManager $sessionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		Config $config,
		CentralAuthSessionManager $sessionManager,
		UserOptionsLookup $userOptionsLookup,
		HookContainer $hookContainer
	) {
		$this->config = $config;
		$this->sessionManager = $sessionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->caHookRunner = new CentralAuthHookRunner( $hookContainer );
	}

	/**
	 * @param User $user
	 * @param string &$inject_html
	 * @param bool|null $direct Was this directly after a login? (see T140853)
	 * @return bool
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
			// User will be redirected to Special:CentralLogin/start (central wiki),
			// then redirected back to Special:CentralLogin/complete (this wiki).
			// Sanity check that "returnto" is not one of the central login pages. If it
			// is, then clear the "returnto" options (LoginForm will use the main page).
			$returnTo = $request->getVal( 'returnto', '' );
			$returnToQuery = $request->getVal( 'returntoquery', '' );
			$returnToTitle = Title::newFromText( $returnTo );
			if ( $returnToTitle && $returnToTitle->isSpecial( 'CentralLogin' ) ) {
				$returnTo = '';
				$returnToQuery = '';
			}

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

			$remember = $request->getSession()->shouldRememberUser();
			$type = $title->isSpecial( 'CreateAccount' ) ? 'signup' : '';

			// When POSTs triggered from Special:CentralLogin/start are sent back to
			// this wiki, the token will be checked to see if it was signed with this.
			// This is needed as Special:CentralLogin/start only takes a token argument
			// and we need to make sure an agent requesting such a URL actually initiated
			// the login request that spawned that token server-side.
			$secret = MWCryptRand::generateHex( 32 );
			$request->setSessionData( 'CentralAuth:autologin:current-attempt', [
				'secret'	=> $secret,
				'remember'      => $remember,
				'returnTo'      => $returnTo,
				'returnToQuery' => $returnToQuery,
				// cookies set secure or not (local CentralAuth cookies)
				'stickHTTPS'    => $secureCookies,
				'finalProto'    => $finalProto, // final page http or https
				'type'	  => $type
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
				'finalProto'    => $finalProto, // http or https for very final page
				// current proto (in case login is https, but final page is http)
				'currentProto'  => WebRequest::detectProtocol()
			];

			$this->caHookRunner->onCentralAuthLoginRedirectData( $centralUser, $data );

			$tokenStore->set( $key, $data, $tokenStore::TTL_MINUTE );

			$query = [ 'token' => $token ];

			$wiki = WikiMap::getWiki( $this->config->get( 'CentralAuthLoginWiki' ) );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$context->getOutput()->redirect( // expands to PROTO_CURRENT
				wfAppendQuery( $wiki->getFullUrl( 'Special:CentralLogin/start' ), $query )
			);
			// Set $inject_html to some text to bypass the LoginForm redirection
			$inject_html .= '<!-- do CentralAuth redirect -->';
		} else {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
		}

		return true;
	}
}
