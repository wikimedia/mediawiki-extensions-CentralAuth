<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthSessionProvider;
use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUtilityService;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\PageDisplayHookHandler;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialPageBeforeExecuteHookHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Session\Session;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SkinTemplate;
use Wikimedia\ScopedCallback;

/**
 * Unlisted special page that handles central autologin and edge login, and some related
 * functionality.
 *
 * It does different things depending on the subpage name:
 * - /start, /checkLoggedIn, /createSession, /validateSession, /setCookies: these are successive
 *   steps of autologin/edge login, with each step calling the next one via a redirect.
 *   (/start is somewhat optional.) The 'type' get parameter tells how the chain was triggered
 *   (via a script tag, a visible or invisible img tag, or top-level redirect); the final step
 *   needs to generate a response accordingly.
 * - /refreshCookies: used right after login to update the central session cookies.
 * - /deleteCookies: used after logout.
 * - /toolslist: a helper used after successful autologin to update the skin's personal toolbar.
 * See the inline comments in the big switch() construct for the description of each.
 *
 * @see CentralAuthHooks::getEdgeLoginHTML()
 * @see PageDisplayHookHandler::onBeforePageDisplay()
 * @see SpecialPageBeforeExecuteHookHandler::onSpecialPageBeforeExecute()
 * @see SpecialCentralLogin
 * @see https://www.mediawiki.org/wiki/Extension:CentralAuth/authentication
 */
class SpecialCentralAutoLogin extends UnlistedSpecialPage {

	/** @var Session|null */
	protected $session = null;

	private HookContainer $hookContainer;
	private LanguageFactory $languageFactory;
	private UserFactory $userFactory;
	private UserOptionsLookup $userOptionsLookup;
	private CentralAuthSessionManager $sessionManager;
	private CentralAuthTokenManager $tokenManager;
	private CentralAuthUtilityService $centralAuthUtilityService;
	private CentralDomainUtils $centralDomainUtils;
	private LoggerInterface $logger;
	private string $subpage;

