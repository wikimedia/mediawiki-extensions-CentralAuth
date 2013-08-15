<?php
/**
 * Extension credits
 */
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Central Auth',
	'url' => 'https://www.mediawiki.org/wiki/Extension:CentralAuth',
	'author' => 'Brion Vibber',
	'descriptionmsg' => 'centralauth-desc',
);

$wgExtensionCredits['specialpage'][] = array(
	'path'           => __FILE__,
	'name'           => 'MergeAccount',
	'author'         => 'Brion Vibber',
	'url'            => '//meta.wikimedia.org/wiki/Help:Unified_login',
	'descriptionmsg' => 'centralauth-mergeaccount-desc',
);

/**
 * Database name you keep central auth data in.
 *
 * If this is not on the primary database connection, don't forget
 * to also set up $wgDBservers to have an entry with a groupLoads
 * setting for the 'CentralAuth' group. Alternatively you can use
 * $wgLBFactoryConf to set up an LBFactory_Multi object.
 *
 * To use a database with a table prefix, set this variable to
 * "{$database}-{$prefix}".
 */
$wgCentralAuthDatabase = 'centralauth';

/**
 * If true, new account registrations will be registered globally if
 * the username hasn't been used elsewhere.
 */
$wgCentralAuthAutoNew = false;

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
 * If true, merging won't actually be possible through the Special:MergeAccount
 * interface.
 */
$wgCentralAuthDryRun = false;

/**
 * If true, global session and token cookies will be set alongside the
 * per-wiki session and login tokens when users log in with a global account.
 * This allows other wikis on the same domain to transparently log them in.
 */
$wgCentralAuthCookies = false;

/**
 * Database name of a central login wiki. This is an alternative to directly setting
 * cross-domain cookies for each wiki in $wgCentralAuthAutoLoginWikis. If set, a single
 * login wiki will use a session/cookie to handle unified login sessions across wikis.
 *
 * On login, users will be redirected to the login wiki's Special:CentralLogin/login
 * page and then redirected to Special:CentralLogin back on the originating wiki.
 * In the process, the central login wiki cookie and session will be set.
 * As the user accesses other wikis, the login wiki will be checked via JavaScript
 * to check login status and set the local session and cookies.
 *
 * This requires $wgCentralAuthCookies.
 */
$wgCentralAuthLoginWiki = false;

/**
 * Domain to set global cookies for.
 * For instance, '.wikipedia.org' to work on all wikipedia.org subdomains
 * instead of just the current one.
 *
 * Leave blank to set the cookie for the current domain only, such as if
 * all your wikis are hosted on the same subdomain.
 */
$wgCentralAuthCookieDomain = '';

/**
 * Prefix for CentralAuth global authentication cookies.
 */
$wgCentralAuthCookiePrefix = 'centralauth_';

/**
 * List of wiki IDs which should be called on login to try to set third-party
 * cookies for the global session state.
 *
 * The wiki ID is typically the database name, except when table prefixes are
 * used, in which case it is the database name, a hyphen separator, and then
 * the table prefix.
 *
 * This allows a farm with multiple second-level domains to set up a global
 * session on all of them by hitting one wiki from each domain
 * (en.wikipedia.org, en.wikinews.org, etc).
 *
 * Done by accessing Special:CentralAutoLogin/start on each wiki.
 *
 * If empty, no other wikis will be hit.
 *
 * The key should be set to the cookie domain name.
 */
$wgCentralAuthAutoLoginWikis = array();

/**
 * Whether login should be done silently (with 302 redirects)
 * Note this may eventually be defaulted to true and removed.
 */
$wgCentralAuthSilentLogin = false;

/**
 * Local filesystem path to the icon returned by Special:CentralAutoLogin
 * Should be a 20x20px PNG.
 */
$wgCentralAuthLoginIcon = false;

/**
 * Set this true until all wikis are running a version with
 * Special:CentralAutoLogin/start available.
 */
$wgCentralAuthUseOldAutoLogin = true;

/**
 * User preferences for which we should recommend reloading the page after
 * a successful central login query.
 *
 * If you need to do something more complicated than just
 * $user->getOption( $pref ) !== User::getDefaultOption( $pref ), use the hook
 * CentralAuthIsUIReloadRecommended.
 */
