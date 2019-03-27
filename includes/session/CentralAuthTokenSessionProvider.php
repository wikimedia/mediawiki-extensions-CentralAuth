<?php

use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\UserInfo;

/**
 * Session provider for CentralAuth API centralauthtoken
 *
 * This session provider looks for the 'centralauthtoken' request parameter,
 * and checks that it corresponds to an existing token set up by
 * ApiCentralAuthToken. If the parameter is present but invalid, it returns a
 * bogus SessionInfo and hooks ApiBeforeMain to throw an appropriate exception
 * later when MediaWiki is ready to handle it.
 */
class CentralAuthTokenSessionProvider extends \MediaWiki\Session\SessionProvider {

	public function __construct( array $params = [] ) {
		global $wgHooks;

		parent::__construct();

		$wgHooks['APIGetAllowedParams'][] = $this;
		$wgHooks['BeforePageDisplay'][] = $this;
	}

	/**
	 * Throw an exception, later
	 *
	 * @param string $code Error code
	 * @param string|array $error Error message key, or key+parameters
	 * @return SessionInfo
	 */
	private function makeException( $code, $error ) {
		global $wgHooks;

		// First, schedule the throwing of the exception for later when the API
		// is ready to catch it
		$exception = \ApiUsageException::newWithMessage( null, $error, $code );
		$wgHooks['ApiBeforeMain'][] = function () use ( $exception ) {
			throw $exception;
		};

		// Then return an appropriate SessionInfo
		$id = $this->hashToSessionId( 'bogus' );
		return new SessionInfo( SessionInfo::MAX_PRIORITY, [
			'provider' => $this,
			'id' => $id,
			'userInfo' => UserInfo::newAnonymous(),
			'persisted' => false,
		] );
	}

