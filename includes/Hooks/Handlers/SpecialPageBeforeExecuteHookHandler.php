<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\ResourceLoader\ClientHtml;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

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
	private HookRunner $hookRunner;
	private CentralAuthTokenManager $tokenManager;
	private CentralDomainUtils $centralDomainUtils;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		AuthManager $authManager,
		HookContainer $hookContainer,
		CentralAuthTokenManager $tokenManager,
		CentralDomainUtils $centralDomainUtils,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->authManager = $authManager;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->tokenManager = $tokenManager;
		$this->centralDomainUtils = $centralDomainUtils;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/**
	 * Triggers top-level central autologin attempt on Special:Userlogin, and handles the
	 * outcome of such an attempt at the end of the redirect chain.
	 *
	 * @inheritDoc
	 *
	 * @see SpecialCentralAutoLogin
	 * @see SharedDomainHookHandler::onSpecialPageBeforeExecute()
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$amKey = 'AuthManagerSpecialPage:return:' . $special->getName();

		// default assumption is that we're not doing SUL3
		$isSul3Enabled = false;
		if ( !$this->sharedDomainUtils->isSharedDomain()
			&& ( $special->getName() === 'CreateAccount' || $special->getName() === 'Userlogin' )
		) {
			$isSul3Enabled = $this->sharedDomainUtils->isSul3Enabled( $request );
		}

		// In SUL3 mode, account creation is seen locally as a login, so redirect
		// there. The 'sul3-action' flag will ensure that the user ends up on the
		// account creation page once on the central domain.
		if ( $special->getName() === 'CreateAccount'
			&& $isSul3Enabled
			&& !$this->sharedDomainUtils->isSharedDomain()
			// The isNamed() case is handled in SharedDomainHookHandler::onSpecialPageBeforeExecute()
			&& !$special->getUser()->isNamed()
		) {
			$localLoginUrl = SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL();
			$params = [];
			$this->hookRunner->onAuthPreserveQueryParams( $params, [ 'request' => $request ] );
			// replicate the non-hook part of LoginSignupSpecialPage::getPreservedParams()
			$params += [
				'display' => $request->getRawVal( 'display' ),
				'uselang' => $request->getRawVal( 'uselang' ),
				'variant' => $request->getRawVal( 'variant' ),
				// Mask these parameters, so that Userlogin doesn't immediately return if the user is logged in
				'sul3-returnto' => $request->getRawVal( 'returnto' ),
				'sul3-returntoquery' => $request->getRawVal( 'returntoquery' ),
				'sul3-returntoanchor' => $request->getRawVal( 'returntoanchor' ),
			];
			$params['sul3-action'] = 'signup';
			$url = wfAppendQuery( $localLoginUrl, $params );

			$special->getOutput()->redirect( $url );
			return false;
		}

		if ( $special->getUser()->isAnon() && $this->sharedDomainUtils->isSharedDomain() ) {
			$this->setClientPreferences( $request );
		}

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
			|| !$this->centralDomainUtils->centralDomainExists( $request )
			|| $this->centralDomainUtils->isCentralDomain( $request )
		) {
			return true;
		}

		$isMobile = $this->sharedDomainUtils->shouldUseMobile();

		// Do a top-level autologin if the user needs to log in. Use a cookie to prevent
		// unnecessary autologin attempts if we already know they will fail, and a query parameter
		// (to be set by SpecialCentralAutoLogin) to avoid an infinite loop even if we cannot set
		// cookies for some reason.
		if ( $special->getUser()->isAnon()
			 && !$request->getCookie( self::AUTOLOGIN_TRIED_COOKIE, '' )
			 && !$request->getCheck( self::AUTOLOGIN_TRIED_QUERY_PARAM )
		) {
			$url = $this->centralDomainUtils->getUrl(
				CentralDomainUtils::AUTOLOGIN_CENTRAL_DOMAIN_ID,
				'Special:CentralAutoLogin/checkLoggedIn',
				$request
			);

			$this->log( 'Top-level autologin started', $special, $isMobile );

			$request->response()->setCookie(
				self::AUTOLOGIN_TRIED_COOKIE,
				'1',
				// add 1 day, like in anon-set.js
				time() + ( 24 * 3600 ),
				// match the behavior of the CentralAuthAnon cookie
				[ 'prefix' => '', 'httpOnly' => false ]
			);

			$returnUrl = wfAppendQuery( $request->getFullRequestURL(), [
				self::AUTOLOGIN_TRIED_QUERY_PARAM => 1,
			] );
			$returnUrl = $this->sharedDomainUtils->makeUrlDeviceCompliant( $returnUrl );
			$returnUrlToken = $this->tokenManager->tokenize( $returnUrl, 'centralautologin-returnurl' );
			$url = wfAppendQuery( $url, [
				'type' => 'redirect',
				'returnUrlToken' => $returnUrlToken,
				'wikiid' => WikiMap::getCurrentWikiId(),
				'usesul3' => $this->sharedDomainUtils->isSul3Enabled( $request ) ? 1 : 0,
			] );

			$special->getOutput()->redirect( $this->sharedDomainUtils->makeUrlDeviceCompliant( $url ) );

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
			$error = $request->getRawVal( self::AUTOLOGIN_ERROR_QUERY_PARAM ) ?? 'unknown error';
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

	/**
	 * When anonymous clients set their preferences, these preferences
	 * should be memorized and applied onto the shared domain during
	 * authentication to retain browsing experience across domains.
	 *
	 * For example when anon users set their theme to dark mode, it
	 * should be applied to the shared domain and render in dark mode.
	 */
	private function setClientPreferences( WebRequest $request ): void {
		$token = $request->getRawVal( 'centralauthLoginToken' );

		// A user can try to fake a request to the shared domain without a
		// centralauthLoginToken. Don't try to do anything, they can apply
		// preferences manually on the interface there if they desire.
		if ( !$token ) {
			return;
		}

		$inputData = $this->tokenManager->detokenize(
			$token,
			CentralAuthRedirectingPrimaryAuthenticationProvider::START_TOKEN_KEY_PREFIX
		);

		// T395957 - If we have valid input data but no client preferences
		// set in the data, is let's just return and use the default preference
		// on this domain. This happened when users were about the login in
		// between train deploys and the `clientPref` parameter was unset. So
		// to protect against this, let's check to make sure we have preferences
		// to apply before we even attempt to apply them.
		if ( !$inputData || !isset( $inputData['clientPref'] ) ) {
			// No valid input data will be bad if we find ourselves here during
			// an actual authentication flow and the user's preferences is not
			// applied. In the worse case, let's just default to whatever mode
			// was already used previously by this client or whatever we have on
			// the shared domain for this client.
			return;
		}

		if ( $inputData['clientPref'] ) {
			$cookieName = ClientHtml::CLIENT_PREFS_COOKIE_NAME;
			$request->response()->setCookie(
				$cookieName,
				$inputData['clientPref'],
				0,
				[ 'httpOnly' => false ]
			);
		}
	}
}