$wgCentralAuthPrefsForUIReload = array(
	'skin',
	'language',
	'thumbsize',
	'underline',
	'stubthreshold',
	'showhiddencats',
	'justify',
	'numberheadings',
	'editondblclick',
	'editsection',
	'editsectiononrightclick',
	'usenewrc',
	'extendwatchlist',
);

/**
 * Specify a P3P header value to be used when setting CentralAuth cookies on
 * the login wiki ($wgCentralAuthLoginWiki).
 *
 * When set true, a invalid policy (lacking all required tokens) will be sent
 * that none the less serves to allow current versions of IE with the default
 * privacy settings to see the cookies in the auto-login check.
 *
 * Set false to disable sending the P3P header altogether. Note this will
 * likely break the auto-login check in IE, unless the header is being set
 * globally elsewhere (e.g. in the webserver).
 *
 * Otherwise, whatever string is assigned here will be sent as the value of the
 * P3P header.
 *
 * @var bool|string
 */
$wgCentralAuthCookiesP3P = true;

/**
 * If true, local accounts will be created for active global sessions
 * on any page view. This is kind of creepy, so we're gonna have it off
 * for a little bit.
 *
 * With other default options, the local autocreation will be held off
 * until an active login attempt, while global sessions will still
 * automatically log in those who already have a merged account.
 */
$wgCentralAuthCreateOnView = false;

/**
 * Settings for sending the CentralAuth events to the RC-to-UDP system
 */
$wgCentralAuthUDPAddress = false;
$wgCentralAuthNew2UDPPrefix = '';

/**
 * List of local pages global users may edit while being globally locked.
 */
$wgCentralAuthLockedCanEdit = array();

/**
 * Disable editing for non-global accounts (except on NS_USER_TALK and NS_PROJECT_TALK)
 */
$wgDisableUnmergedEditing = false;

/**
 * Size of wikis handled in one suppress user job.
 * Keep in mind that one wiki requires ~10 queries.
 */
$wgCentralAuthWikisPerSuppressJob = 10;

/**
 * Like $wgReadOnly, used to set extension to database read only mode
 * @var bool
 */
$wgCentralAuthReadOnly = false;

/**
 * Use the EventLogging extension to measure various activities
 */
$wgCentralAuthUseEventLogging = false;

/**
 * Initialization of the autoloaders, and special extension pages.
 */
$caBase = __DIR__;
$wgAutoloadClasses['SpecialCentralAuth'] = "$caBase/specials/SpecialCentralAuth.php";
$wgAutoloadClasses['SpecialCentralLogin'] = "$caBase/specials/SpecialCentralLogin.php";
$wgAutoloadClasses['SpecialMergeAccount'] = "$caBase/specials/SpecialMergeAccount.php";
$wgAutoloadClasses['SpecialGlobalUsers'] = "$caBase/specials/SpecialGlobalUsers.php";
$wgAutoloadClasses['SpecialMultiLock'] = "$caBase/specials/SpecialMultiLock.php";
$wgAutoloadClasses['CentralAuthUser'] = "$caBase/CentralAuthUser.php";
$wgAutoloadClasses['CentralAuthPlugin'] = "$caBase/CentralAuthPlugin.php";
$wgAutoloadClasses['CentralAuthHooks'] = "$caBase/CentralAuthHooks.php";
$wgAutoloadClasses['CentralAuthSuppressUserJob'] = "$caBase/SuppressUserJob.php";
$wgAutoloadClasses['WikiSet'] = "$caBase/WikiSet.php";
$wgAutoloadClasses['SpecialAutoLogin'] = "$caBase/specials/SpecialAutoLogin.php";
$wgAutoloadClasses['SpecialCentralAutoLogin'] = "$caBase/specials/SpecialCentralAutoLogin.php";
$wgAutoloadClasses['CentralAuthUserArray'] = "$caBase/CentralAuthUserArray.php";
$wgAutoloadClasses['CentralAuthUserArrayFromResult'] = "$caBase/CentralAuthUserArray.php";
$wgAutoloadClasses['SpecialGlobalGroupMembership'] = "$caBase/specials/SpecialGlobalGroupMembership.php";
$wgAutoloadClasses['CentralAuthGroupMembershipProxy'] = "$caBase/CentralAuthGroupMembershipProxy.php";
$wgAutoloadClasses['SpecialGlobalGroupPermissions'] = "$caBase/specials/SpecialGlobalGroupPermissions.php";
$wgAutoloadClasses['SpecialWikiSets'] = "$caBase/specials/SpecialWikiSets.php";
$wgAutoloadClasses['ApiQueryGlobalUserInfo'] = "$caBase/api/ApiQueryGlobalUserInfo.php";
$wgAutoloadClasses['ApiDeleteGlobalAccount'] = "$caBase/api/ApiDeleteGlobalAccount.php";
$wgAutoloadClasses['ApiSetGlobalAccountStatus'] = "$caBase/api/ApiSetGlobalAccountStatus.php";
$wgAutoloadClasses['ApiQueryGlobalGroups'] = "$caBase/api/ApiQueryGlobalGroups.php";
$wgAutoloadClasses['ApiQueryWikiSets'] = "$caBase/api/ApiQueryWikiSets.php";
$wgAutoloadClasses['ApiQueryGlobalAllUsers'] = "$caBase/api/ApiQueryGlobalAllUsers.php";
$wgAutoloadClasses['CentralAuthReadOnlyError'] = "$caBase/CentralAuthReadOnlyError.php";

