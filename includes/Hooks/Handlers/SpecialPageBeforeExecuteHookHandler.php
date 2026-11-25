<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\CentralAuthRedirectingPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\ResourceLoader\ClientHtml;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\Helpers\LoginHelper;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

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
	public const AUTOLOGIN_TRIED_QUERY_PARAM = 'centralAuthAutologinTried';

	/** Query parameter used to return error messages from SpecialCentralAutoLogin. */
	public const AUTOLOGIN_ERROR_QUERY_PARAM = 'centralAuthError';

	/**
	 * Short-lived cookie used to check that the client keeps cookies before a SUL3 login
	 * starts a session. See checkCookiesBeforeSul3Login().
	 */
	public const SUL3_COOKIE_CHECK_COOKIE = 'CentralAuthSul3CookieCheck';

	/**
	 * Query parameter that marks that the SUL3 cookie check cookie has been set.
	 * Its value is the time when the cookie was set.
	 */
	public const SUL3_COOKIE_CHECK_QUERY_PARAM = 'centralAuthCookieCheck';

	/**
	 * Lifetime of the SUL3 cookie check cookie, in seconds. The query parameter counts
	 * for the same time.
	 */
	private const SUL3_COOKIE_CHECK_LIFETIME = 300;

	public function __construct(
		private readonly AuthManager $authManager,
		private readonly Config $config,
		private readonly StatsFactory $statsFactory,
		private readonly CentralAuthTokenManager $tokenManager,
		private readonly CentralDomainUtils $centralDomainUtils,
		private readonly SharedDomainUtils $sharedDomainUtils,
	) {
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
			$params = [
				'sul3-action' => 'signup',
				// support existing warning messages from anon actions that redirect to auth pages (T416057)
				'error' => $request->getRawVal( 'error' ),
				'warning' => $request->getRawVal( 'warning' ),
				'notice' => $request->getRawVal( 'notice' ),
				// replicate the non-hook part of LoginSignupSpecialPage::getPreservedParams()
				// (except HTTP login which is not supported in CentralAuth)
				'alwaysShowLogin' => '1',
			];
			$loginHelper = new LoginHelper( $special->getContext() );
			$params = $loginHelper->getPreservedParams( [ 'params' => $params ] );

			$localLoginUrl = SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL();
			$url = wfAppendQuery( $localLoginUrl, $params );
			$special->getOutput()->redirect( $url );
			return false;
		}

		if ( $special->getName() === 'Userlogin'
			&& $isSul3Enabled
			&& !$this->checkCookiesBeforeSul3Login( $special, $subPage )
		) {
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
				'usesul3' => $this->sharedDomainUtils->isSul3Enabled( $request )
					? SharedDomainUtils::SUL3_ENABLED : SharedDomainUtils::SUL3_DISABLED,
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

	/**
	 * Check that the client keeps cookies before a SUL3 login starts a session.
	 *
	 * On a wiki's own domain, Special:UserLogin saves a session and redirects to the shared
	 * domain. A client that does not keep cookies cannot log in, but each of its requests
	 * saves a new session. So for a client with no session, set a short-lived cookie and
	 * redirect to the same URL, and go on only when the cookie comes back.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 * @return bool False when a redirect or an error page was sent
	 */
	private function checkCookiesBeforeSul3Login( SpecialPage $special, $subPage ): bool {
		$request = $special->getRequest();
		if ( !$this->config->get( CAMainConfigNames::CentralAuthSul3CookieCheck )
			|| $request->wasPosted()
			// The return from the shared domain
			|| $subPage === 'return'
			|| $special->getUser()->isRegistered()
			|| $request->getSession()->isPersistent()
		) {
			return true;
		}

		// Only trust a recent marker. The marker stays in the URL after a passed check, so an
		// old one (a reload, a bookmark, a shared link) does not mean that we just set the cookie.
		$now = ConvertibleTimestamp::time();
		$markerAge = $now - $request->getInt( self::SUL3_COOKIE_CHECK_QUERY_PARAM );
		$hasMarker = $markerAge >= 0 && $markerAge < self::SUL3_COOKIE_CHECK_LIFETIME;

		if ( $request->getCookie( self::SUL3_COOKIE_CHECK_COOKIE ) ) {
			if ( $hasMarker ) {
				$this->countCookieCheck( 'passed' );
			}
			return true;
		}

		$out = $special->getOutput();
		// The response depends on the cookies and the time, so the browser must not reuse it.
		$out->disableClientCache();
		// Also when the check failed, so that a reload works once the client keeps cookies.
		$request->response()->setCookie(
			self::SUL3_COOKIE_CHECK_COOKIE, '1', $now + self::SUL3_COOKIE_CHECK_LIFETIME );

		if ( $hasMarker ) {
			// We set the cookie on the previous request, and it did not come back.
			$this->countCookieCheck( 'failed' );
			$this->log( 'SUL3 login stopped: client does not keep cookies', $special,
				$this->sharedDomainUtils->shouldUseMobile() );
			$out->showErrorPage( 'loginerror', 'nocookieslogin' );
			return false;
		}

		$this->countCookieCheck( 'started' );
		// A local URL, so that the client stays on the host that got the cookie.
		$out->redirect( SpecialPage::getTitleFor( 'Userlogin', $subPage ?: false )->getLocalURL(
			$request->appendQueryValue( self::SUL3_COOKIE_CHECK_QUERY_PARAM, (string)$now )
		) );
		return false;
	}

	/**
	 * @param string $result 'started' (cookie set, client redirected), 'passed' (the cookie
	 *   came back) or 'failed' (it did not, error shown)
	 */
	private function countCookieCheck( string $result ): void {
		$this->statsFactory->withComponent( 'CentralAuth' )
			->getCounter( 'sul3_cookie_check_total' )
			->setLabel( 'result', $result )
			->increment();
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
