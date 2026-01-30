<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use Generator;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\ScrambledPassword;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Password\InvalidPassword;

/**
 * "Scramble" (or unscramble) passwords for a given set of users. Users with scrambled passwords
 * cannot log in; when they try, they will get an error message explaining that they need to reset
 * their password. Scrambled passwords can be unscrambled to restore the original version.
 *
 * Optionally, also send these users an email
 *
 * @see ScrambledPassword
 */
class ScramblePassword extends Maintenance {

	/** @inheritDoc */
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Reversibly scramble passwords. Users will be asked to reset their password. '
			. 'The i18n message centralauth-scrambled-reason-<task> (centralauth-scrambled-noemail-reason-<task> '
			. 'for users without an email address) can be used to customize the message.' );
		$this->addOption( 'user', 'User name to act on', false, true );
		$this->addOption( 'users', 'Path to a file with one username per line', false, true );
		$this->addOption( 'task', 'Phabricator task ID', true, true );
		$this->addOption( 'undo', 'Unscramble instead of scrambling', false, false );
		$this->addOption( 'dry-run', 'Do not save changes / send email', false, false );
		$this->addOption( 'email-subject', 'Subject of the email to send to affected users', false, true );
		$this->addOption( 'email-body', 'Path to text file with the body of the email', false, true );
		$this->addOption( 'email-sender', 'Name of the user account sending the email '
			. '(will use system address otherwise)', false, true );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		$scrambled = 0;

		$this->validateOptions();

		$dbw = CentralAuthServices::getDatabaseManager()->getCentralPrimaryDB();
		foreach ( $this->getUserNameBatch() as $userNameBatch ) {
			$queryBuilder = CentralAuthServices::getGlobalUserSelectQueryBuilderFactory()
				->newGlobalUserSelectQueryBuilder();
			$queryBuilder->whereUserNames( array_values( $userNameBatch ) );
			$centralUsers = $queryBuilder->caller( __METHOD__ )->fetchCentralAuthUsers();

			$inputs = array_flip( $userNameBatch );
			foreach ( $centralUsers as $centralUser ) {
				unset( $inputs[ $centralUser->getName() ] );
				$scrambled += $this->handleUser( $centralUser );
			}

			foreach ( $inputs as $originalUserName ) {
				$this->error( "User not found: $originalUserName" );
			}

			$this->commitTransaction( $dbw, __METHOD__ );
		}

		if ( $this->hasOption( 'users' ) ) {
			if ( $this->hasOption( 'dry-run' ) ) {
				$action = $this->hasOption( 'undo' ) ? 'Would unscramble' : 'Would scramble';
			} else {
				$action = $this->hasOption( 'undo' ) ? 'Unscrambled' : 'Scrambled';
			}
			$this->output( "$action $scrambled users\n" );
		}
	}

	private function validateOptions(): void {
		if ( !preg_match( '/^[a-zA-Z0-9]+$/', $this->getOption( 'task' ) ) ) {
			$this->fatalError( 'Invalid task ID: ' . $this->getOption( 'task' ) );
		}

		if ( $this->hasOption( 'user' ) && $this->hasOption( 'users' ) ) {
			$this->fatalError( 'Cannot use --user and --users at the same time' );
		} elseif ( !$this->hasOption( 'user' ) && !$this->hasOption( 'users' ) ) {
			$this->fatalError( 'Either --user or --users is required' );
		} elseif ( $this->hasOption( 'users' ) ) {
			if ( !is_readable( $this->getOption( 'users' ) ) ) {
				$this->fatalError( 'File is not readable: ' . $this->getOption( 'users' ) );
			}
		}

		if ( $this->hasOption( 'email-subject' ) !== $this->hasOption( 'email-body' ) ) {
			$this->fatalError( '--email-subject and --email-body must be used together' );
		} elseif ( $this->hasOption( 'email-body' ) ) {
			if ( !is_readable( $this->getOption( 'email-body' ) ) ) {
				$this->fatalError( 'File is not readable: ' . $this->getOption( 'email-body' ) );
			}
		}
	}

	private function handleUser( CentralAuthUser $centralUser ): bool {
		$undo = $this->hasOption( 'undo' );
		$dryRun = $this->hasOption( 'dry-run' );
		$taskId = $this->getOption( 'task' );
		$userName = $centralUser->getName();
		$success = false;

		if ( $undo ) {
			if ( !$centralUser->hasScrambledPassword() ) {
				$this->error( "Can't unscramble password which is not scrambled for $userName" );
			} elseif ( $centralUser->getScrambledPasswordReason() !== $taskId ) {
				$this->error( "Won't unscramble password with task ID mismatch for $userName" );
			} else {
				if ( !$dryRun ) {
					$success = $this->unscramble( $centralUser );
					$action = $success ? 'Unscrambled' : 'Failed to unscramble';
				} else {
					$success = true;
					$action = 'Would unscramble';
				}
				$this->output( "$action password for $userName\n" );
			}
		} else {
			if ( $centralUser->hasScrambledPassword() ) {
				$this->error( "Can't scramble password which is already scrambled for $userName" );
			} elseif ( $centralUser->getPasswordObject() instanceof InvalidPassword ) {
				$this->output( "Skipping $userName with no password\n" );
			} else {
				if ( !$dryRun ) {
					$success = $this->scramble( $centralUser );
					$action = $success ? 'Scrambled' : 'Failed to scramble';
				} else {
					$success = true;
					$action = 'Would scramble';
				}
				$this->output( "$action password for $userName\n" );
			}
		}

		if ( $success && $this->hasOption( 'email-body' ) && !$this->hasOption( 'dry-run' ) ) {
			$userFactory = MediaWikiServices::getInstance()->getUserFactory();
			$user = $userFactory->newFromName( $userName );
			$sender = $this->hasOption( 'email-sender' )
				? $userFactory->newFromName( $this->getOption( 'email-sender' ) )
				: null;
			$subject = $this->getOption( 'email-subject' );
			$body = file_get_contents( $this->getOption( 'email-body' ) );
			if ( $body === false ) {
				$this->error( 'Could not load email body' );
			} else {
				$emailStatus = $user->sendMail( $subject, $body, $sender );
				if ( !$emailStatus->isOK() ) {
					$this->error( $emailStatus );
				}
			}
		}

		return $success;
	}

	private function scramble( CentralAuthUser $centralUser ): bool {
		$taskId = $this->getOption( 'task' );
		$success = $centralUser->scramblePassword( $taskId );

		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$sessionManager = MediaWikiServices::getInstance()->getSessionManager();
		$user = $userFactory->newFromName( $centralUser->getName() );
		if ( $user ) {
			$sessionManager->invalidateSessionsForUser( $user );
		}

		return $success;
	}

	private function unscramble( CentralAuthUser $centralUser ): bool {
		$taskId = $this->getOption( 'task' );
		return $centralUser->unscramblePassword( $taskId );
	}

	private function getUserNameBatch(): Generator {
		if ( $this->hasOption( 'user' ) ) {
			yield $this->normalizeUserNameBatch( [ $this->getOption( 'user' ) ] );
		} elseif ( $this->hasOption( 'users' ) ) {
			$userFile = $this->getOption( 'users' );
			$limit = $this->getBatchSize();
			$batch = [];
			foreach ( $this->readLines( $userFile ) as $line ) {
				$batch[] = $line;
				$limit--;
				if ( !$limit ) {
					yield $this->normalizeUserNameBatch( $batch );
					$batch = [];
					$limit = $this->getBatchSize();
				}
			}
			if ( $batch ) {
				yield $this->normalizeUserNameBatch( $batch );
			}
		}
	}

	private function readLines( string $pathToFile ): Generator {
		$handle = fopen( $pathToFile, 'rt' );
		if ( !$handle ) {
			$this->fatalError( "Could not open file $pathToFile" );
		}
		do {
			$line = fgets( $handle );
			if ( $line !== false ) {
				yield trim( $line );
			}
		} while ( $line !== false );
	}

	private function normalizeUserNameBatch( array $batch ): array {
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		$normalizedBatch = [];
		foreach ( $batch as $userName ) {
			$normalizedUserName = $userNameUtils->getCanonical( $userName );
			if ( $normalizedUserName === false ) {
				$this->error( "Invalid username: $userName" );
				continue;
			}
			$normalizedBatch[$userName] = $normalizedUserName;
		}
		return $normalizedBatch;
	}

}

// @codeCoverageIgnoreStart
$maintClass = ScramblePassword::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
