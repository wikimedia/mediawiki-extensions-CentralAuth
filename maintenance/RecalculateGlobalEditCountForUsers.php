<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;

/**
 * @author Clément Goubert
 */
class RecalculateGlobalEditCountForUsers extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription(
			"Re-calculate the global edit count for users provided.\n" .
			'Can use STDIN or a file argument'
		);
		$this->addArg(
			'file',
			'File with list of users to re-calculate global edit count for, one user per line',
			false
		);
	}

	public function execute() {
		if ( $this->hasArg( 0 ) ) {
			$file = fopen( $this->getArg( 0 ), 'r' );
		} else {
			$file = $this->getStdin();
		}

		// Setup
		if ( !$file ) {
			$this->fatalError( "Unable to read file, exiting" );
		}

		$editCounter = CentralAuthServices::getEditCounter( $this->getServiceContainer() );
		$handled = 0;
		$skipped = 0;

		for ( $linenum = 1; !feof( $file ); $linenum++ ) {
			$name = trim( fgets( $file ) );

			if ( $name === '' ) {
				continue;
			}

			try {
				$user = CentralAuthUser::getInstanceByName( $name );
			} catch ( \Exception $e ) {
				$this->output( "Warning: Could not find user '{$name}'\n" . $e->getMessage() );
				$skipped++;
				continue;
			}

			if ( !$user->exists() ) {
				$this->output( "Warning: User '{$name}' does not exist\n" );
				$skipped++;
				continue;
			}

			$oldCount = $editCounter->getCount( $user );
			$count = $editCounter->recalculate( $user );

			if ( $oldCount !== $count ) {
				$this->output( "Global edit count for '{$name}' updated from {$oldCount} to {$count}\n" );
			}

			$handled++;
		}

		$this->output( "Processed all {$handled} users!\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = RecalculateGlobalEditCountForUsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
