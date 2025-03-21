<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
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
	private Config $config;
	private HookRunner $hookRunner;
	private UserNameUtils $userNameUtils;
	private CentralAuthTokenManager $tokenManager;
	private CentralDomainUtils $centralDomainUtils;
	private SharedDomainUtils $sharedDomainUtils;

	/**
	 * @param AuthManager $authManager
	 * @param HookContainer $hookContainer
	 * @param Config $config
	 * @param UserNameUtils $userNameUtils
	 * @param CentralAuthTokenManager $tokenManager
	 * @param CentralDomainUtils $centralDomainUtils
	 * @param SharedDomainUtils $sharedDomainUtils
	 */
	public function __construct(
		AuthManager $authManager,
		HookContainer $hookContainer,
		Config $config,
		UserNameUtils $userNameUtils,
		CentralAuthTokenManager $tokenManager,
		CentralDomainUtils $centralDomainUtils,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->authManager = $authManager;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->config = $config;
		$this->userNameUtils = $userNameUtils;
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
			$isSul3Enabled = $this->sharedDomainUtils->isSul3Enabled( $request, $isSul3StateUnset );
			if ( $isSul3StateUnset ) {
				// we will either:
				// set an sul3 wanted cookie for IP users because IP matches a cutoff
				// set an sul3 wanted cookie for IP users because sul3 is enabled for everyone on the wiki
				// set SUL3 rollout global pref for user from UserName cookie
				// and the next call to isSul3Enabled will indicate SUL3RolloutParticipating
				$isSul3Enabled = $this->checkSUL3RolloutParticipation( $request, $special->getName() );
			}
		}

		// In SUL3 mode, account creation is seen locally as a login, so redirect
		// there. The 'sul3-action' flag will ensure that the user ends up on the
		// account creation page once on the central domain.
		if ( $special->getName() === 'CreateAccount'
			&& $isSul3Enabled
			&& !$this->sharedDomainUtils->isSharedDomain()
		) {
			$localLoginUrl = SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL();
			$params = [];
			$this->hookRunner->onAuthPreserveQueryParams( $params, [ 'request' => $request ] );
			$params['sul3-action'] = 'signup';
			$url = wfAppendQuery( $localLoginUrl, $params );

			$special->getOutput()->redirect( $url );
			return false;
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
			|| $this->centralDomainUtils->isActiveOrPassiveCentralDomain( $request )
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
	 * Given a user who isn't in either the SUL2 or SUL3 cohort yet, select a cohort for them.
	 * @param WebRequest $request
	 * @param string $specialPageName
	 * @return bool True if the user is in the SUL3 cohort.
	 */
	private function checkSUL3RolloutParticipation( $request, $specialPageName ): bool {
		$isSignup = ( $specialPageName === 'CreateAccount' );
		$user = RequestContext::getMain()->getUser();
		$setSul3Flag = false;
		if ( $isSignup ) {
			if ( $this->shouldSetSUL3RolloutCookie( $request, $user ) ) {
				$setSul3Flag = true;
			}
		} elseif ( $this->sharedDomainUtils->shouldSetSUL3RolloutGlobalPref( $request, $user ) ) {
			$setSul3Flag = true;
		}

		if ( $setSul3Flag ) {
			// Set a short-lived cookie just to make sure future checks are consistent.
			$this->sharedDomainUtils->setSUL3RolloutCookie( $request );
			// Also make sure checks in the current request are affected.
			$request->setVal( 'usesul3', 1 );
			return true;
		}
		return false;
	}

	/**
	 * check if we should set SUL3 wanted cookie:
	 * is the user an IP address? is the cookie already set?
	 * does the IP address meet the conditions for participation
	 * in the rollout?
	 * alternatively, is the local wiki set to always be sul3 enabled?
	 * return true if so, false otherwise
	 *
	 * @param Webrequest $request
	 * @param User $user
	 * @return bool
	 */
	private function shouldSetSUL3RolloutCookie( $request, $user ) {
		if ( !$this->sharedDomainUtils->hasSul3EnabledFlag( SharedDomainUtils::SUL3_ENABLED_COOKIE ) ) {
			return false;
		}
		$name = $user->getName();
		if ( !$this->userNameUtils->isIP( $name ) ) {
			return false;
		}
		if ( $this->sharedDomainUtils->hasSul3EnabledFlag( SharedDomainUtils::SUL3_ENABLED_ALWAYS ) ) {
			return true;
		}
		$sul3RolloutAnonUserConfig = $this->config->get( CAMainConfigNames::Sul3RolloutAnonSignupPercentage );
		if ( $this->sharedDomainUtils->checkPercentage(
			$user, $sul3RolloutAnonUserConfig, 'Sul3RolloutAnonSignupPercentage' ) ) {
			return true;
		}
		return false;
	}
}
