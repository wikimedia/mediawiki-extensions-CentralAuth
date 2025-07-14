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

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use Exception;
use Generator;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @copyright © 2016 Wikimedia Foundation and contributors.
 */
class AttachAccount extends Maintenance {

	/** Starting microtimestamp */
	protected float $start;

	/** Number of accounts which weren't found */
	protected int $missing;

	/** Number of accounts which were partially attached (ie. some wikis succeeded, some failed) */
	protected int $partial;

	/** Number of accounts for which all attach attempts failed */
	protected int $failed;

	/** @var int */
	protected int $attached;

	/** @var int */
	protected int $ok;

	/** @var int */
	protected int $total;

	/** @var bool */
	protected bool $dryRun;

	/** @var bool */
	protected bool $quiet;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Attaches the specified usernames to a global account' );
		$this->start = microtime( true );
		$this->missing = 0;
		$this->partial = 0;
		$this->failed = 0;
		$this->attached = 0;
		$this->ok = 0;
		$this->total = 0;
		$this->dryRun = false;
		$this->quiet = false;

		$this->addOption( 'userlist',
			'File with the list of usernames to attach, one per line, on every possible wiki', false, true );
		$this->addOption( 'wiki-user-list',
			'Tab-separated file of whom/where to attach, wiki ID then username, one per line', false, true );
		$this->addOption( 'dry-run', 'Do not update database' );
		$this->addOption( 'quiet',
			'Only report database changes and final statistics' );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$this->dryRun = $this->hasOption( 'dry-run' );
		$this->quiet = $this->hasOption( 'quiet' );

		if ( $this->hasOption( 'wiki-user-list' ) ) {
			foreach ( $this->readFileByLine( $this->getOption( 'wiki-user-list' ) ) as $line ) {
				if ( count( explode( "\t", $line ) ) !== 2 ) {
					$this->fatalError( "ERROR: Invalid line in wiki-user-list: {$line}" );
				}
				[ $wiki, $username ] = array_map( 'trim', explode( "\t", $line ) );
				$this->attach( $username, $wiki );
				$this->maybeWaitForReplication();
			}
		} elseif ( $this->hasOption( 'userlist' ) ) {
			foreach ( $this->readFileByLine( $this->getOption( 'userlist' ) ) as $line ) {
				$username = trim( $line );
				$this->attach( $username );
				$this->maybeWaitForReplication();
			}
		} else {
			$this->fatalError( 'ERROR: Either --userlist or --wiki-user-list is required' );
		}

		$this->report();
		$this->output( "done.\n" );
	}

	/**
	 * @param string $username
	 * @param string|null $wiki Wiki ID, or null for all available wikis
	 */
	protected function attach( string $username, ?string $wiki = null ) {
		$this->total++;
		if ( !$this->quiet ) {
			$this->output( "CentralAuth account attach for: {$username}\n" );
		}

		$central = new CentralAuthUser(
			$username, IDBAccessObject::READ_LATEST );

		if ( !$central->exists() ) {
			$this->missing++;
			$this->output( "ERROR: No CA account found for: {$username}\n" );
			return;
		}

		try {
			$unattached = $central->listUnattached();
		} catch ( Exception ) {
			// This might happen due to localnames inconsistencies (T69350)
			$this->missing++;
			$this->output(
				"ERROR: Fetching unattached accounts for {$username} failed.\n"
			);
			return;
		}

		if ( count( $unattached ) === 0
			 || ( $wiki !== null && !in_array( $wiki, $unattached ) )
		) {
			$this->ok++;
			if ( !$this->quiet ) {
				$this->output( "OK: {$username}\n" );
			}
			return;
		}

		$wikisToAttach = $wiki !== null ? [ $wiki ] : $unattached;
		foreach ( $wikisToAttach as $wikiID ) {
			$this->output( "ATTACHING: {$username}@{$wikiID}\n" );
			if ( !$this->dryRun ) {
				$central->attach(
					$wikiID,
					'admin',
					false
				);
			}
		}

		if ( $this->dryRun ) {
			// Don't recheck if we aren't changing the db
			return;
		}

		$numUnattachedAfter = count( $central->listUnattached() );
		$numNewlyAttached = count( $unattached ) - $numUnattachedAfter;
		if ( $numNewlyAttached === 0 ) {
			$this->failed++;
			$this->output(
				"WARN: No accounts attached for {$username}; " .
				"({$numUnattachedAfter} unattached)\n" );
		} elseif ( $numNewlyAttached == count( $wikisToAttach ) ) {
			$this->attached++;
		} else {
			$this->partial++;
			$this->output(
				"INFO: Incomplete attachment for {$username}; " .
				"({$numUnattachedAfter} unattached)\n" );
		}
	}

	/**
	 * @param int|float $val
	 *
	 * @return float|int
	 */
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
			' failed: %d (%.1f%%);' .
			' missing: %d (%.1f%%);' .
			"\n";
		$this->output( sprintf( $format,
			wfTimestamp( TS_DB ),
			$this->total, $this->total / $delta,
			$this->ok, $this->reportPcnt( $this->ok ),
			$this->attached, $this->reportPcnt( $this->attached ),
			$this->partial, $this->reportPcnt( $this->partial ),
			$this->failed, $this->reportPcnt( $this->failed ),
			$this->missing, $this->reportPcnt( $this->missing )
		) );
	}

	/**
	 * @return Generator<string>
	 */
	private function readFileByLine( string $filename ): Generator {
		if ( !is_file( $filename ) ) {
			$this->fatalError( "ERROR - File not found: {$filename}" );
		}
		$file = fopen( $filename, 'r' );
		if ( $file === false ) {
			$this->fatalError( "ERROR - Could not open file: {$filename}" );
		}
		while ( true ) {
			$line = fgets( $file );
			if ( $line === false ) {
				break;
			}
			yield $line;
		}
		fclose( $file );
	}

	private function maybeWaitForReplication(): void {
		if ( $this->total % $this->mBatchSize === 0 ) {
			$this->waitForReplication();
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = AttachAccount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