// only used by maintenance/sendConfirmAndMigrateEmail.php
$wgAutoloadClasses['EmailableUser'] = "$caBase/EmailableUser.php";

$wgExtensionMessagesFiles['SpecialCentralAuth'] = "$caBase/CentralAuth.i18n.php";
$wgExtensionMessagesFiles['SpecialCentralAuthAliases'] = "$caBase/CentralAuth.alias.php";

$wgJobClasses['crosswikiSuppressUser'] = 'CentralAuthSuppressUserJob';

$wgHooks['SetupAfterCache'][] = 'CentralAuthHooks::onSetupAfterCache';
$wgHooks['AuthPluginSetup'][] = 'CentralAuthHooks::onAuthPluginSetup';
$wgHooks['AddNewAccount'][] = 'CentralAuthHooks::onAddNewAccount';
$wgHooks['GetPreferences'][] = 'CentralAuthHooks::onGetPreferences';
$wgHooks['AbortNewAccount'][] = 'CentralAuthHooks::onAbortNewAccount';
$wgHooks['UserLoginComplete'][] = 'CentralAuthHooks::onUserLoginComplete';
$wgHooks['UserLoadFromSession'][] = 'CentralAuthHooks::onUserLoadFromSession';
$wgHooks['UserLogout'][] = 'CentralAuthHooks::onUserLogout';
$wgHooks['GetCacheVaryCookies'][] = 'CentralAuthHooks::onGetCacheVaryCookies';
$wgHooks['UserArrayFromResult'][] = 'CentralAuthHooks::onUserArrayFromResult';
$wgHooks['UserGetEmail'][] = 'CentralAuthHooks::onUserGetEmail';
$wgHooks['UserGetEmailAuthenticationTimestamp'][] = 'CentralAuthHooks::onUserGetEmailAuthenticationTimestamp';
$wgHooks['UserInvalidateEmailComplete'][] = 'CentralAuthHooks::onUserInvalidateEmailComplete';
$wgHooks['UserSetEmail'][] = 'CentralAuthHooks::onUserSetEmail';
$wgHooks['UserSaveSettings'][] = 'CentralAuthHooks::onUserSaveSettings';
$wgHooks['UserSetEmailAuthenticationTimestamp'][] = 'CentralAuthHooks::onUserSetEmailAuthenticationTimestamp';
$wgHooks['UserGetRights'][] = 'CentralAuthHooks::onUserGetRights';
$wgHooks['UserSetCookies'][] = 'CentralAuthHooks::onUserSetCookies';
$wgHooks['UserLoadDefaults'][] = 'CentralAuthHooks::onUserLoadDefaults';
$wgHooks['getUserPermissionsErrorsExpensive'][] = 'CentralAuthHooks::onGetUserPermissionsErrorsExpensive';
$wgHooks['MakeGlobalVariablesScript'][] = 'CentralAuthHooks::onMakeGlobalVariablesScript';
$wgHooks['SpecialPasswordResetOnSubmit'][] = 'CentralAuthHooks::onSpecialPasswordResetOnSubmit';
$wgHooks['OtherBlockLogLink'][] = 'CentralAuthHooks::getBlockLogLink';
$wgHooks['BeforePageDisplay'][] = 'CentralAuthHooks::onBeforePageDisplay';
$wgHooks['ApiTokensGetTokenTypes'][] = 'ApiDeleteGlobalAccount::injectTokenFunction';
$wgHooks['ApiTokensGetTokenTypes'][] = 'ApiSetGlobalAccountStatus::injectTokenFunction';
$wgHooks['ApiTokensGetTokenTypes'][] = 'CentralAuthHooks::onApiTokensGetTokenTypes';
$wgHooks['APIGetAllowedParams'][] = 'CentralAuthHooks::onAPIGetAllowedParams';
$wgHooks['APIGetParamDescription'][] = 'CentralAuthHooks::onAPIGetParamDescription';
$wgHooks['ApiCheckCanExecute'][] = 'CentralAuthHooks::onApiCheckCanExecute';

