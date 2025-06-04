<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * This maintenance script is used to resend confirmation emails to users with
 * unattached accounts in the hopes that we will then be able to automatically
 * attach some unattached accounts.
 * We also override the contents of the email and send them to Special:MergeAccount
 * instead of Special:ConfirmEmail and thus put them right into the workflow to attach
 * accounts by password.
 *
 * THIS MUST BE RUN ON THE LOCAL WIKI SO THAT THE EMAIL IS SENT IN THE CORRECT LANGUAGE
 */
class SendConfirmAndMigrateEmail extends Maintenance {

	/**
	 * Whether to send to accounts with confirmed emails
	 * @var bool
	 */
	private $sendToConfirmed;

	/**
	 * How long to wait in between emails
	 *
	 * @var int
	 */
	private $sleep;

	/**
	 * @var bool
	 */
	private $dryrun;

	/**
	 * @var string|bool
	 */
	private $resume;

	/** @var float */
	protected $start;

	/** @var int */
	protected $sent;

	/** @var int */
	protected $total;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Resends the 'confirm your email address email' with a link to " .
			"Special:MergeAccount" );
		$this->start = microtime( true );
		$this->sent = 0;
		$this->total = 0;

		$this->addOption( 'userlist', 'List of usernames', false, true );
		$this->addOption( 'username', 'The user name to migrate', false, true, 'u' );
		$this->addOption( 'confirmed', 'Send email to confirmed accounts', false, false );
		$this->addOption( 'sleep',
			'How long to wait in between emails (default 1 second)', false, true );
		$this->addOption( 'dryrun', 'Don\'t actually send any emails', false, false );
		$this->addOption( 'resume', 'Which username to resume after', false, true );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$this->sendToConfirmed = $this->getOption( 'confirmed', false );
		$this->sleep = (int)$this->getOption( 'sleep', 1 );
		$this->dryrun = $this->hasOption( 'dryrun' );
		$this->resume = $this->getOption( 'resume', true );

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
			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			while ( $username = fgets( $file ) ) {
				// trim the \n
				$username = trim( $username );
				if ( $this->resume !== true ) {
					if ( $username === $this->resume ) {
						$this->resume = true;
					}
					continue;
				}
				$this->resendConfirmationEmail( $username );

				if ( $this->total % $this->mBatchSize == 0 ) {
					$this->output( "Waiting for replicas to catch up ... " );
					if ( !$this->dryrun ) {
						$this->waitForReplication();
					}
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

	/**
	 * @param string $username
	 */
	private function resendConfirmationEmail( $username ) {
		$wikiID = WikiMap::getCurrentWikiId();

		$this->total++;
		$this->output( "Sending confirmation email for: '$username@$wikiID'\n" );

		// we want to start with the local user
		$user = $this->getServiceContainer()
			->getUserFactory()
			->newFromName( $username );
		if ( $user === null ) {
			$this->output( "ERROR: $username is an invalid username\n" );
			return;
		}
		$user->load();

		if ( !$this->sendToConfirmed && $user->isEmailConfirmed() ) {
			$this->output(
				"ERROR: The user '$username@$wikiID' already has a confirmed email address\n"
			);
			return;
		}

		$central = CentralAuthUser::getInstance( $user );

		if ( !$central->exists() ) {
			$this->output( "ERROR: No global account for '$username'\n" );
			return;
		}
		if ( !$central->isAttached() ) {
			$this->output( "ERROR: '$username@$wikiID' is not attached to the global user\n" );
			return;
		}

		$unattached = $central->queryUnattached();
		if ( count( $unattached ) == 0 ) {
			$this->output( "ERROR: No unattached accounts for '$username'\n" );
			return;
		}

		if ( $this->dryrun ) {
			$this->output( "Would have sent email\n" );
			return;
		}

		if ( $this->sendConfirmAndMigrateMail( $user ) ) {
			$this->output( "Sent email to $username\n" );
			$this->sent++;
			sleep( $this->sleep );
		} else {
			$this->output(
				"ERROR: Sending confirm and migrate email failed for '$username@$wikiID'\n"
			);
		}
	}

	/**
	 * Generate a new e-mail confirmation token and send a confirmation/invalidation
	 * mail to the user's given address.
	 *
	 * @param User $user The user to send the confirmation email to
	 * @return Status
	 */
	private function sendConfirmAndMigrateMail( User $user ) {
		// we want this token to last a little bit longer (14 days) since we are cold-emailing
		// users and we really want as many responses as possible
		$tokenLife = 14 * 24 * 60 * 60;

		$token = $user->getConfirmationToken( $expiration, $tokenLife );

		if ( $user->isEmailConfirmed() ) {
			// Hack to bypass localization of 'Special:'
			// @see User::getTokenUrl
			$mergeAccountUrl = Title::makeTitle( NS_MAIN, 'Special:MergeAccount' )->getCanonicalURL();
		} else {
			// create a "token url" for MergeAccount since we have added email
			// confirmation there
			$mergeAccountUrl = $user->getTokenUrl( 'MergeAccount', $token );
		}

		$invalidateURL = $user->getInvalidationTokenUrl( $token );
		$user->saveSettings();

		return $user->sendMail(
			wfMessage( 'centralauth-finishglobaliseemail_subject' )->text(),
			wfMessage( "centralauth-finishglobaliseemail_body",
				$user->getRequest()->getIP(),
				$user->getName(),
				$mergeAccountUrl
			)->dateTimeParams(
				$expiration
			)->params(
				$invalidateURL
			)->dateParams(
				$expiration
			)->timeParams(
				$expiration
			)->text()
		);
	}

	private function report() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf(
			"%s: %s processed %d usernames (%.1f/sec), %d (%.1f%%) emails sent\n",
			WikiMap::getCurrentWikiId(),
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->sent,
			$this->total > 0 ? ( $this->sent / $this->total * 100.0 ) : 0
		) );
	}
}

// @codeCoverageIgnoreStart
$maintClass = SendConfirmAndMigrateEmail::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
