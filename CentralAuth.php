<?php

/**
 * Database name you keep central auth data in.
 *
 * If this is not on the primary database connection, don't forget
 * to also set up $wgDBservers to have an entry with a groupLoads
 * setting for the 'CentralAuth' group.
 */
$wgCentralAuthDatabase = 'centralauth';


/**
 * If true, existing unattached accounts will be automatically migrated
 * if possible at first login.
 *
 * Any new account creations will be required to attach.
 *
 * If false, unattached accounts will not be harassed unless the individual
 * account has opted in to migration.
 */
$wgCentralAuthAutoMigrate = false;

/**
 * If true, remaining accounts which have not been attached will be forbidden
 * from logging in until they are resolved.
 */
$wgCentralAuthStrict = false;


/**
 * Initialization of the autoloaders, and special extension pages.
 */
$caBase = dirname( __FILE__ );
$wgAutoloadClasses['SpecialCentralAuth'] = "$caBase/SpecialCentralAuth.php";
$wgAutoloadClasses['SpecialMergeAccount'] = "$caBase/SpecialMergeAccount.php";
$wgAutoloadClasses['CentralAuthUser'] = "$caBase/CentralAuthUser.php";
$wgAutoloadClasses['CentralAuthPlugin'] = "$caBase/CentralAuthPlugin.php";
$wgAutoloadClasses['WikiMap'] = "$caBase/WikiMap.php";
$wgAutoloadClasses['WikiReference'] = "$caBase/WikiMap.php";

$wgExtensionFunctions[] = 'wfSetupCentralAuth';
$wgHooks['AuthPluginSetup'][] = 'wfSetupCentralAuthPlugin';
$wgHooks['AddNewAccount'][] = 'wfCentralAuthAddNewAccount';
$wgHooks['PreferencesUserInformationPanel'][] = 'wfCentralAuthInformationPanel';

$wgGroupPermissions['steward']['centralauth-admin'] = true;

$wgSpecialPages['CentralAuth'] = 'SpecialCentralAuth';
$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
}

function wfSetupCentralAuthPlugin( &$auth ) {
	$auth = new StubObject( 'wgAuth', 'CentralAuthPlugin' );
	return true;
}

/**
 * Add a little pretty to the preferences user info section
 */
function wfCentralAuthInformationPanel( $prefsForm, &$html ) {
	global $wgUser;
	$skin = $wgUser->getSkin();
	$special = SpecialPage::getTitleFor( 'MergeAccount' );
	
	
	// Possible states:
	// - account not merged at all
	// - global accounts exists, but this local account is unattached
	// - this local account is attached, but migration incomplete
	// - all local accounts are attached
	
	$global = new CentralAuthUser( $wgUser->getName() );
	if( $global->exists() ) {
		if( $global->isAttached() ) {
			// Local is attached...
			$attached = count( $global->listAttached() );
			$unattached = count( $global->listUnattached() );
			if( $unattached ) {
				// Migration incomplete
				$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-migration' ) . '</strong>' .
					'<br />' .
					wfMsgHtml( 'centralauth-prefs-count-attached', $attached ) .
					'<br />' .
					wfMsgHtml( 'centralauth-prefs-count-unattached', $unattached );
			} else {
				// Migration complete
				$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-complete' ) . '</strong>' .
					'<br />' .
					wfMsgHtml( 'centralauth-prefs-count-attached', $attached );
			}
		} else {
			// Account is in migration, but the local account is not attached
			$message = '<strong>' . wfMsgHtml( 'centralauth-prefs-unattached' ) . '</strong>' .
				'<br />' .
				wfMsgHtml( 'centralauth-prefs-detail-unattached' );
		}
	} else {
		// Not migrated.
		$message = wfMsgHtml( 'centralauth-prefs-not-managed' );
	}
	
	$manageLink = $skin->makeKnownLinkObj( $special,
		wfMsgHtml( 'centralauth-prefs-manage' ) );
	$html .= $prefsForm->tableRow(
		wfMsgHtml( 'centralauth-prefs-status' ),
		"$message<br />($manageLink)" );
	return true;
}

/**
 * Make sure migration information in localuser table is populated
 * on local account creation
 */
function wfCentralAuthAddNewAccount( $user ) {
	$central = new CentralAuthUser( $user );
	$central->lazyImportLocalNames();
	$central->storeMigrationData( $wgDBname, array( $user->getName() ) );
}
