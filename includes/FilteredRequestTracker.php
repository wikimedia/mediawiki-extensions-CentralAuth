<?php

namespace MediaWiki\Extension\CentralAuth;

use LogicException;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Request\WebRequest;
use MWExceptionHandler;

/**
 * A trivial service to track whether authentication providers have been filtered in the current
 * request, used as shared state between SharedDomainHookHandler (which does the filtering, and
 * needs to know at the end of the authentication whether filtering was done, but can't easily
 * preserve that information through a multi-request authentication process) and
 * CentralAuthSharedDomainPreAuthenticationProvider (which can use AuthManager to preserve state
 * during an authentication flow).
 * @see \MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler
 * @see \MediaWiki\Extension\CentralAuth\CentralAuthSharedDomainPreAuthenticationProvider
 */
class FilteredRequestTracker {

	private const SESSION_KEY = 'CentralAuth.filtered';

	/** @var WebRequest|null The authentication request for which we disabled non-SUL3 local providers. */
	private ?WebRequest $filteredRequest = null;

	/**
	 * Mark the given request as using relaxed security due to the AuthManagerVerifyAuthentication hook.
	 * Note that on authentication requests this is called before the authentication process starts,
	 * and it can be called on non-authentication requests as well (initializing authentication
	 * providers is involved in e.g. skin logic).
	 */
	public function markRequestAsFiltered( WebRequest $request ): void {
		$this->filteredRequest = $request;
	}

	/**
	 * Store filtered state in the authentication session. Must be invoked inside the
	 * authentication flow.
	 */
	public function saveState( AuthManager $authManager ): void {
		$arePreviousRequestsFiltered = $authManager->getAuthenticationSessionData( self::SESSION_KEY, false );
		if ( !$arePreviousRequestsFiltered && $this->filteredRequest ) {
			$authManager->setAuthenticationSessionData( self::SESSION_KEY, true );
		} elseif ( $arePreviousRequestsFiltered && !$this->filteredRequest ) {
			// TODO This is probably possible if the user manipulates request data mid-authentication;
			//   maybe we should prevent that in a cleaner way. For now just log. The session flag
			//   will stick, so security-wise the AuthManagerVerifyAuthentication hook will
			//   handle it correctly.
			MWExceptionHandler::logException( new LogicException( 'Filter flag applied inconsistently' ) );
		}
	}

	/**
	 * Check whether the current authentication flow is using relaxed security due to the
	 * AuthManagerVerifyAuthentication hook (invoked in either the current request or a subsequent
	 * authentication step).
	 */
	public function isCurrentAuthenticationFlowFiltered( AuthManager $authManager ): bool {
		$arePreviousRequestsFiltered = $authManager->getAuthenticationSessionData( self::SESSION_KEY, false );
		if ( $this->filteredRequest && $authManager->getRequest() !== $this->filteredRequest ) {
			// Maybe a unit test where some but not all services got reset?
			// Should not happen during real authentication.
			MWExceptionHandler::logException( new LogicException(
				'Request changed between AuthManagerFilterProviders and AuthManagerVerifyAuthentication' ) );
		}
		return $this->filteredRequest || $arePreviousRequestsFiltered;
	}

	/**
	 * Reset the state of the tracker.
	 * @internal For use in tests only
	 */
	public function reset(): void {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new LogicException( 'reset() is for testing only' );
		}
		$this->filteredRequest = null;
	}

}
