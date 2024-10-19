<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use InvalidArgumentException;
use MailAddress;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\UserIdentity;
use UserMailer;
use Wikimedia\Message\MessageSpecifier;

class BatchVanishUsers extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Vanish users that are in a CSV containing vanish requests.' );
		$this->addOption( 'data', 'Path to the file containing the vanish request data.', true, true, 'd' );
		$this->addOption( 'performer', 'Performer of the vanish action.', true, true, 'p' );
		$this->addOption( 'output', 'Path for the generated report. (default: output.csv)', false, true, 'o' );
		$this->addOption( 'dry-run', 'Don\'t actually vanish the users, just report what it would do.' );
	}

	public function execute(): void {
		$csvPath = $this->getOption( 'data' );
		$performer = $this->getOption( 'performer' );
		$isDryRun = $this->getOption( 'dry-run' );
		$outputPath = $this->getOption( 'output', 'output.csv' );

		$services = $this->getServiceContainer();
		if ( !$services->getCentralIdLookupFactory()->getNonLocalLookup() ) {
			$this->fatalError( 'This script cannot be run when CentralAuth is disabled.' );
		}

		$performerUser = CentralAuthUser::getInstanceByName( $performer );
		if ( $performerUser->getId() === 0 ) {
			$this->fatalError( "Performer with username {$performer} cannot be found.\n" );
		}
		// Fetching UserIdentity from performer because GlobalRenameFactory uses both CA and UI
		$performerIdentity = $services->getUserIdentityLookup()->getUserIdentityByName( $performer );
		// This error should never happen, we already found the CentralAuth performer
		if ( !$performerIdentity || !$performerIdentity->isRegistered() ) {
			$this->fatalError( "Performed with username {$performer} cannot be found in UserIdentityLookup. \n" );
		}

		// Load and parse CSV containing vanish requests from file.
		$handle = fopen( $csvPath, 'r' );
		if ( !$handle ) {
			$this->fatalError( "Unable to open vanish request data at provided path: {$csvPath}" );
		}
		$vanishRequests = $this->parseUserVanishRequests( $handle );
		fclose( $handle );

		$outputHandle = fopen( $outputPath, 'w' );
		if ( !$outputHandle ) {
			$this->fatalError( "Unable to create output file: {$outputPath}" );
		}
		if ( !fputcsv( $outputHandle, [ 'ticketId', 'result' ] ) ) {
			$this->fatalError( "Unable to write to output file: {$outputPath}" );
		}
		$vanishRequestCount = count( $vanishRequests );
		$successCount = 0;
		$failureCount = 0;

		// Iterate through all of the vanish requests and add them to the queue
		// one-by-one if they're valid.
		foreach ( $vanishRequests as $index => $request ) {
			$current = $index + 1;
			$messagePrefix = $isDryRun
				? "({$current}/{$vanishRequestCount}) (DRY RUN) "
				: "({$current}/{$vanishRequestCount}) ";
			$this->output( "{$messagePrefix}Submitting vanish request for user {$request['username']}\n" );

			$requestResult = $this->requestUserVanish( $request, $performerUser, $performerIdentity );

			if ( $requestResult[ 'success' ] ) {
				$successCount++;
			} else {
				fputcsv( $outputHandle, [ $request[ 'ticketLink' ], $requestResult[ 'message' ] ] );
				$failureCount++;
			}
		}

		fclose( $outputHandle );

		// Print success and failure counts.
		$this->output( "\nSucessfully submitted {$successCount} vanish requests.\n" );
		$this->output( "Failed to submit {$failureCount} vanish requests.\n" );
		$this->output( "Report produced - {$outputPath}\n" );
	}

	/**
	 * Parse a CSV file containing vanish requests.
	 *
	 * @param resource $handle file stream of a CSV with vanish requests
	 * @return array an array of valid vanish requests
	 */
	private function parseUserVanishRequests( $handle ): array {
		$vanishRequests = [];

		// Skip CSV header.
		$data = fgets( $handle );
		if ( $data === false ) {
			return $vanishRequests;
		}

		do {
			$data = fgetcsv( $handle, 4096, ',' );
			if ( $data !== false ) {
				$vanishRequests[] = [
					'createdDate' => $data[0],
					'ticketId' => $data[1],
					'ticketStatus' => $data[2],
					'requesterEmail' => $data[3],
					'ticketLink' => $data[4],
					'globalRenamersLink' => $data[5],
					'usernameLink' => $data[6],
					'username' => $data[7],
					'tags' => $data[8],
					'duplicateTickets' => $data[9],
				];
			}
		} while ( $data !== false );

		return $vanishRequests;
	}

	/**
	 * Submit a user vanish using provided information in the request.
	 *
	 * @param array $request
	 * @param CentralAuthUser $performer
	 * @param UserIdentity $uiPerformer
	 * @return array with keys:
	 *  - "success" (bool) if the vanish action was successful
	 *  - "message" (string) detail of the operation
	 */
	private function requestUserVanish( array $request, CentralAuthUser $performer, UserIdentity $uiPerformer ): array {
		$isDryRun = $this->getOption( 'dry-run', false );

		try {
			$username = $request['username'];
			$causer = CentralAuthUser::getInstanceByName( $username );
		} catch ( InvalidArgumentException $ex ) {
			$errorMessage = "Skipping user {$username} as that username is invalid.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "no-user" ];
		}

		if ( !$causer->exists() || !$causer->isAttached() ) {
			$errorMessage = "Skipping user {$username} as there is no CentralAuth user with that username.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "no-user" ];
		}

		// isBlocked() is an expensive operation
		// It is needed here and below to evaluate if the request is eligible for auto-vanish
		// Whatever change in this also impacts the condition for auto-vanish below
		$causerIsBlocked = $causer->isBlocked();
		if ( $causerIsBlocked ) {
			$errorMessage = "{$username} - has blocks.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "blocked" ];
		}

		$services = $this->getServiceContainer();
		$globalRenameRequestStore = $services->get( 'CentralAuth.GlobalRenameRequestStore' );

		if ( $globalRenameRequestStore->currentNameHasPendingRequest( $username ) ) {
			$errorMessage = "Skipping user {$username} - there is already a pending rename or vanish request for them.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "duplicate" ];
		}

		$globalRenamersQueryParams = null;
		$parsedLink = parse_url( $request[ 'globalRenamersLink' ] ?? '', PHP_URL_QUERY );
		parse_str( $parsedLink, $globalRenamersQueryParams );
		$reason = urldecode( $globalRenamersQueryParams[ 'reason' ] ?? '' );
		$decodedNewName = urldecode( $globalRenamersQueryParams[ 'newname' ] ?? '' );
		$newName = $decodedNewName === '' ? null : $decodedNewName;

		// If new name couldn't be extracted, generate a random one
		// Format should be `Renamed user <some_random_string>`
		if ( !isset( $newName ) ) {
			$attempts = 0;
			do {
				$candidate = wfRandomString();
				if ( GlobalRenameRequest::isNameAvailable( $candidate )->isGood() ) {
					$newName = "Renamed user {$candidate}";
					$this->output( "New name not present in global_renamers_link. Generated '{$newName}' \n" );
				}
				$attempts++;
			} while ( !isset( $newName ) && $attempts < 5 );
		}

		if ( !isset( $newName ) ) {
			$errorMessage = "Skipping user {$username} as max attempts reached generating username.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "error" ];
		}

		$request = $globalRenameRequestStore
			->newBlankRequest()
			->setName( $username )
			->setNewName( $newName )
			->setReason( $reason )
			->setComments( "Added automatically by maintenance/batchVanishUsers.php" )
			->setPerformer( $performer->getId() )
			->setType( GlobalRenameRequest::VANISH );

		// If request can be auto-vanished, don't add to the queue
		// - no edits, not blocked, and no logs
		if (
			$causer->getGlobalEditCount() === 0 &&
			// Commented because of lint, if causer has block(s) the function returns early (code above)
			// $causerIsBlocked === false &&
			!$causer->hasPublicLogs()
		) {
			if ( $isDryRun ) {
				return [ "success" => true, "message" => "dry-auto-vanished" ];
			}

			$globalRenameFactory = $services->get( 'CentralAuth.GlobalRenameFactory' );
			$requestArray = $request->toArray();

			// We need to add this two fields that are usually being provided by the Form
			$requestArray['movepages'] = true;
			$requestArray['suppressredirects'] = true;

			$renameResult = $globalRenameFactory
				->newGlobalRenameUser( $uiPerformer, $causer, $newName )
				->rename( $requestArray );
			if ( !$renameResult->isGood() ) {
				$errorMessage = "Skipping user {$username} as there was a problem in the auto-vanish process.";
				$this->output( $errorMessage . "\n" );
				return [ "success" => false, "message" => "error" ];
			}

			// We still want to leave a record that this happened, so change
			// the status over to 'approved' for the subsequent save.
			$request
				->setPerformer( $performer->getId() )
				->setComments( "Your username vanish request was processed successfully." )
				->setStatus( GlobalRenameRequest::APPROVED );

			// Save the request to the database for it to be processed later.
			if ( !$globalRenameRequestStore->save( $request ) ) {
				$errorMessage = "Skipping user {$username} as there was a problem in the auto-vanish process.";
				$this->output( $errorMessage . "\n" );
				return [ "success" => false, "message" => "error" ];
			}

			$this->sendVanishingSuccessfulEmail( $causer, $request );

			return [ "success" => true, "message" => "auto-vanished" ];
		}

		// Save the vanish request to the database as all validation has
		// passed, but only if we're not in dry run mode.
		if ( !$isDryRun && !$globalRenameRequestStore->save( $request ) ) {
			$errorMessage = "Skipping user {$username} as there was a problem saving the vanish request to the queue.";
			$this->output( $errorMessage . "\n" );
			return [ "success" => false, "message" => "error" ];
		}

		return [ "success" => true, "message" => "vanished" ];
	}

	/**
	 * Attempt to send a success email to the user whose vanish was fulfilled.
	 *
	 * TODO: https://phabricator.wikimedia.org/T369134 - refactor email sending
	 *
	 * @param CentralAuthUser $causer
	 * @param GlobalRenameRequest $request
	 * @return void
	 */
	private function sendVanishingSuccessfulEmail( CentralAuthUser $causer, GlobalRenameRequest $request ): void {
		$bodyKey = 'globalrenamequeue-vanish-email-body-approved-with-note';

		$subject = $this->msg( 'globalrenamequeue-vanish-email-subject-approved' );
		$body = $this->msg( $bodyKey, [ $request->getName(), $request->getComments() ] );

		$from = new MailAddress(
			$this->getConfig()->get( 'PasswordSender' ),
			$this->msg( 'emailsender' )
		);
		$to = new MailAddress( $causer->getEmail(), $causer->getName(), '' );

		// Users don't always have email addresses.
		if ( !$to->address ) {
			return;
		}

		// Attempt to send the email, and log an error if this fails.
		$emailSendResult = UserMailer::send( $to, $from, $subject, $body );
		if ( !$emailSendResult->isOK() ) {
			$this->output( $emailSendResult->getValue() . "\n" );
		}
	}

	/**
	 * Get translated messages.
	 *
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return string
	 */
	private function msg( $key, ...$params ): string {
		return wfMessage( $key, ...$params )->inLanguage( 'en' )->text();
	}

}

$maintClass = BatchVanishUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
