<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Wikimedia\Rdbms\IDatabase;

class MigrateAccount extends Maintenance {

	/** @var int */
	protected $start;

	/** @var int */
	protected $partial;

	/** @var int */
	protected $migrated;

	/** @var int */
	protected $total;

	/** @var bool */
	protected $safe;

	/** @var IDatabase|null */
	protected $dbBackground;

	/** @var bool */
	protected $autoMigrate;

	/** @var bool */
	protected $resetToken;

	/** @var bool */
	protected $suppressRC;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( 'Migrates the specified usernames to a global account if email ' .
			'matches and there are no conflicts' );
		$this->start = microtime( true );
		$this->partial = 0;
		$this->migrated = 0;
		$this->total = 0;
		$this->safe = false;
		$this->dbBackground = null;
		$this->autoMigrate = false;
		$this->resetToken = false;
		$this->suppressRC = false;

		$this->addOption( 'auto',
			'Extended migration: ALWAYS create a global account for the username where missing ' .
			'and merge all the local accounts which match its email; the winner is picked using ' .
			'CentralAuthUser::attemptAutoMigration defaults, or forced to "homewiki" where ' .
			'specified by --userlist or --homewiki', false, false
		);
		$this->addOption( 'userlist',
			'List of usernames to migrate in the format username\thomewiki, where \thomewiki is ' .
			'optional and overrides the default winner if specified', false, true
		);
		$this->addOption( 'username', 'The username to migrate', false, true, 'u' );
		$this->addOption( 'homewiki',
			'The wiki to set as the homewiki. Can only be used with --username', false, true, 'h'
		);
		$this->addOption( 'safe',
			'Skip usernames used more than once across all wikis', false, false
		);
		$this->addOption( 'attachmissing',
			'Attach matching local accounts to an existing global account', false, false
		);
		$this->addOption( 'attachbroken',
			'Attach broken local accounts to the existing global account', false, false
		);
		$this->addOption( 'resettoken',
			'Allows for the reset of auth tokens in certain circumstances', false, false
		);
		$this->addOption( 'suppressrc', 'Do not send entries to RC feed', false, false );
		$this->setBatchSize( 1000 );
	}

	public function execute() {
		$this->dbBackground = CentralAuthUtils::getCentralReplicaDB();

		if ( $this->getOption( 'safe', false ) !== false ) {
			$this->safe = true;
		}
		if ( $this->getOption( 'auto', false ) !== false ) {
			$this->autoMigrate = true;
		}
		if ( $this->getOption( 'resettoken', false ) !== false ) {
			$this->resetToken = true;
		}

		if ( $this->getOption( 'suppressrc', false ) !== false ) {
			$this->suppressRC = true;
		}

		// Check to see if we are processing a single username
		if ( $this->getOption( 'username', false ) !== false ) {
			$username = $this->getOption( 'username' );
			$homewiki = $this->getOption( 'homewiki', null );
			$this->migrate( $username, $homewiki );

		} elseif ( $this->getOption( 'userlist', false ) !== false ) {
			$list = $this->getOption( 'userlist' );
			if ( !is_file( $list ) ) {
				$this->output( "ERROR - File not found: $list" );
				exit( 1 );
			}
			$file = fopen( $list, 'r' );
			if ( $file === false ) {
				$this->output( "ERROR - Could not open file: $list" );
				exit( 1 );
			}

			// @codingStandardsIgnoreStart
			while ( strlen( $line = trim( fgets( $file ) ) ) ) {
			// @codingStandardsIgnoreEnd
				$values = explode( "\t", $line );
				switch ( count( $values ) ) {
					case 1:
						$this->migrate( $values[0] );
						break;
					case 2:
						$this->migrate( $values[0], $values[1] );
						break;
					default:
						$this->output( "ERROR: Invalid account specification: '$line'\n" );
						break;
				}
				if ( $this->total % $this->mBatchSize == 0 ) {
					$this->output( "Waiting for slaves to catch up ... " );
					CentralAuthUtils::waitForSlaves();
					$this->output( "done\n" );
				}
			}
			fclose( $file );

		} else {
			$this->output( "ERROR - No username or list of usernames given\n" );
			exit( 1 );
		}

		$this->migratePassOneReport();
		$this->output( "done.\n" );
	}

	private function migrate( $username, $homewiki = null ) {
		$this->total++;
		$this->output( "CentralAuth account migration for: " . $username . "\n" );

		$central = new CentralAuthUser( $username, CentralAuthUser::READ_LATEST );
		try {
			$unattached = $central->queryUnattached();
		} catch ( Exception $e ) {
			// This might happen due to localnames inconsistencies (bug 67350)
			$this->output( "ERROR: Fetching unattached accounts for $username failed." );
			return;
		}

		/**
		 * Migration with an existing global account
		 */
		if ( $central->exists() ) {
			$this->output( "INFO: A global account already exists for: $username\n" );

			if (
				$this->getOption( 'attachmissing', false )
				&& !is_null( $central->getEmailAuthenticationTimestamp() )
			) {
				foreach ( $unattached as $wiki => $local ) {
					if (
						$central->getEmail() === $local['email']
						&& !is_null( $local['emailAuthenticated'] )
					) {
						$this->output( "ATTACHING: $username@$wiki\n" );
						$central->attach( $wiki, 'mail', /** $sendToRC = */ !$this->suppressRC );
					}
				}
			}

			if ( $this->getOption( 'attachbroken', false ) ) {
				// This option is for bug 61876 / bug 39996 where the account has
				// an empty password and email set, and became unattached.
				// Since there is no way an account can have an empty password manually
				// it has to be due to a CentralAuth bug. So just attach it then.
				// But just to be on the safe side, check that it also has 0 edits.
				foreach ( $unattached as $wiki => $local ) {
					if ( $local['email'] === '' && $local['password'] === ''
						&& $local['editCount'] === '0'
					) {
						$this->output( "ATTACHING: $username@$wiki\n" );
						// Ironically, the attachment is made due to lack of a password.
						$central->attach(
							$wiki, 'password', /** $sendToRC = */ !$this->suppressRC
						);
					}
				}
			}
		} else {
			/**
			 * Migration without an existing global account
			 */
			if ( count( $unattached ) == 0 ) {
				$this->output( "ERROR: No local accounts found for: $username\n" );
				return;
			}

			if ( $this->safe && count( $unattached ) !== 1 ) {
				$this->output(
					"ERROR: More than 1 local user account found for username: $username\n"
				);
				foreach ( $unattached as $local ) {
					$this->output( "\t" . $central->getName() . "@" . $local['wiki'] . "\n" );
				}
				return;
			}

			if ( $homewiki !== null ) {
				if ( !array_key_exists( $homewiki, $unattached ) ) {
					$this->output( "ERROR: Unattached user not found for $username@$homewiki\n" );
					return;
				}
				$this->output( "INFO: Setting homewiki for '$username' to $homewiki\n" );
				$central->mHomeWiki = $homewiki;
			}

			// Check that all unattached (i.e. ALL) accounts have a confirmed email
			// address and that the addresses are all the same. We are using this
			// to match accounts to the same user, since we can't use the password.
			$emailMatch = true;
			$email = null;
			foreach ( $unattached as $local ) {
				if ( is_null( $email ) ) {
					$email = $local['email'];
				}
				if ( $local['email'] === $email && !is_null( $local['emailAuthenticated'] ) ) {
					continue;
				}
				$emailMatch = false;
				break;
			}

			// All of the emails are the same and confirmed? Merge all the accounts.
			// They aren't? Skip, or merge the winner if --auto was specified.
			if ( $emailMatch ) {
				$this->output( "Email addresses match and are confirmed for: $username\n" );
				$central->storeAndMigrate( [], !$this->suppressRC );
			} else {
				if ( isset( $central->mHomeWiki ) || $this->autoMigrate ) {
					$central->storeAndMigrate( [], !$this->suppressRC );
				} else {
					$this->output( "ERROR: Auto migration is disabled and email addresses do " .
						"not match for: $username\n" );
				}
			}
		}
		$unattachedAfter = $central->queryUnattached();

		if ( count( $unattachedAfter ) == 0 ) {
			$this->migrated++;
			return;
		} elseif ( count( $unattachedAfter ) > 0 &&
			count( $unattachedAfter ) < count( $unattached )
		) {
			$this->partial++;
			$this->output( "INFO: Incomplete migration for '$username'\n" );
		}
		if ( $this->resetToken ) {
			$this->output( "INFO: Resetting CentralAuth auth token for '$username'\n" );
			$central->resetAuthToken();
		}
	}

	private function migratePassOneReport() {
		$delta = microtime( true ) - $this->start;
		$this->output( sprintf(
			"%s processed %d usernames (%.1f/sec), %d (%.1f%%) fully migrated, %d (%.1f%%) " .
				"partially migrated\n",
			wfTimestamp( TS_DB ),
			$this->total,
			$this->total / $delta,
			$this->migrated,
			$this->total > 0 ? ( $this->migrated / $this->total * 100.0 ) : 0,
			$this->partial,
			$this->total > 0 ? ( $this->partial / $this->total * 100.0 ) : 0
		) );
	}
}

$maintClass = MigrateAccount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