// For interaction with the Special:Renameuser extension
$wgHooks['RenameUserWarning'][] = 'CentralAuthHooks::onRenameUserWarning';
$wgHooks['RenameUserPreRename'][] = 'CentralAuthHooks::onRenameUserPreRename';
$wgHooks['RenameUserComplete'][] = 'CentralAuthHooks::onRenameUserComplete';

// For interaction with the Special:AbuseFilter extension
$wgHooks['AbuseFilter-computeVariable'][] = 'CentralAuthHooks::abuseFilterComputeVariable';
$wgHooks['AbuseFilter-generateUserVars'][] = 'CentralAuthHooks::abuseFilterGenerateUserVars';
$wgHooks['AbuseFilter-builder'][] = 'CentralAuthHooks::abuseFilterBuilder';

// For SecurePoll
$wgHooks['SecurePoll_GetUserParams'][] = 'CentralAuthHooks::onSecurePoll_GetUserParams';

// For OAuth
$wgHooks['OAuthGetLocalUserFromCentralId'][] = 'CentralAuthHooks::onOAuthGetLocalUserFromCentralId';
$wgHooks['OAuthGetCentralIdFromLocalUser'][] = 'CentralAuthHooks::onOAuthGetCentralIdFromLocalUser';
$wgHooks['OAuthGetUserNamesFromCentralIds'][] = 'CentralAuthHooks::onOAuthGetUserNamesFromCentralIds';

$wgAvailableRights[] = 'centralauth-merge';
$wgAvailableRights[] = 'centralauth-unmerge';
$wgAvailableRights[] = 'centralauth-lock';
$wgAvailableRights[] = 'centralauth-oversight';
$wgAvailableRights[] = 'globalgrouppermissions';
$wgAvailableRights[] = 'globalgroupmembership';
$wgAvailableRights[] = 'centralauth-autoaccount';

$wgGroupPermissions['steward']['centralauth-unmerge'] = true;
$wgGroupPermissions['steward']['centralauth-lock'] = true;
$wgGroupPermissions['steward']['centralauth-oversight'] = true;
$wgGroupPermissions['*']['centralauth-merge'] = true;

$wgSpecialPages['CentralAuth'] = 'SpecialCentralAuth';
$wgSpecialPages['CentralLogin'] = 'SpecialCentralLogin';
$wgSpecialPages['AutoLogin'] = 'SpecialAutoLogin';
$wgSpecialPages['CentralAutoLogin'] = 'SpecialCentralAutoLogin';
$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
$wgSpecialPages['GlobalGroupMembership'] = 'SpecialGlobalGroupMembership';
$wgSpecialPages['GlobalGroupPermissions'] = 'SpecialGlobalGroupPermissions';
$wgSpecialPages['WikiSets'] = 'SpecialWikiSets';
$wgSpecialPages['GlobalUsers'] = 'SpecialGlobalUsers';
$wgSpecialPages['MultiLock'] = 'SpecialMultiLock';
$wgSpecialPageGroups['CentralAuth'] = 'users';
$wgSpecialPageGroups['MergeAccount'] = 'login';
$wgSpecialPageGroups['GlobalGroupMembership'] = 'users';
$wgSpecialPageGroups['GlobalGroupPermissions'] = 'users';
$wgSpecialPageGroups['WikiSets'] = 'wiki';
$wgSpecialPageGroups['GlobalUsers'] = 'users';
$wgSpecialPageGroups['MultiLock'] = 'users';

