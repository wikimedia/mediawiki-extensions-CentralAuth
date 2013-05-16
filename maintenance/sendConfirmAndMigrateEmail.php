<?php

if ( PHP_SAPI != 'cli' ) {
	print "This script must be run from a shell";
	die();
}

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * Class SendConfirmAndMigrateEmail
 *
 * This maintenance script is used to resend confirmation emails to users with
 * unattached accounts in the hopes that we will then be able to automatically
 * attach some unattached accounts.  In addition, by using an EmailableUser object
 * we are able to override the contents of the email and send them to Special:MergeAccount
 * instead of Special:ConfirmEmail and thus put them right into the workflow to attach
 * accounts by password.
 *
 * THIS MUST BE RUN ON THE LOCAL WIKI SO THAT THE EMAIL IS SENT IN THE CORRECT LANGUAGE
 */
class SendConfirmAndMigrateEmail extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Resends the 'confirm your email address email' with a link to Special:MergeAccount";
		$this->start = microtime( true );
		$this->sent = 0;
		$this->total = 0;
		$this->batchSize = 1000;

		$this->addOption( 'userlist', 'List of usernames', false, true );
		$this->addOption( 'username', 'The user name to migrate', false, true, 'u' );
	}

	public function execute() {
		// check to see if we are processing a single username
		if ( $this->getOption( 'username', false ) !== false ) {
			$username = $this->getOption( 'username' );

			$this->resendConfirmationEmail( $username );

		} elseif ( $this->getOption( 'userlist', false ) !== false ) {
			$list = $this->getOption( 'userlist' );
			if ( !is_file( $list ) ) {
				$this->output( "ERROR - File not found: $list\n" );
				exit( 1 );
			}
			$file = fopen( $list, 'r' );
			if ( $file === false ) {
				$this->output( "ERROR - Could not open file: $list\n" );
				exit( 1 );
			}
			while( $username = fgets( $file ) ) {
				$username = trim( $username ); // trim the \n
				$this->resendConfirmationEmail( $username );

				if ( $this->total % $this->batchSize == 0 ) {
					$this->output( "Waiting for slaves to catch up ... " );
					wfWaitForSlaves( false );
					$this->output( "done\n" );
				}
			}
			fclose( $file );

		} else {
			$this->output( "ERROR - No username or list of usernames given\n" );
			exit( 1 );
		}

		$this->report();
		$this->output( "done.\n" );
	}

	function resendConfirmationEmail( $username ) {
		global $wgDBname;

		$this->total++;
		$this->output( "Sending confirmation email for: '$username@$wgDBname'\n" );

		// we want to start with the local user
		$user = EmailableUser::newFromName( $username );
		$user->load();

		if ( $user->isEmailConfirmed() ) {
			$this->output( "ERROR: The user '$username@$wgDBname' already has a confirmed email address\n" );
			return;
		}

		$central = CentralAuthUser::getInstance( $user );

		if ( !$central->exists() ) {
			$this->output( "ERROR: No global account for '$username'\n" );
			return;
		}
		if( !$central->isAttached() ) {
			$this->output( "ERROR: '$username@$wgDBname' is not attached to the global user\n" );
			return;
		}

		$unattached = $central->queryUnattached();
		if ( count( $unattached ) == 0 ) {
			$this->output( "ERROR: No unattached accounts for '$username'\n" );
			return;
		}

		if ( $user->sendConfirmAndMigrateMail() ) {
			$this->sent++;
		} else {
			$this->output( "ERROR: Sending confirm and migrate email failed for '$username@$wgDBname'\n" );
		}

	}

	function report() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf( "%s processed %d usernames (%.1f/sec), %d (%.1f%%) emails sent\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->sent,
			$this->total > 0 ? ( $this->sent / $this->total * 100.0 ) : 0
		) );
	}
}

$maintClass = "SendConfirmAndMigrateEmail";
require_once( RUN_MAINTENANCE_IF_MAIN );