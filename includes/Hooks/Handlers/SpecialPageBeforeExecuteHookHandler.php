<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

/**
 * Triggers a server-side (top-level) central autologin attempt on Special:Userlogin.
 */
class SpecialPageBeforeExecuteHookHandler implements SpecialPageBeforeExecuteHook {

	/**
	 * Name of the cookie that represents that top-level auto-login has been attempted.
	 * It is set before the initiation of autologin, cleared on success (or via expiry),
	 * and prevents further (or concurrent) attempts.
	 * See also ext.centralauth.centralautologin.clearcookie.js.
	 */
	public const AUTOLOGIN_TRIED_COOKIE = 'CentralAuthAnonTopLevel';

	/**
	 * Query parameter that marks that we have just returned from a top-level autologin attempt.
	 * Used to prevent redirect loops. Normally the cookie would do that, but the client might
	 * not record the cookie for some reason.
	 */
	private const AUTOLOGIN_TRIED_QUERY_PARAM = 'centralAuthAutologinTried';

	/** Query parameter used to return error messages from SpecialCentralAutoLogin. */
	public const AUTOLOGIN_ERROR_QUERY_PARAM = 'centralAuthError';

	private AuthManager $authManager;
	private Config $config;
	private CentralAuthTokenManager $tokenManager;
	private SharedDomainUtils $sharedDomainUtils;

	/**
	 * @param AuthManager $authManager
	 * @param Config $config
	 * @param CentralAuthTokenManager $tokenManager
	 * @param SharedDomainUtils $sharedDomainUtils
	 */
	public function __construct(
		AuthManager $authManager,
		Config $config,
		CentralAuthTokenManager $tokenManager,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->authManager = $authManager;
		$this->config = $config;
		$this->tokenManager = $tokenManager;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/**
	 * Triggers top-level central autologin attempt on Special:Userlogin, and handles the
	 * outcome of such an attempt at the end of the redirect chain.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 * @return bool
	 *
	 * @see SpecialCentralAutoLogin
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$amKey = 'AuthManagerSpecialPage:return:' . $special->getName();

		// Only attempt top-level autologin if the user is about to log in, the login isn't
		// already in progress, it is a normal login, and there is a central login wiki to use.
		// This check will also pass if the user just finished (successfully or not) the autologin.
		if ( $special->getName() !== 'Userlogin'
			|| $request->wasPosted()
			|| $subPage
			// Deal with the edge case where AuthManagerSpecialPage::handleReturnBeforeExecute()
			// redirects from Special:Userlogin/return, and the login session is the only thing
			// that gives away that we are in the middle of a remote login flow.
			|| $this->authManager->getAuthenticationSessionData( $amKey )
			// elevated-security reauthentication of already logged-in user
			|| $request->getBool( 'force' )
			|| $this->sharedDomainUtils->isSul3Enabled( $request )
			|| !$this->config->get( 'CentralAuthLoginWiki' )
			|| $this->config->get( 'CentralAuthLoginWiki' ) === WikiMap::getCurrentWikiId()
		) {
			return true;
		}

		$isMobile = CentralAuthHooks::isMobileDomain();

		// Do a top-level autologin if the user needs to log in. Use a cookie to prevent
		// unnecessary autologin attempts if we already know they will fail, and a query parameter
		// (to be set by SpecialCentralAutoLogin) to avoid an infinite loop even if we cannot set
		// cookies for some reason.
		if ( $special->getUser()->isAnon()
			 && !$request->getCookie( self::AUTOLOGIN_TRIED_COOKIE, '' )
			 && !$request->getCheck( self::AUTOLOGIN_TRIED_QUERY_PARAM )
		) {
			$url = WikiMap::getForeignURL(
				$this->config->get( 'CentralAuthLoginWiki' ),
				'Special:CentralAutoLogin/checkLoggedIn'
			);
			if ( $url === false ) {
				// WikiMap misconfigured?
				return true;
			}

			$this->log( 'Top-level autologin started', $special, $isMobile );

			$request->response()->setCookie(
				self::AUTOLOGIN_TRIED_COOKIE,
				'1',
				// use 1 day like anon-set.js
				time() + ExpirationAwareness::TTL_DAY,
				// match the behavior of the CentralAuthAnon cookie
				[ 'prefix' => '', 'httpOnly' => false ]
			);

			$returnUrl = wfAppendQuery( $request->getFullRequestURL(), [
				self::AUTOLOGIN_TRIED_QUERY_PARAM => 1,
			] );
			if ( CentralAuthHooks::isMobileDomain() ) {
				// WebRequest::getFullRequestURL() uses $wgServer, not the actual request
				// domain, but we do want to preserve that
				$returnUrl = MobileContext::singleton()->getMobileUrl( $returnUrl );
			}
			$returnUrlToken = $this->tokenManager->tokenize( $returnUrl, 'centralautologin-returnurl' );
			$url = wfAppendQuery( $url, [
				'type' => 'redirect',
				'returnUrlToken' => $returnUrlToken,
				'wikiid' => WikiMap::getCurrentWikiId(),
				'mobile' => $isMobile ? 1 : null,
			] );
			$special->getOutput()->redirect( $url );

			return false;
		}

		// Clean up after successful autologin.
		if ( $special->getUser()->isRegistered()
			&& $request->getCheck( self::AUTOLOGIN_TRIED_QUERY_PARAM )
		) {
			$this->log( 'Top-level autologin succeeded', $special, $isMobile );

			$request->response()->clearCookie( self::AUTOLOGIN_TRIED_COOKIE, [ 'prefix' => '' ] );
			// If returnto is set, let SpecialUserlogin redirect the user. If it is not set,
			// we would just show the login page, which would be confusing, so send the user away.
			if ( $request->getCheck( 'returnto' ) ) {
				return true;
			} else {
				$special->getOutput()->redirect( Title::newMainPage( $special->getContext() )->getLocalURL() );
				return false;
			}
		}

		// Log failed / prevented autologin.
		if ( $special->getUser()->isAnon()
			&& $request->getCheck( self::AUTOLOGIN_TRIED_QUERY_PARAM )
		) {
			$error = $request->getRawVal( self::AUTOLOGIN_ERROR_QUERY_PARAM, 'unknown error' );
			$this->log( "Top-level autologin failed: $error", $special, $isMobile );
		} elseif ( $special->getUser()->isAnon()
			&& $request->getCookie( self::AUTOLOGIN_TRIED_COOKIE, '' )
		) {
			$this->log( 'Top-level autologin prevented by cookie', $special, $isMobile );
		}
		// Else: the user is already logged in and manually visiting the login page, e.g.
		// to log in as another user. Nothing to do.

		return true;
	}

	private function log( string $message, SpecialPage $special, bool $isMobile ): void {
		$request = $special->getRequest();
		LoggerFactory::getInstance( 'CentralAuth' )->debug( $message, [
			'userAgent' => $request->getHeader( 'User-Agent' ),
			'isMobile' => $isMobile,
			'username' => $special->getUser()->isRegistered() ? $special->getUser()->getName() : '',
			'suggestedLoginUsername' => $request->getSession()->suggestLoginUsername(),
		] );
	}

}
