<?php

use MediaWiki\Session\SessionManager;
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

	public function __construct( array $params = array() ) {
		global $wgHooks;

		parent::__construct();

		$wgHooks['APIGetAllowedParams'][] = $this;
	}

	/**
	 * Throw an exception, later
	 *
	 * @param string $code Error code
	 * @param string $error Error message
	 * @returns SessionInfo
	 */
	private function makeException( $code, $error ) {
		global $wgHooks;

		// First, schedule the throwing of the exception for later when the API
		// is ready to catch it
		$exception = new \UsageException( $error, $code );
		$wgHooks['ApiBeforeMain'][] = function () use ( $exception ) {
			throw $exception;
		};

		// Then return an appropriate SessionInfo
		$id = $this->hashToSessionId( 'bogus' );
		return new SessionInfo( SessionInfo::MAX_PRIORITY, array(
			'provider' => $this,
			'id' => $id,
			'userInfo' => UserInfo::newAnonymous(),
			'persisted' => false,
		) );
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

		$key = CentralAuthUtils::memcKey( 'api-token', $token );
		$cache = CentralAuthUtils::getSessionCache();
		$data = $cache->get( $key );
		if ( !is_array( $data ) ||
			!isset( $data['userName'] ) ||
			!isset( $data['token'] ) ||
			!isset( $data['origin'] ) ||
			!isset( $data['originSessionId'] )
		) {
			$this->logger->debug( __METHOD__ . ': centralauthtoken is invalid' );
			return $this->makeException( 'badtoken', 'The centralauthtoken is not valid' );
		}

		$userName = $data['userName'];
		$token = $data['token'];

		// Clean up username
		$userName = User::getCanonicalName( $userName, 'valid' );
		if ( !$userName ) {
			$this->logger->debug( __METHOD__ . ': invalid username' );
			return $this->makeException( 'badtoken', 'The centralauthtoken is not valid' );
		}

		// Try the central user
		// Don't use CentralAuthUser::getInstance, we don't want to cache it on failure.
		$centralUser = new CentralAuthUser( $userName );

		// Skip if they're being renamed
		if ( $centralUser->renameInProgress() ) {
			$this->logger->debug( __METHOD__ . ': rename in progress' );
			return $this->makeException( 'renameinprogress', 'The user is being renamed, cannot use' );
		}

		if ( !$centralUser->exists() ) {
			$this->logger->debug( __METHOD__ . ': global account doesn\'t exist' );
			return $this->makeException( 'badtoken', 'The centralauthtoken is not valid' );
		}
		if ( !$centralUser->isAttached() && User::idFromName( $userName ) ) {
			$this->logger->debug( __METHOD__ . ': not attached and local account exists' );
			return parent::provideSessionInfo( $request );
		}

		$key = CentralAuthUtils::memcKey( 'api-token-blacklist', $centralUser->getId() );
		if ( $cache->get( $key ) ) {
			$this->logger->debug( __METHOD__ . ': user is blacklisted' );
			return $this->makeException( 'badtoken', 'The centralauthtoken is not valid' );
		}

		if ( $centralUser->authenticateWithToken( $token ) != 'ok' ) {
			$this->logger->debug( __METHOD__ . ': token mismatch' );
			return $this->makeException( 'badtoken', 'The centralauthtoken is not valid' );
		}

		$this->logger->debug( __METHOD__ . ': logged in from session' );

		$info = array(
			'userInfo' => UserInfo::newFromName( $userName, true ),
			'provider' => $this,
			'id' => $this->hashToSessionId( join( "\n", $data ) ),
			'persisted' => true,
		);

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

	public function persistSession( MediaWiki\Session\SessionBackend $session, WebRequest $request ) {
		// Nothing to do
	}

	public function unpersistSession( WebRequest $request ) {
		// Nothing to do
	}

	public function preventSessionsForUser( $username ) {
		$username = User::getCanonicalName( $username, 'valid' );
		if ( !$username ) {
			return;
		}

		$centralUser = new CentralAuthUser( $username );
		if ( !$centralUser->exists() ) {
			return;
		}

		// Assume blacklisting for a day will be enough because we assume by
		// then CentralAuth itself will have been instructed to more
		// permanently block the user.
		$cache = CentralAuthUtils::getSessionCache();
		$key = CentralAuthUtils::memcKey( 'api-token-blacklist', $centralUser->getId() );
		$cache->set( $key, true, 86400 );
	}

	/**
	 * Inject the "centralauthtoken" parameter into the API
	 * @param ApiBase &$module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags Flags
	 * @return bool
	 */
	public function onAPIGetAllowedParams( &$module, &$params, $flags ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( $module instanceof ApiMain && $flags ) {
			$params['centralauthtoken'] = array(
				ApiBase::PARAM_TYPE => 'string',
			);
		}
		return true;
	}

	/**
	 * Consume the centralauthtoken
	 * @param ApiBase $module API module
	 * @param User $user User
	 * @param array &$message Error message key and params
	 * @return bool
	 */
	public function onApiCheckCanExecute( $module, $user, &$message ) {
		$token = $module->getMain()->getVal( 'centralauthtoken' ); # Mark used
		$key = CentralAuthUtils::memcKey( 'api-token', $token );
		CentralAuthUtils::getSessionCache()->delete( $key );

		return true;
	}

}
