<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Locks a specific global account, or multiple accounts from a file.
 *
 * @author Taavi Väänänen <taavi@wikimedia.org>
 */
class LockUser extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addOption( 'username', 'User to act on', false, true );
		$this->addOption( 'file', 'File containing usernames to act on (one per line)', false, true );
		$this->addOption( 'actor', 'Username to log with', false, true );
		$this->addOption( 'reason', 'Reason to use', false, true );
		$this->addOption( 'bot', 'Mark as bot in RC', false, false );
		$this->addOption( 'unlock', 'Unlock instead of locking', false, false );
	}

	public function execute() {
		$username = $this->getOption( 'username' );
		$file = $this->getOption( 'file' );

		if ( !$username && !$file ) {
			$this->fatalError( "Either --username or --file must be provided" );
		}

		if ( $username && $file ) {
			$this->fatalError( "Cannot use both --username and --file" );
		}

		$context = $this->makeContext();

		if ( $file ) {
			$this->lockUsersFromFile( $file, $context );
		} else {
			$this->lockUser( $username, $context );
		}
	}

	/**
	 * Lock or unlock a single user.
	 *
	 * @param string $username Username to lock/unlock
	 * @param IContextSource $context
	 */
	private function lockUser( string $username, IContextSource $context ): void {
		$user = CentralAuthUser::getPrimaryInstanceByName(
			$this->getServiceContainer()
				->getUserNameUtils()
				->getCanonical( $username )
		);
		$username = $user->getName();

		if ( !$user->exists() ) {
			$this->error( "User '$username' does not exist" );
			return;
		}

		if ( $user->isLocked() && !$this->getOption( 'unlock', false ) ) {
			$this->error( "User '$username' is already locked" );
			return;
		}

		if ( !$user->isLocked() && $this->getOption( 'unlock', false ) ) {
			$this->error( "User '$username' is not locked" );
			return;
		}

		$status = $user->adminLockHide(
			/* setLocked */ !$this->getOption( 'unlock', false ),
			/* setHidden */ null,
			$this->getOption( 'reason', '' ),
			$context,
			$this->getOption( 'bot', false )
		);

		if ( $status->isGood() ) {
			$action = $this->getOption( 'unlock', false ) ? 'Unlocked' : 'Locked';
			$this->output( "$action user '$username'" . PHP_EOL );
		} else {
			$this->error( "Failed to " .
				( $this->getOption( 'unlock', false ) ? 'unlock' : 'lock' ) .
				" user '$username'"
			);
			$this->error( $status );
		}
	}

	/**
	 * Lock or unlock multiple users from a file.
	 *
	 * @param string $filePath Path to the file containing usernames (one per line)
	 * @param IContextSource $context
	 */
	private function lockUsersFromFile( string $filePath, IContextSource $context ): void {
		$lines = $this->readUsersFile( $filePath );
		if ( $lines === false ) {
			$this->fatalError( "Failed to read file '$filePath'" );
		}

		$this->output( "Processing " . count( $lines ) . " users" . PHP_EOL );

		foreach ( $lines as $username ) {
			$username = trim( $username );
			if ( $username === '' ) {
				continue;
			}
			$this->lockUser( $username, $context );
		}
	}

	/**
	 * Wrapper for the {@link file()} function to allow mocking in tests
	 */
	protected function readUsersFile( string $filePath ): array|false {
		return file( $filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	}

	private function makeContext(): IContextSource {
		$context = RequestContext::newExtraneousContext( Title::makeTitleSafe( NS_SPECIAL, 'Badtitle' ) );

		$username = $this->getOption( 'actor' );
		if ( $username ) {
			$user = $this->getServiceContainer()
				->getUserFactory()
				->newFromName( $username );

			if ( !$user || !$user->isRegistered() ) {
				$this->fatalError( "No user '$username' found!" );
			}

			'@phan-var User $user';
			$context->setUser( $user );
		} else {
			$context->setUser( User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ) );
		}

		$context->setAuthority( new UltimateAuthority( $context->getUser(), false ) );
		return $context;
	}
}

// @codeCoverageIgnoreStart
$maintClass = LockUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