	public function provideSessionInfo( WebRequest $request ) {
		global $wgHooks;

		// Only relevant in the API
		if ( !defined( 'MW_API' ) ) {
			return null;
		}

		$token = $request->getVal( 'centralauthtoken' );
		if ( $token === null ) {
			return null;
		}

		$this->logger->debug( __METHOD__ . ': Found a centralauthtoken!' );

		$sessionStore = CentralAuthUtils::getSessionStore();
		$key = CentralAuthUtils::memcKey( 'api-token', $token );
		$data = CentralAuthUtils::getKeyValueUponExistence( $sessionStore, $key );
		if ( !is_array( $data ) ||
			!isset( $data['userName'] ) ||
			!isset( $data['token'] ) ||
			!isset( $data['origin'] ) ||
			!isset( $data['originSessionId'] )
		) {
			$this->logger->debug( __METHOD__ . ': centralauthtoken is invalid' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}

		$userName = $data['userName'];
		$token = $data['token'];

		// Clean up username
		$userName = User::getCanonicalName( $userName, 'valid' );
		if ( !$userName ) {
			$this->logger->debug( __METHOD__ . ': invalid username' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}
		if ( !User::isUsableName( $userName ) ) {
			$this->logger->debug( __METHOD__ . ': unusable username' );
			return $this->makeException( 'badusername',
				[ 'apierror-centralauth-badusername', wfEscapeWikiText( $userName ) ] );
		}

		// Try the central user
		$centralUser = CentralAuthUser::getInstanceByName( $userName );

		// Skip if they're being renamed
		if ( $centralUser->renameInProgress() ) {
			$this->logger->debug( __METHOD__ . ': rename in progress' );
			return $this->makeException(
				'renameinprogress', 'apierror-centralauth-renameinprogress'
			);
		}

		if ( !$centralUser->exists() ) {
			$this->logger->debug( __METHOD__ . ': global account doesn\'t exist' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}
		if ( !$centralUser->isAttached() && User::idFromName( $userName ) ) {
			$this->logger->debug( __METHOD__ . ': not attached and local account exists' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}

		$key = CentralAuthUtils::memcKey( 'api-token-blacklist', $centralUser->getId() );
		if ( $sessionStore->get( $key ) ) {
			$this->logger->debug( __METHOD__ . ': user is blacklisted' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}

		if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
			$this->logger->debug( __METHOD__ . ': token mismatch' );
			return $this->makeException( 'badtoken', 'apierror-centralauth-badtoken' );
		}

		$this->logger->debug( __METHOD__ . ': logged in from session' );

		$info = [
			'userInfo' => UserInfo::newFromName( $userName, true ),
			'provider' => $this,
			'id' => $this->hashToSessionId( implode( "\n", $data ) ),
			'persisted' => true,
			'forceUse' => true,
		];

		// Delete the token once it's actually used
		$wgHooks['ApiCheckCanExecute'][] = $this;

		return new SessionInfo( SessionInfo::MAX_PRIORITY, $info );
	}

	public function persistsSessionId() {
		return false;
	}

	public function canChangeUser() {
		return false;
	}

	public function persistSession(
		MediaWiki\Session\SessionBackend $session, WebRequest $request
	) {
		// Nothing to do
	}

	public function unpersistSession( WebRequest $request ) {
		// Nothing to do
	}

	public function invalidateSessionsForUser( User $user ) {
		$centralUser = CentralAuthUser::getMasterInstance( $user );
		if ( $centralUser->exists() && ( $centralUser->isAttached() || $user->isAnon() ) ) {
			$centralUser->resetAuthToken();
		}
	}

	public function preventSessionsForUser( $username ) {
		$username = User::getCanonicalName( $username, 'valid' );
		if ( !$username ) {
			return;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		if ( !$centralUser->exists() ) {
			return;
		}

		// Assume blacklisting for a day will be enough because we assume by
		// then CentralAuth itself will have been instructed to more
		// permanently block the user.
		$sessionStore = CentralAuthUtils::getSessionStore();
		$key = CentralAuthUtils::memcKey( 'api-token-blacklist', $centralUser->getId() );
		$sessionStore->set( $key, true, $sessionStore::TTL_DAY, $sessionStore::WRITE_SYNC );
	}

	/**
	 * Inject the "centralauthtoken" parameter into the API
	 * @param ApiBase &$module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags
	 * @return bool
	 */
	public function onAPIGetAllowedParams( &$module, &$params, $flags ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( $module instanceof ApiMain ) {
			$params['centralauthtoken'] = [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_SENSITIVE => true,
			];
		}
		return true;
	}

	/**
	 * Consume the centralauthtoken
	 * @param ApiBase $module
	 * @param User $user
	 * @param array &$message Error message key and params
	 * @return bool
	 */
	public function onApiCheckCanExecute( $module, $user, &$message ) {
		$token = $module->getMain()->getVal( 'centralauthtoken' ); # Mark used
		$sessionStore = CentralAuthUtils::getSessionStore();
		$key = CentralAuthUtils::memcKey( 'api-token', $token );

		if ( !$sessionStore->changeTTL( $key, time() - 3600, $sessionStore::WRITE_SYNC ) ) {
			// Raced out trying to mark the token as expired
			$message = [ 'badtoken', 'apierror-centralauth-badtoken' ];

			return false;
		}

		return true;
	}

	/**
	 * Prevent user scripts and styles when centralauthtoken is in use
	 * @param OutputPage $out
	 * @return bool
	 */
	public function onBeforePageDisplay( $out ) {
		if ( $out->getRequest()->getSession()->getProvider()
			instanceof CentralAuthTokenSessionProvider
		) {
			$out->reduceAllowedModules(
				ResourceLoaderModule::TYPE_SCRIPTS, ResourceLoaderModule::ORIGIN_USER_SITEWIDE
			);
			$out->reduceAllowedModules(
				ResourceLoaderModule::TYPE_STYLES, ResourceLoaderModule::ORIGIN_USER_SITEWIDE
			);
		}
		return true;
	}

}