$wgAPIModules['deleteglobalaccount'] = 'ApiDeleteGlobalAccount';
$wgAPIModules['setglobalaccountstatus'] = 'ApiSetGlobalAccountStatus';

// API Query-Modules
$wgAPIMetaModules['globaluserinfo'] = 'ApiQueryGlobalUserInfo';
$wgAPIListModules['globalgroups'] = 'ApiQueryGlobalGroups';
$wgAPIListModules['wikisets'] = 'ApiQueryWikiSets';
$wgAPIListModules['globalallusers'] = 'ApiQueryGlobalAllUsers';

$wgLogTypes[]                      = 'globalauth';
$wgLogNames['globalauth']          = 'centralauth-log-name';
$wgLogHeaders['globalauth']        = 'centralauth-log-header';
$wgLogActions['globalauth/delete'] = 'centralauth-log-entry-delete';
$wgLogActions['globalauth/lock']   = 'centralauth-log-entry-lock';
$wgLogActions['globalauth/unlock'] = 'centralauth-log-entry-unlock';
$wgLogActions['globalauth/hide']   = 'centralauth-log-entry-hide';
$wgLogActions['globalauth/unhide'] = 'centralauth-log-entry-unhide';
$wgLogActions['globalauth/lockandhid'] = 'centralauth-log-entry-lockandhide';
$wgLogActions['globalauth/setstatus'] = 'centralauth-log-entry-chgstatus';
$wgLogActions['suppress/setstatus'] = 'centralauth-log-entry-chgstatus';

$wgLogTypes[]                          = 'gblrights';
$wgLogNames['gblrights']               = 'centralauth-rightslog-name';
$wgLogHeaders['gblrights']             = 'centralauth-rightslog-header';
$wgLogActions['gblrights/usergroups']  = 'centralauth-rightslog-entry-usergroups';
$wgLogActions['gblrights/groupperms']  = 'centralauth-rightslog-entry-groupperms';
$wgLogActions['gblrights/groupprms2']  = 'centralauth-rightslog-entry-groupperms2';
$wgLogActions['gblrights/groupprms3']  = 'centralauth-rightslog-entry-groupperms3';
$wgLogActionsHandlers['gblrights/grouprename'] = 'efHandleGrouprenameLogEntry';

foreach ( array( 'newset', 'setrename', 'setnewtype', 'setchange', 'deleteset' ) as $type ) {
	$wgLogActionsHandlers["gblrights/{$type}"] = 'efHandleWikiSetLogEntry';
}

$commonModuleInfo = array(
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => 'CentralAuth/modules',
);

$wgResourceModules['ext.centralauth'] = array(
	'scripts' => 'ext.centralauth.js',
	'styles' => 'ext.centralauth.css',
	'dependencies' => array(
		'mediawiki.util',
		'jquery.spinner'
	),
	'messages' => array(
		'centralauth-merge-method-primary',
		'centralauth-merge-method-primary-desc',
		'centralauth-merge-method-new',
		'centralauth-merge-method-new-desc',
		'centralauth-merge-method-empty',
		'centralauth-merge-method-empty-desc',
		'centralauth-merge-method-password',
		'centralauth-merge-method-password-desc',
		'centralauth-merge-method-mail',
		'centralauth-merge-method-mail-desc',
		'centralauth-merge-method-admin',
		'centralauth-merge-method-admin-desc',
		'centralauth-merge-method-login',
		'centralauth-merge-method-login-desc',
		'centralauth-admin-delete-confirm',
		'centralauth-completelogin-back',
		'centralauth-completelogin-finishing'
	),
) + $commonModuleInfo;
$wgResourceModules['ext.centralauth.centralautologin'] = array(
	'scripts' => 'ext.centralauth.centralautologin.js',
	'styles' => 'ext.centralauth.centralautologin.css',
	'position' => 'top',
	'dependencies' => array(
		'mediawiki.notify',
		'mediawiki.jqueryMsg',
	),
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.noflash'] = array(
	'styles' => 'ext.centralauth.noflash.css',
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.globalusers'] = array(
	'styles' => 'ext.centralauth.globalusers.css',
) + $commonModuleInfo;
$wgResourceModules['ext.centralauth.globalgrouppermissions'] = array(
	'styles' => 'ext.centralauth.globalgrouppermissions.css',
) + $commonModuleInfo;

