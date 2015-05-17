<?php
/**
 * Class containing updater functions for a CentralAuth environment
 */
class CentralAuthUpdaterHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		if ( $wgCentralAuthCluster !== false ) {
			// DatabaseUpdater does not support other databases, so skip
			return;
		}
		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {

			$updater->addExtensionTable( 'globalnames', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'localnames', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'globaluser', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'localuser', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'global_user_groups', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'global_group_permissions', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'wikiset', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'global_group_restrictions', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'renameuser_status', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'renameuser_queue', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionTable( 'users_to_rename', __DIR__ . "/central-auth.sql" );
			$updater->addExtensionField( 'globaluser', 'gu_auth_token', __DIR__ . "/db_patches/patch-globaluser_gu_authtoken.sql" );
			$updater->modifyExtensionField( 'globaluser', 'gu_hidden', __DIR__ . "/db_patches/patch-gu_hidden.sql" );
			$updater->addExtensionField( 'globaluser', 'gu_locked', __DIR__ . "/db_patches/patch-gu_hidden.sql" );
			$updater->addExtensionField( 'globaluser', 'gu_hidden', __DIR__ . "/db_patches/patch-gu_hidden.sql" );
		}
	}
}
