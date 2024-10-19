<?php

namespace MediaWiki\Extension\CentralAuth\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MWCryptRand;

class MigrateInitialAccounts extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralAuth' );
		$this->addDescription( "Globalize all user accounts, assuming all default config options.\n" .
			"This script is intended to only be used during MediaWiki installation." );
	}

	public function execute() {
		if ( !defined( 'MEDIAWIKI_INSTALL' ) ) {
			$this->fatalError( "This script is intended to only be used during MediaWiki installation." );
		}

		// Assume that CentralAuth database is the same as the local wiki's database.
		$dbw = $this->getPrimaryDB();

		// Assume that no global users exist, and all local users can be migrated.
		// Assume that there are very few users, so we don't need batching.
		$users = $dbw->newSelectQueryBuilder()
			->from( 'user' )
			->select( [
				'user_id',
				'user_email',
				'user_name',
				'user_email_authenticated',
				'user_password',
				'user_registration',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// We're in the installer, so none of the CentralAuth code except for this script is loaded,
		// therefore we can't rely on any of our services or default values of config options.
		// Migrate the users manually using custom database queries.

		foreach ( $users as $user ) {
			$dbw->doAtomicSection( __METHOD__, static function ( $dbw, $fname ) use ( $user ) {
				global $wgDBname;

				// Unlike migratePass0/1, we don't need to store anything into globalnames and localnames,
				// these tables are only used for incomplete migrations and we're doing a complete one.

				// See CentralAuthUser::storeGlobalData()
				$dbw->newInsertQueryBuilder()
					->insertInto( 'globaluser' )
					->row( [
						'gu_name' => $user->user_name,
						'gu_home_db' => $wgDBname,
						'gu_email' => $user->user_email,
						'gu_email_authenticated' => $user->user_email_authenticated,
						'gu_password' => $user->user_password,
						'gu_locked' => 0,
						'gu_hidden_level' => 0,
						'gu_registration' => $user->user_registration,
						'gu_auth_token' => MWCryptRand::generateHex( 32 ),
					] )
					->caller( $fname )
					->execute();
				$gu_id = $dbw->insertId();

				// See CentralAuthUser::attach()
				$dbw->newInsertQueryBuilder()
					->insertInto( 'localuser' )
					->row( [
						'lu_wiki' => $wgDBname,
						'lu_name' => $user->user_name,
						'lu_attached_timestamp' => $user->user_registration,
						'lu_attached_method' => 'new',
						'lu_local_id' => $user->user_id,
						'lu_global_id' => $gu_id,
					] )
					->caller( $fname )
					->execute();

				// We don't handle AntiSpoof integration here, because it's complicated, and the
				// AntiSpoof extension doesn't handle the initial accounts either.
			} );
		}

		$count = count( $users );
		$this->output( "Globalized $count accounts\n" );
		return true;
	}
}
