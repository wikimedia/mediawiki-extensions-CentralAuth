<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Status\Status;
use MWCryptRand;

class MigrateInitialAccountsTask extends \MediaWiki\Installer\Task\Task {

	/**
	 * @return string
	 */
	public function getName() {
		return 'centralauth-migrate-accounts';
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return '[CentralAuth] Globalizing initial user';
	}

	/**
	 * @return string[]
	 */
	public function getDependencies() {
		return [ 'created-user-names', 'extension-tables', 'services' ];
	}

	public function execute(): Status {
		$services = $this->getServices();
		$dbw = $services->getConnectionProvider()->getPrimaryDatabase( 'virtual-centralauth' );

		$names = $this->getContext()->getProvision( 'created-user-names' );
		if ( !$names ) {
			return Status::newGood();
		}

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
			->where( [ 'user_name' => $names ] )
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

		return Status::newGood();
	}
}