// If AntiSpoof is installed, we can do some AntiSpoof stuff for CA
// Though, doing it this way, AntiSpoof has to be loaded/included first
// I guess this is bug 30234
if ( class_exists( 'AntiSpoof' ) ) {
	$wgExtensionCredits['antispam'][] = array(
		'path' => __FILE__,
		'name' => 'AntiSpoof for CentralAuth',
		'url' => 'https://www.mediawiki.org/wiki/Extension:AntiSpoof',
		'author' => 'Sam Reed',
		'descriptionmsg' => 'centralauth-antispoof-desc',
	);
	$wgAutoloadClasses['CentralAuthSpoofUser'] = "$caBase/AntiSpoof/CentralAuthSpoofUser.php";
	$wgAutoloadClasses['CentralAuthAntiSpoofHooks'] = "$caBase/AntiSpoof/CentralAuthAntiSpoofHooks.php";

	$wgHooks['AbortNewAccount'][] = 'CentralAuthAntiSpoofHooks::asAbortNewAccountHook';
	$wgHooks['AddNewAccount'][] = 'CentralAuthAntiSpoofHooks::asAddNewAccountHook';
	$wgHooks['RenameUserComplete'][] = 'CentralAuthAntiSpoofHooks::asAddRenameUserHook';
}

/**
 * @param $type
 * @param $action
 * @param $title
 * @param $skin Skin|null If null, we want to use the wiki content language, since that will go to the IRC feed.
 * @param $params
 * @param $filterWikilinks bool
 * @return String
 */
function efHandleWikiSetLogEntry( $type, $action, $title, $skin, $params, $filterWikilinks = false ) {
	if ( $skin ) {
		$link = Linker::link( $title, htmlspecialchars( $params[0] ) );
	} else {
		$link = $params[0];
	}

	switch( $action ) {
		case 'newset':
			$args = array( WikiSet::formatType( $params[1] ), $params[2] );
			break;
		case 'setrename':
			$args = array( $params[1] );
			break;
		case 'setnewtype':
			$args = array( WikiSet::formatType( $params[1] ), WikiSet::formatType( $params[2] ) );
			break;
		case 'setchange':
			$args = array( $params[1]
				? $params[1] : wfMessage( 'rightsnone' )->text(), $params[2] ? $params[2] : wfMessage( 'rightsnone' )->text() );
			break;
		default: //'deleteset'
			$args = array();
	}

	// Give grep a chance to find the usages:
	// centralauth-rightslog-entry-newset, centralauth-rightslog-entry-setrename,
	// centralauth-rightslog-entry-setnewtype, centralauth-rightslog-entry-setchange,
	// centralauth-rightslog-entry-deleteset
	$msg = wfMessage( "centralauth-rightslog-entry-{$action}", $link )->params( $args );
	if( $skin ) {
		return $msg->text();
	} else {
		return $msg->inContentLanguage()->text();
	}
}

/**
 * Format global group rename log entries
 *
 * @param $type
 * @param $action
 * @param $title
 * @param $skin Skin|null If null, we want to use the wiki content language, since that will go to the IRC feed.
 * @param $params
 * @param $filterWikilinks bool
 *
 * @return String
 */
function efHandleGrouprenameLogEntry( $type, $action, $title, $skin, $params, $filterWikilinks = false ) {
	// $params[0] is the new one, $params[1] the old one
	if ( $skin ) {
		$params[0] = Linker::link( Title::newFromText( $params[0] ), htmlspecialchars( $params[0] ) );
		$params[1] = Linker::link( Title::newFromText( $params[1] ), htmlspecialchars( $params[1] ) );
	} else {
		$params[0] = htmlspecialchars( $params[0] );
		$params[1] = htmlspecialchars( $params[1] );
	}

	$msg = wfMessage( 'centralauth-rightslog-entry-grouprename' )->rawParams( $params[0], $params[1] );
	if ( $skin ) {
		return $msg->text();
	} else {
		return $msg->inContentLanguage()->text();
	}
}
