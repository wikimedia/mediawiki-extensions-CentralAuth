<?php
/**
 * @section LICENSE
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
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * @copyright © 2016 Wikimedia Foundation and contributors.
 */
class AttachAccount extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription =
			"Attaches the specified usernames to a global account";
		$this->start = microtime( true );
		$this->missing = 0;
		$this->partial = 0;
		$this->attached = 0;
		$this->ok = 0;
		$this->total = 0;
		$this->dbBackground = null;
		$this->batchSize = 1000;
		$this->dryRun = false;
		$this->quiet = false;

		$this->addOption( 'userlist',
			'List of usernames to attach, one per line', true, true );
		$this->addOption( 'dry-run', 'Do not update database' );
		$this->addOption( 'quiet',
			'Only report database changes and final statistics' );
	}

	public function execute() {
		$this->dbBackground = CentralAuthUtils::getCentralSlaveDB();

		$this->dryRun = $this->hasOption( 'dry-run' );
		$this->quiet = $this->hasOption( 'quiet' );

		$list = $this->getOption( 'userlist' );
		if ( !is_file( $list ) ) {
			$this->output( "ERROR - File not found: {$list}" );
			exit( 1 );
		}
		$file = fopen( $list, 'r' );
		if ( $file === false ) {
			$this->output( "ERROR - Could not open file: {$list}" );
			exit( 1 );
		}
		while( strlen( $username = trim( fgets( $file ) ) ) ) {
			$this->attach( $username );
			if ( $this->total % $this->batchSize == 0 ) {
				$this->output( "Waiting for slaves to catch up ... " );
				CentralAuthUtils::waitForSlaves();
				$this->output( "done\n" );
			}
		}
		fclose( $file );

		$this->report();
		$this->output( "done.\n" );
	}

	protected function attach( $username ) {
		$this->total++;
		if ( !$this->quiet ) {
			$this->output( "CentralAuth account attach for: {$username}\n");
		}
		$central = new CentralAuthUser(
			$username, CentralAuthUser::READ_LATEST );
		try {
			$unattached = $central->queryUnattached();
		} catch ( Exception $e ) {
			// This might happen due to localnames inconsistencies (bug 67350)
			$this->output(
				"ERROR: Fetching unattached accounts for {$username} failed.\n"
			);
			return;
		}

		if ( count( $unattached ) === 0 ) {
			$this->ok++;
			if ( !$this->quiet ) {
				$this->output( "OK: {$username}\n" );
			}
			return;
		}

		if ( !$central->exists() ) {
			$this->missing++;
			$this->output( "ERROR: No CA account found for: {$username}\n" );
			return;
		}

		foreach ( $unattached as $wiki => $local ) {
			$this->output( "ATTACHING: {$username}@{$wiki}\n" );
			if ( !$this->dryRun ) {
				$central->attach( $wiki, 'login', false );
			}
		}

		if ( $this->dryRun ) {
			// Don't recheck if we aren't changing the db
			return;
		}

		$unattachedAfter = $central->queryUnattached();
		if ( count( $unattachedAfter ) === 0 ) {
			$this->attached++;
			return;
		} elseif ( count( $unattachedAfter ) > 0 &&
			count( $unattachedAfter ) < count( $unattached )
		) {
			$this->partial++;
			$this->output( "INFO: Incomplete migration for {$username}\n" );
		}
	}

	protected function reportPcnt( $val ) {
		if ( $this->total > 0 ) {
			return $val / $this->total * 100.0;
		}
		return 0;
	}

	protected function report() {
		$delta = microtime( true ) - $this->start;
		$format = '[%s]' .
			' processed: %d (%.1f/sec);' .
			' ok: %d (%.1f%%);' .
			' attached: %d (%.1f%%);' .
			' partial: %d (%.1f%%);' .
			' missing: %d (%.1f%%);' .
			"\n";
		$this->output( sprintf( $format,
			wfTimestamp( TS_DB ),
			$this->total,    $this->total / $delta,
			$this->ok,       $this->reportPcnt( $this->ok ),
			$this->attached, $this->reportPcnt( $this->attached ),
			$this->partial,  $this->reportPcnt( $this->partial ),
			$this->missing,  $this->reportPcnt( $this->missing )
		) );
	}
}

$maintClass = "AttachAccount";
require_once( RUN_MAINTENANCE_IF_MAIN );
