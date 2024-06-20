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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

class BatchVanishUsers extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Vanish users that are in a CSV containing vanish requests.' );
		$this->addOption( 'data', 'Path to the file containing the vanish request data.', true, true, 'd' );
		$this->addOption( 'performer', 'Performer of the vanish action.', false, true, 'p' );
		$this->addOption( 'dry-run', 'Don\'t actually vanish the users, just report what it would do.' );
	}

	public function execute(): void {
		$csvPath = $this->getOption( 'data' );
		$performer = $this->getOption( 'performer' );
		$isDryRun = $this->getOption( 'dry-run' );

		$services = $this->getServiceContainer();
		if ( !$services->getCentralIdLookupFactory()->getNonLocalLookup() ) {
			$this->fatalError( 'This script cannot be run when CentralAuth is disabled.' );
		}

		$performerUser = null;
		if ( isset( $performer ) ) {
			$performerUser = CentralAuthUser::getInstanceByName( $performer );
			if ( $performerUser->getId() === 0 ) {
				$this->output( "Performer with username {$performer} cannot be found.\n" );
			}
		}

		// Load and parse CSV containing vanish requests from file.
		$handle = fopen( $csvPath, 'r' );
		if ( !$handle ) {
			$this->fatalError( "Unable to open vanish request data at provided path: {$csvPath}" );
		}
		$vanishRequests = $this->parseUserVanishRequests( $handle );
		fclose( $handle );

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

			if ( $this->requestUserVanish( $request, $performerUser ) ) {
				$successCount++;
			} else {
				$failureCount++;
			}
		}

		// Print success and failure counts.
		$this->output( "\nSucessfully submitted {$successCount} vanish requests.\n" );
		$this->output( "Failed to submit {$failureCount} vanish requests.\n" );
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
	 * @param CentralAuthUser|null $performer
	 * @return bool true if the vanish action was successful, and false otherwise
	 */
	private function requestUserVanish( array $request, ?CentralAuthUser $performer ): bool {
		$isDryRun = $this->getOption( 'dry-run', false );

		try {
			$username = $request['username'];
			$causer = CentralAuthUser::getInstanceByName( $username );
		} catch ( InvalidArgumentException $ex ) {
			$this->output( "Skipping user {$username} as that username is invalid.\n" );
			return false;
		}

		if ( !$causer->exists() || !$causer->isAttached() ) {
			$this->output( "Skipping user {$username} as there is no CentralAuth user with that username.\n" );
			return false;
		}

		$services = $this->getServiceContainer();
		$globalRenameRequestStore = $services->get( 'CentralAuth.GlobalRenameRequestStore' );

		if ( $globalRenameRequestStore->currentNameHasPendingRequest( $username ) ) {
			$this->output(
				"Skipping user {$username} as there is already a pending rename or vanish request for them.\n"
			);
			return false;
		}

		$parsedLink = parse_url( $request[ 'globalRenamersLink' ] ?? '', PHP_URL_QUERY );
		parse_str( $parsedLink, $globalRenamersQueryParams );
		$reason = urldecode( $globalRenamersQueryParams[ 'reason' ] ?? '' );
		$decodedNewName = urldecode( $globalRenamersQueryParams[ 'newname' ] ?? '' );
		$newName = $decodedNewName === '' ? null : $decodedNewName;

		// If new name couldn't be extracted, generate a random one
		// Format should be `Vanished user <some_random_string>`
		if ( !isset( $newName ) ) {
			$attempts = 0;
			do {
				$candidate = wfRandomString();
				if ( GlobalRenameRequest::isNameAvailable( $candidate )->isGood() ) {
					$newName = "Vanished user {$candidate}";
					$this->output( "New name not present in global_renamers_link. Generated '{$newName}' \n" );
				}
				$attempts++;
			} while ( !isset( $newName ) && $attempts < 5 );
		}

		if ( !isset( $newName ) ) {
			$this->output( "Skipping user {$username} as max attempts reached generating username.\n" );
			return false;
		}

		$request = $globalRenameRequestStore
			->newBlankRequest()
			->setName( $username )
			->setNewName( $newName )
			->setReason( $reason )
			->setComments( "Added automatically by maintenance/batchVanishUsers.php" )
			->setType( GlobalRenameRequest::VANISH );

		if ( isset( $performer ) ) {
			$request->setPerformer( $performer->getId() );
		}

		// Save the vanish request to the database as all validation has
		// passed, but only if we're not in dry run mode.
		if ( !$isDryRun && !$globalRenameRequestStore->save( $request ) ) {
			$this->output(
				"Skipping user {$username} as there was a problem saving the vanish request to the queue.\n"
			);
			return false;
		}

		return true;
	}

}

$maintClass = BatchVanishUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