	/**
	 * @param HookContainer $hookContainer
	 * @param LanguageFactory $languageFactory
	 * @param UserFactory $userFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param CentralAuthSessionManager $sessionManager
	 * @param CentralAuthTokenManager $tokenManager
	 * @param CentralAuthUtilityService $centralAuthUtilityService
	 * @param CentralDomainUtils $centralDomainUtils
	 */
	public function __construct(
		HookContainer $hookContainer,
		LanguageFactory $languageFactory,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup,
		CentralAuthSessionManager $sessionManager,
		CentralAuthTokenManager $tokenManager,
		CentralAuthUtilityService $centralAuthUtilityService,
		CentralDomainUtils $centralDomainUtils
	) {
		parent::__construct( 'CentralAutoLogin' );

		$this->hookContainer = $hookContainer;
		$this->languageFactory = $languageFactory;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->sessionManager = $sessionManager;
		$this->tokenManager = $tokenManager;
		$this->centralAuthUtilityService = $centralAuthUtilityService;
		$this->centralDomainUtils = $centralDomainUtils;
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	/**
	 * Get contents of a javascript file for inline use.
	 *
	 * @param string $name Path to file relative to /modules/inline/
	 * @return string Minified JavaScript code
	 * @throws Exception If file doesn't exist
	 */
	protected static function getInlineScript( $name ) {
		$filePath = __DIR__ . '/../../modules/inline/' . $name;
		if ( !file_exists( $filePath ) ) {
			throw new RuntimeException( __METHOD__ . ": file not found: \"$filePath\"" );
		}
		$rawScript = file_get_contents( $filePath );

		// Hot path, static content, must use a cache
		return ResourceLoader::filter( 'minify-js', $rawScript, [ 'cache' => true ] );
	}

	/**
	 * Check the session (if applicable) and fill in $this->session
	 * @param string $body
	 * @param string|bool $type
	 * @return bool
	 */
	protected function checkSession( $body = '', $type = false ) {
		$session = $this->getRequest()->getSession();
		if ( !$session->getProvider() instanceof CentralAuthSessionProvider ) {
			$this->doFinalOutput(
				false,
				'Cannot operate when using ' .
					$session->getProvider()->describe( $this->languageFactory->getLanguage( 'en' ) ),
				$body,
				$type
			);
			return false;
		}
		$this->session = $session;
		return true;
	}

	/**
	 * Check whether the user's preferences are such that a UI reload is
	 * recommended.
	 * @param User $user
	 * @return bool
	 */
	private function isUIReloadRecommended( User $user ) {
		foreach ( $this->getConfig()->get( CAMainConfigNames::CentralAuthPrefsForUIReload ) as $pref ) {
			if (
				$this->userOptionsLookup->getOption( $user, $pref ) !==
				$this->userOptionsLookup->getDefaultOption( $pref, $this->userFactory->newAnonymous() )
			) {
				return true;
			}
		}

		$hookRunner = new CentralAuthHookRunner( $this->hookContainer );

		$recommendReload = false;
		$hookRunner->onCentralAuthIsUIReloadRecommended( $user, $recommendReload );
		return $recommendReload;
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		if (
			in_array( $par, [ 'toolslist', 'refreshCookies', 'deleteCookies', 'start', 'checkLoggedIn',
				'createSession', 'validateSession', 'setCookies' ], true )
		) {
			$this->logger->debug( 'CentralAutoLogin step {step}', [
				'step' => $par,
			] );
		}

		$this->subpage = $par ?? '';

		$request = $this->getRequest();

		if ( !$this->centralDomainUtils->centralDomainExists( $this->getRequest() ) ) {
			$fromwiki = $request->getVal( 'from' );
			if ( $fromwiki !== null && WikiMap::getWiki( $fromwiki ) ) {
				$this->centralDomainUtils = $this->centralDomainUtils->withFallbackLoginWikiId( $fromwiki );
			}
		}

		// Parameters which should be preserved across autologin steps.
		$params = $request->getValues(
			// The method by which autologin is initiated: 'script' (as the src of a <script> tag),
			// 'json' (via AJAX), '1x1' (as the src of an invisible pixel), 'icon' (as the src of
			// a site logo icon), 'redirect' (top-level redirect). Determines how the final response
			// is formatted, in some cases might affect the logic in other ways as well.
			'type',
			// The wiki that started the autologin process. Not necessarily the wiki where the
			// user is supposed to be logged in, because of edge autologin. Used as a fallback
			// "central" wiki when no dedicated central wiki is configured.
			'from',
			// Token for the final return URL for type=redirect
			'returnUrlToken',
			// When 'return' is set, at the end of autologin the user will be redirected based on
			// returnto/returntoquery (like for normal login). Used for autologin triggered on the
			// login page.
			'return',
			'returnto',
			'returntoquery',
			// SUL3 opt-in flag to make sure a sequence of central autologin steps all use the
			// same mechanism on a wiki farm that is in the middle of SUL3 rollout
			'usesul3',
		);
		// Other parameters that that are not always preserved:
		// 'wikiid': The wiki where the user is being auto-logged in. (used in checkIsCentralWiki)
		// 'token': Random store key, used to pass information in a secure manner.

		// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.BreakIndent
		switch ( strval( $par ) ) {
			// Extra steps, not part of the login process
			case 'toolslist':
				// Return the contents of the user menu so autologin.js can sort-of refresh the skin
				// without reloading the page. This results in lots of inconsistencies and brokenness,
				// but at least the user sees they are logged in.
				// Runs on the local wiki.

				// Do not cache this, we want updated Echo numbers and such.
				$this->getOutput()->disableClientCache();

				if ( !$this->checkSession( '', 'json' ) ) {
					return;
				}

				$user = $this->getUser();
				if ( $user->isRegistered() ) {
					$skin = $this->getSkin();
					if (
						!$this->isUIReloadRecommended( $user ) &&
						$skin instanceof SkinTemplate
					) {
						$html = $skin->makePersonalToolsList();
						$json = FormatJson::encode( [ 'toolslist' => $html ] );
					} else {
						$gender = $this->userOptionsLookup->getOption( $this->getUser(), 'gender' );
						if ( strval( $gender ) === '' ) {
							$gender = 'unknown';
						}
						$json = FormatJson::encode( [
							'notify' => [
								'username' => $user->getName(),
								'gender' => $gender
							]
						] );
					}
					$this->doFinalOutput( true, 'OK', $json, 'json' );
				} else {
					$this->doFinalOutput( false, 'Not logged in', '', 'json' );
				}
				return;

			case 'refreshCookies':
				// Refresh cookies on the central login wiki at the end of a successful login,
				// to fill in information that could not be set when those cookies where created
				// (e.g. the 'remember me' token).
				// Runs on the central login wiki.

				// Do not cache this, we need to reset the cookies every time.
				$this->getOutput()->disableClientCache();

				if ( !$this->centralDomainUtils->centralDomainExists( $this->getRequest() ) ) {
					$this->logger->debug( "refreshCookies: no login wiki" );
					return;
				}
				if ( !$this->assertIsCentralDomain() ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				$centralUser = CentralAuthUser::getInstance( $this->getUser() );
				if ( $centralUser->getId() && $centralUser->isAttached() ) {
					$remember = (bool)$this->getCentralSession( 'refreshCookies' )['remember'];
					$delay = $this->session->delaySave();
					$this->session->setRememberUser( $remember );
					$this->session->persist();
					ScopedCallback::consume( $delay );
					$this->doFinalOutput( true, 'success' );
				} else {
					$this->doFinalOutput( false, 'Not logged in' );
				}
				return;

			case 'deleteCookies':
				// Delete CentralAuth-specific cookies on logout. (This is just cleanup, the backend
				// session will be invalidated regardless of whether this succeeds.)
				// Runs on the central login wiki and the edge wikis.

				// Do not cache this, we need to reset the cookies every time.
				$this->getOutput()->disableClientCache();

				if ( !$this->checkSession() ) {
					return;
				}

				if ( $this->getUser()->isRegistered() ) {
					$this->doFinalOutput( false, 'Cannot delete cookies while still logged in' );
					return;
				}

				$this->session->setUser( new User );
				$this->session->persist();
				$this->doFinalOutput( true, 'success' );
				return;

			// Login process
			case 'start':
				// Entry point for edge autologin: this is called on various wikis via an <img> URL
				// to preemptively log the user in on that wiki, so their session exists by the time
				// they first visit.  Sometimes it is also used as the entry point for local autologin;
				// there probably isn't much point in that (it's an extra redirect). This endpoint
				// doesn't do much, just redirects to /checkLoggedIn on the central login wiki.
				// Runs on the local wiki and the edge wikis.

				// Note this is safe to cache, because the cache already varies on
				// the session cookies.
				$this->getOutput()->setCdnMaxage( 1200 );

				if ( !$this->assertIsLocalDomain() ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				// Do not use isSul3Enabled() since this response will be cached, and so we don't
				// want it to depend on the user. In practice, it would probably be fine since at
				// this point we should have an explicit 'usesul3' URL parameter which splits the
				// cache, but it's better to be safe.
				$useSul3 = $request->getBool( 'usesul3' ) ? 1 : 0;
				$domain = $useSul3
					? CentralDomainUtils::SUL3_CENTRAL_DOMAIN_ID
					: CentralDomainUtils::SUL2_CENTRAL_DOMAIN_ID;
				$this->do302Redirect( $domain, 'checkLoggedIn', [
					'wikiid' => WikiMap::getCurrentWikiId(),
					'usesul3' => $useSul3,
				] + $params );
				return;

			case 'checkLoggedIn':
				// Sometimes entry point for autologin, sometimes second step after /start.
				// Runs on the central login wiki. Checks that the user has a valid session there,
				// then redirects back to /createSession on the original wiki and passes the user ID
				// (in the form of a lookup key for the shared token store, to prevent a malicious
				// website from learning it).
				// FIXME the indirection of user ID is supposedly for T59081, but unclear how that's
				//   supposed to be a threat when the redirect URL has been validated via WikiMap.
				// Runs on the central login wiki.

				// Note this is safe to cache, because the cache already varies on
				// the session cookies.
				$this->getOutput()->setCdnMaxage( 1200 );

				$wikiid = $this->getRequest()->getRawVal( 'wikiid' );
				if ( !$this->assertIsCentralDomain() || !$this->assertLocalWikiIsValid( $wikiid ) ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				if ( !$this->getUser()->isRegistered() ) {
					// Try /checkLoggedIn on both the SUL2 and SUL3 central domains: if it didn't
					// work on the current one, redirect to the other one.
					// At this point, requests without a session cookie still need to be cacheable,
					// and changes to the code won't immediately affect cached responses, so this
					// needs to be managed carefully to avoid e.g. redirect loops.
					$triedSul3Fallback = $this->getRequest()->getCheck( 'triedSul3Fallback' );
					if ( !$triedSul3Fallback ) {
						// Make sure we have a deterministic, URL-based opt-in flag, like in /start.
						// This is the opposite of the actual URL parameter, since we want to switch.
						$useSul3 = $request->getBool( 'usesul3' ) ? 0 : 1;
						$domain = $useSul3
							? CentralDomainUtils::SUL3_CENTRAL_DOMAIN_ID
							: CentralDomainUtils::SUL2_CENTRAL_DOMAIN_ID;
						$this->do302Redirect( $domain, 'checkLoggedIn', [
							'wikiid' => $wikiid,
							'usesul3' => $useSul3,
							'triedSul3Fallback' => 1,
						] + $params );
						return;
					} else {
						$this->doFinalOutput( false, 'Not centrally logged in',
							self::getInlineScript( 'anon-set.js' ) );
						return;
					}
				}

				// If we got here, the user is probably logged in, and responses will contain
				// information specific to them; prevent further caching. The cache is split
				// on cookies, and isRegistered() couldn't be passed if there weren't any, so
				// this is OK.
				$this->getOutput()->disableClientCache();

				$centralUser = CentralAuthUser::getInstance( $this->getUser() );

				// Check if the loginwiki account isn't attached, things are broken (T137551)
				if ( !$centralUser->isAttached() ) {
					$this->doFinalOutput( false,
						'Account on central wiki is not attached (this shouldn\'t happen)',
						self::getInlineScript( 'anon-set.js' )
					);
					return;
				}

				$memcData = [ 'gu_id' => $centralUser->getId() ];
				$token = $this->tokenManager->tokenize( $memcData, 'centralautologin-token' );

				$this->do302Redirect( $wikiid, 'createSession', [
					'token' => $token,
					] + $params );
				return;

			case 'createSession':
				// Creates an unvalidated local session, and redirects back to the central login wiki
				// to validate it.
				// At this point we received the user ID from /checkLoggedIn but must ensure this is
				// not a session fixation attack, so we set a session cookie for an anonymous session,
				// set a random proof token in that session, stash the user's supposed identity
				// under that token in the shared store, and pass the token back to the /validateSession
				// endpoint of the central login wiki.
				// Runs on the wiki where the autologin needs to log the user in (the local wiki,
				// or the edge wikis, or both).

				if ( !$this->assertIsLocalDomain() ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				$token = $request->getVal( 'token', '' );
				if ( $token !== '' ) {
					$memcData = $this->tokenManager->detokenizeAndDelete( $token, 'centralautologin-token' );
					if ( !$memcData || !isset( $memcData['gu_id'] ) ) {
						$this->doFinalOutput( false, 'Invalid parameters' );
						return;
					}
					$gu_id = intval( $memcData['gu_id'] );
				} else {
					$this->doFinalOutput( false, 'Invalid parameters' );
					return;
				}

				if ( $gu_id <= 0 ) {
					$this->doFinalOutput( false, 'Not centrally logged in',
						self::getInlineScript( 'anon-set.js' ) );
					return;
				}

				// At this point we can't cache anymore because we need to set
				// cookies and memc each time.
				$this->getOutput()->disableClientCache();

				// Ensure that a session exists
				$this->session->persist();

				// Create memc token
				$wikiid = WikiMap::getCurrentWikiId();
				$memcData = [
					'gu_id' => $gu_id,
					'wikiid' => $wikiid,
				];
				$token = $this->tokenManager->tokenize( $memcData, [ 'centralautologin-token', $wikiid ] );

				// Save memc token for the 'setCookies' step
				$request->setSessionData( 'centralautologin-token', $token );

				$this->do302Redirect( CentralDomainUtils::AUTOLOGIN_CENTRAL_DOMAIN_ID, 'validateSession', [
					'token' => $token,
					'wikiid' => $wikiid,
				] + $params );
				return;

			case 'validateSession':
				// Validates the session created by /createSession by looking up the user ID in the
				// shared store and comparing it to the actual user ID. Puts all extra information
				// needed to create a logged-in session ("remember me" flag, ID of the central
				// session etc.) under the same entry in the shared store that /createSession initiated.
				// Runs on the central login wiki.

				// Do not cache this, we need to reset the cookies and memc every time.
				$this->getOutput()->disableClientCache();

				$wikiid = $this->getRequest()->getRawVal( 'wikiid' );
				if ( !$this->assertIsCentralDomain() || !$this->assertLocalWikiIsValid( $wikiid ) ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				if ( !$this->getUser()->isRegistered() ) {
					$this->doFinalOutput( false, 'Not logged in' );
					return;
				}

				// Validate params
				$token = $request->getVal( 'token', '' );
				if ( $token === '' ) {
					$this->doFinalOutput( false, 'Invalid parameters' );
					return;
				}

				// Load memc data
				$memcData = $this->tokenManager->detokenizeAndDelete(
					$token, [ 'centralautologin-token', $wikiid ]
				);

				// Check memc data
				$centralUser = CentralAuthUser::getInstance( $this->getUser() );
				if ( !$memcData ||
					$memcData['wikiid'] !== $wikiid ||
					!$centralUser->getId() ||
					!$centralUser->isAttached() ||
					$memcData['gu_id'] != $centralUser->getId()
				) {
					$this->doFinalOutput( false, 'Invalid parameters' );
					return;
				}

				// Write info for session creation into memc
				$centralSession = $this->getCentralSession( 'validateSession' );
				$memcData += [
					'userName' => $centralUser->getName(),
					'token' => $centralUser->getAuthToken(),
					'remember' => $centralSession['remember'],
					'sessionId' => $centralSession['sessionId'],
				];

				$this->tokenManager->tokenize(
					$memcData,
					[ 'centralautologin-token', $wikiid ],
					[ 'token' => $token ]
				);

				// No need to pass the token, the initiating wiki has it in its session.
				$this->do302Redirect( $wikiid, 'setCookies', $params );
				return;

			case 'setCookies':
				// Final step of the autologin sequence, replaces the unvalidated session with a real
				// logged-in session. Also schedules an edge login for the next pageview, and for
				// type=script autocreates the user so that mw.messages notices can be shown in the
				// user language.
				// If all went well, the data about the user's session on the central login wiki will
				// be in the shared store, under a random key that's stored in the temporary,
				// anonymous local session. The access to that key proves that this is the same device
				// that visited /createSession before with a preliminary central user ID, and the
				// fact that /validateSession updated the store data proves that the preliminary ID was
				// in fact correct.
				// Runs on the wiki where the autologin needs to log the user in (the local wiki,
				// or the edge wikis, or both).

				// This is a bit inaccurate: it could also be a no-JS autologin. But good enough for our
				// purposes.
				$isEdgeLogin = !in_array( $request->getVal( 'type' ), [ 'script', 'redirect' ], true );

				// Do not cache this, we need to reset the cookies and memc every time.
				$this->getOutput()->disableClientCache();

				if ( !$this->assertIsLocalDomain() ) {
					return;
				}
				if ( !$this->checkSession() ) {
					return;
				}

				// Check saved memc token
				$token = $this->getRequest()->getSessionData( 'centralautologin-token' );
				if ( $token === null ) {
					$this->doFinalOutput( false, 'Lost session' );
					return;
				}

				// Load memc data
				$wikiid = WikiMap::getCurrentWikiId();
				$memcData = $this->tokenManager->detokenizeAndDelete( $token, [ 'centralautologin-token', $wikiid ] );

				// Check memc data
				if (
					!is_array( $memcData ) ||
					$memcData['wikiid'] !== $wikiid ||
					!isset( $memcData['userName'] ) ||
					!isset( $memcData['token'] )
				) {
					$this->doFinalOutput( false, 'Lost session' );
					return;
				}

				// Load and check CentralAuthUser. But don't check if it's
				// attached, because then if the user is missing en.site they
				// won't be auto logged in to any of the non-en versions either.
				$centralUser = CentralAuthUser::getInstanceByName( $memcData['userName'] );
				if ( !$centralUser->getId() || $centralUser->getId() != $memcData['gu_id'] ) {
					$this->logger->warning( __METHOD__ . ": Wrong user: expected {gu_id}, got {cu_id}", [
						'gu_id' => $memcData['gu_id'],
						'cu_id' => $centralUser->getId(),
					] );
					$this->doFinalOutput( false, 'Lost session' );
					return;
				}
				$loginResult = $centralUser->authenticateWithToken( $memcData['token'] );
				if ( $loginResult != 'ok' ) {
					$this->logger->warning( __METHOD__ . ": Bad token: {loginResult}", [
						'loginResult' => $loginResult,
					] );
					$this->doFinalOutput( false, 'Lost session' );
					return;
				}
				$localUser = User::newFromName( $centralUser->getName(), 'usable' );
				if ( !$localUser ) {
					$this->doFinalOutput( false, 'Invalid username' );
					return;
				}
				if ( $localUser->isRegistered() && !$centralUser->isAttached() ) {
					$this->doFinalOutput( false, 'Local user exists but is not attached' );
					return;
				}

				/** @var ScopedCallback|null $delay */
				$delay = null;

				$delay = $this->session->delaySave();
				$this->session->resetId();
				// FIXME what is the purpose of this (beyond storing the central session ID in the
				//   local session, which we could do directly)? We have just read this data from
				//   the central session one redirect hop ago.
				$this->sessionManager->setCentralSession( [
					'remember' => $memcData['remember'],
				], $memcData['sessionId'], $this->session );
				if ( $centralUser->isAttached() ) {
					// Set the user on the session, if the user is already attached.
					$this->session->setUser( User::newFromName( $centralUser->getName() ) );
				}
				$this->session->setRememberUser( $memcData['remember'] );
				$this->session->persist();

				// Now, figure out how to report this back to the user.

				// First, do an edge login on the next pageview (unless we are already doing one,
				// or we are on a central domain).
				if ( !$this->centralDomainUtils->isActiveOrPassiveCentralDomain( $request ) && !$isEdgeLogin ) {
					$this->logger->debug( 'Edge login on the next pageview after CentralAutoLogin' );
					$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
				}

				// Do not autocreate during edge login, only if the user is actually interacting with the wiki.
				if ( $isEdgeLogin ) {
					ScopedCallback::consume( $delay );
					$this->doFinalOutput( true, 'success' );
					return;
				}

				// If it is a script or redirect callback, then we do want to create the user
				// if it doesn't already exist locally (and fail if that can't be done).
				if ( !$localUser->isRegistered() ) {
					$localUser = new User;
					$localUser->setName( $centralUser->getName() );
					if ( $this->centralAuthUtilityService->autoCreateUser(
						$localUser, true, $localUser
					)->isGood() ) {
						$centralUser->invalidateCache();
						$centralUser = CentralAuthUser::getPrimaryInstanceByName( $centralUser->getName() );
					}
				}
				if ( !$centralUser->isAttached() ) {
					ScopedCallback::consume( $delay );
					$this->doFinalOutput(
						false, 'Local user is not attached', self::getInlineScript( 'anon-set.js' ) );
					return;
				}
				// Set the user on the session now that we know it exists.
				$this->session->setUser( $localUser );
				ScopedCallback::consume( $delay );

				$script = self::getInlineScript( 'anon-remove.js' );

				// If we're returning to returnto, do that
				if ( $request->getCheck( 'return' ) ) {
					if ( $this->getConfig()->get( MainConfigNames::RedirectOnLogin ) !== null ) {
						$returnTo = $this->getConfig()->get( MainConfigNames::RedirectOnLogin );
						$returnToQuery = [];
					} else {
						$returnTo = $request->getVal( 'returnto', '' );
						$returnToQuery = wfCgiToArray( $request->getVal( 'returntoquery', '' ) );
					}

					$returnToTitle = Title::newFromText( $returnTo );
					if ( !$returnToTitle ) {
						$returnToTitle = Title::newMainPage();
						$returnToQuery = [];
					}

					$redirectUrl = $returnToTitle->getFullUrlForRedirect( $returnToQuery );

					$script .= "\n" . 'location.href = ' . Html::encodeJsVar( $redirectUrl ) . ';';

					$this->doFinalOutput( true, 'success', $script );
					return;
				}

				// Otherwise, we need to rewrite p-personal and maybe notify the user too
				// Add a script to the page that will pull in the user's toolslist
				// via ajax, and update the UI. Don't write out the tools here (T59081).
				$code = $this->userOptionsLookup->getOption( $localUser, 'language' );
				$code = RequestContext::sanitizeLangCode( $code );

				$this->getHookRunner()->onUserGetLanguageObject( $localUser, $code, $this->getContext() );

				$script .= "\n" . Html::encodeJsCall( 'mw.messages.set', [
						[
							'centralauth-centralautologin-logged-in' =>
								$this->msg( 'centralauth-centralautologin-logged-in' )
									->inLanguage( $code )->plain(),

							'centralautologin' =>
								$this->msg( 'centralautologin' )
									->inLanguage( $code )->plain(),
						]
					] );

				$script .= "\n" . self::getInlineScript( 'autologin.js' );

				// And for good measure, add the edge login HTML images to the page.
				$this->logger->debug( 'Edge login triggered in CentralAutoLogin' );
				// @phan-suppress-next-line SecurityCheck-DoubleEscaped
				$script .= "\n" . Html::encodeJsCall( "jQuery( 'body' ).append", [
						CentralAuthHooks::getEdgeLoginHTML()
					] );

				$this->doFinalOutput( true, 'success', $script );
				return;

			default:
				$this->setHeaders();
				$this->getOutput()->addWikiMsg( 'centralauth-centralautologin-desc' );
		}
	}

	/**
	 * Do a redirect hop to the target wiki and execute the appropriate sub-page
	 * endpoint. Target wikis are in the form of a wiki ID for example: 'loginwiki',
	 * 'enwiki', 'frwiki' etc.
	 *
	 * @param string $target Wiki ID or CENTRAL_DOMAIN_ID.
	 * @param string $stage The autologin stage (subpage name).
	 * @param array $params Query parameters to add.
	 *
	 * @phan-assert string $target
	 */
	private function do302Redirect( string $target, string $stage, array $params ) {
		$url = $this->centralDomainUtils->getUrl(
			$target, "Special:CentralAutoLogin/$stage", $this->getRequest(), $params
		);
		// expands to PROTO_CURRENT
		$this->getOutput()->redirect( $url );
	}

	/**
	 * @param bool $ok
	 * @param string $status
	 * @param string $type
	 */
	private function logFinished( $ok, $status, $type ): void {
		switch ( $this->subpage ) {
			// Extra steps, not part of the login process
			case 'toolslist':
			case 'refreshCookies':
			case 'deleteCookies':
				$this->logger->debug( "{$this->subpage} attempt", [
					'successful' => $ok,
					'status' => $status,
				] );
				break;

			// Login process
			default:
				if ( !in_array( $type, [ 'icon', '1x1', 'redirect', 'script', 'error' ] ) ) {
					// The type is included in metric names, so don't allow weird user-controlled values
					$type = 'unknown';
				}

				// Distinguish edge logins and autologins. Conveniently, all edge logins
				// (and only edge logins) set the otherwise mostly vestigial 'from' parameter,
				// and it's passed through all steps.
				$accountType = 'anon';
				if ( $this->getUser()->isRegistered() ) {
					$accountType = $this->getUser()->isNamed() ? 'named' : 'temp';
				}
				if ( $this->getRequest()->getCheck( 'from' ) ) {
					LoggerFactory::getInstance( 'authevents' )->info( 'Edge login attempt', [
						'event' => 'edgelogin',
						'successful' => $ok,
						'status' => $status,
						// Log this so that we can differentiate between:
						// - success page edge login (type=icon) [no longer used]
						// - next pageview edge login (type=1x1)
						'type' => $type,
						'accountType' => $accountType,
						'extension' => 'CentralAuth',
					] );
				} else {
					LoggerFactory::getInstance( 'authevents' )->info( 'Central autologin attempt', [
						'event' => 'centralautologin',
						'successful' => $ok,
						'status' => $status,
						// Log this so that we can differentiate between:
						// - top-level autologin (type=redirect)
						// - JS subresource autologin (type=script)
						// - no-JS subresource autologin (type=1x1) (likely rarely successful - check this)
						'type' => $type,
						'extension' => 'CentralAuth',
						'accountType' => $accountType,
					] );
				}
				break;
		}
	}

	/**
	 * @param bool $ok
	 * @param string $status
	 * @param string $body
	 * @param string $type
	 */
	private function doFinalOutput( $ok, $status, $body = '', $type = '' ) {
		$type = $type ?: $this->getRequest()->getVal( 'type', 'script' );
		'@phan-var string $type';

		if ( $type === 'redirect' ) {
			$returnUrlToken = $this->getRequest()->getVal( 'returnUrlToken', '' );
			$returnUrl = $this->tokenManager->detokenize(
				$returnUrlToken,
				'centralautologin-returnurl'
			);
			if ( $returnUrl === false ) {
				$type = 'error';
				$status = 'invalid returnUrlToken';
			}
		}

		$this->logFinished( $ok, $status, $type );

		$this->getOutput()->disable();
		wfResetOutputBuffers();
		$this->getOutput()->sendCacheControl();

		if ( $type === 'icon' || $type === '1x1' ) {
			header( 'Content-Type: image/png' );
			header( "X-CentralAuth-Status: $status" );

			if ( $ok && $this->getConfig()->get( CAMainConfigNames::CentralAuthLoginIcon ) && $type === 'icon' ) {
				readfile( $this->getConfig()->get( CAMainConfigNames::CentralAuthLoginIcon ) );
			} else {
				readfile( __DIR__ . '/../../images/1x1.png' );
			}
		} elseif ( $type === 'json' ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( "X-CentralAuth-Status: $status" );
			echo $body;
		} elseif ( $type === 'redirect' ) {
			$this->getRequest()->response()->statusHeader( 302 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( "X-CentralAuth-Status: $status" );
			// $returnUrl is always a string when $type==='redirect' but Phan can't figure it out
			'@phan-var string $returnUrl';
			$returnUrl = wfAppendQuery( $returnUrl, [
				SpecialPageBeforeExecuteHookHandler::AUTOLOGIN_ERROR_QUERY_PARAM => $status,
			] );
			header( "Location: $returnUrl" );
		} elseif ( $type === 'error' ) {
			// type=redirect but the redirect URL is invalid. Just display the error message.
			// This is poor UX (we might not even be on the wiki where the user started) but
			// shouldn't happen unless the request was tampered with.
			$this->getRequest()->response()->statusHeader( 400 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( "X-CentralAuth-Status: $status" );
			echo Html::element( 'p', [], $status );
			echo Html::rawElement( 'p', [],
				Html::element( 'a',
					[ 'href' => 'javascript:window.history.back()' ],
					$this->msg( 'centralauth-completelogin-back' )->text()
				)
			);
		} else {
			header( 'Content-Type: text/javascript; charset=utf-8' );
			echo "/* $status */\n$body";
		}
	}

	/**
	 * @param string|null $wikiId
	 *
	 * @return bool
	 */
	private function assertLocalWikiIsValid( ?string $wikiId ) {
		if ( $wikiId === null ) {
			$this->doFinalOutput( false, 'Missing wiki ID' );
			return false;
		}

		$wiki = WikiMap::getWiki( $wikiId );
		if ( !$wiki ) {
			$this->doFinalOutput( false, 'Specified local wiki not found' );
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	private function assertIsCentralDomain() {
		$isCentralDomain = $this->centralDomainUtils->isCentralDomain( $this->getRequest() );

		if ( !$isCentralDomain ) {
			$this->doFinalOutput( false, 'Not central wiki' );
			return $isCentralDomain;
		}

		return $isCentralDomain;
	}

	private function assertIsLocalDomain() {
		$isLocalDomain = !$this->centralDomainUtils->isCentralDomain( $this->getRequest() );

		if ( !$isLocalDomain ) {
			$this->doFinalOutput( false, 'Is central wiki, should be local' );
			return $isLocalDomain;
		}

		return $isLocalDomain;
	}

	/**
	 * @param string $caller
	 * @return array
	 */
	private function getCentralSession( string $caller ) {
		$centralSession = $this->sessionManager->getCentralSession( $this->session );

		// FIXME temporary logging for T372702
		//   eventually we should probably just return [] here and have the caller do error handling
		if ( !isset( $centralSession['sessionId'] ) ) {
			$this->logger->warning( 'No central session found', [
				'user' => $this->session->getUser()->getName(),
				'sessionId' => $this->session->getSessionId(),
				'centralSessionId' => $this->session->get( 'CentralAuth::centralSessionId' ),
				'persisted' => $this->session->isPersistent(),
				'remembered' => $this->session->shouldRememberUser(),
				'isCentral' => ( $this->session->getProviderMetadata()['CentralAuthSource'] ?? '' ) === 'CentralAuth',
				'caller' => $caller,
			] );
		}

		if ( !isset( $centralSession['remember'] ) ) {
			$centralSession['remember'] = false;
		}

		// Make sure there's a session id by creating a session if necessary.
		if ( !isset( $centralSession['sessionId'] ) ) {
			$centralSession['sessionId'] = $this->sessionManager->setCentralSession(
				$centralSession, false, $this->session );
		}

		return $centralSession;
	}
}
