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
 * $wgLBFactoryConf to set up an LBFactoryMulti object.
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
 * If true, existing unattached accounts where no global account exists
 * will be compared to see if a merge can be made based on passwords
 * and emails with no clashes (all accounts merge).
 *
 * This was formerly controlled by $wgCentralAuthAutoMigrate
 */
$wgCentralAuthAutoMigrateNonGlobalAccounts = false;

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
 * Path for CentralAuth global authentication cookies. Set this variable if you
 * want to restrict cookies to a certain path within the domain specified by
 * $wgCentralAuthCookieDomain.
 */
$wgCentralAuthCookiePath = '/';

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
 * Local filesystem path to the icon returned by Special:CentralAutoLogin
 * Should be a 20x20px PNG.
 */
$wgCentralAuthLoginIcon = false;

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
 * Array of settings for sending the CentralAuth events to the RC Feeds
 *
 * @example $wgRCFeeds['example'] = array(
 *		'uri' => "udp://localhost:1336",
 *	);
 */
$wgCentralAuthRC = array();

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
 * Don't allow new unattached accounts to be created
 * @var bool
 */
$wgCentralAuthPreventUnattached = false;

/**
 * Whether to enable the global user merge tool
 * This only controls the availability of the special page,
 * and does not prevent LocalUserMergeJobs from running
 * on the given wiki.
 * @var bool
 */
$wgCentralAuthEnableUserMerge = false;

/**
 * Feature flag for Special:GlobalRenameRequest
 * @var bool
 */
$wgCentralAuthEnableGlobalRenameRequest = false;

/**
 * Enable special logic to attempt to ease the user facing impact of forced
 * user migrations.
 * @var bool
 */
$wgCentralAuthCheckSULMigration = false;

/**
 * Initialization of the autoloaders, and special extension pages.
 */
$caBase = __DIR__;
$wgAutoloadClasses['SpecialCentralAuth'] = "$caBase/includes/specials/SpecialCentralAuth.php";
$wgAutoloadClasses['SpecialCentralLogin'] = "$caBase/includes/specials/SpecialCentralLogin.php";
$wgAutoloadClasses['SpecialMergeAccount'] = "$caBase/includes/specials/SpecialMergeAccount.php";
$wgAutoloadClasses['SpecialGlobalUsers'] = "$caBase/includes/specials/SpecialGlobalUsers.php";
$wgAutoloadClasses['GlobalUsersPager'] = "$caBase/includes/specials/SpecialGlobalUsers.php";
$wgAutoloadClasses['SpecialMultiLock'] = "$caBase/includes/specials/SpecialMultiLock.php";
$wgAutoloadClasses['SpecialGlobalRenameRequest'] = "$caBase/includes/specials/SpecialGlobalRenameRequest.php";
$wgAutoloadClasses['SpecialGlobalRenameQueue'] = "$caBase/includes/specials/SpecialGlobalRenameQueue.php";
$wgAutoloadClasses['RenameQueueTablePager'] = "$caBase/includes/specials/SpecialGlobalRenameQueue.php";
$wgAutoloadClasses['SpecialSulRenameWarning'] = "$caBase/includes/specials/SpecialSulRenameWarning.php";
$wgAutoloadClasses['CentralAuthUser'] = "$caBase/includes/CentralAuthUser.php";
$wgAutoloadClasses['CentralAuthPlugin'] = "$caBase/includes/CentralAuthPlugin.php";
$wgAutoloadClasses['CentralAuthHooks'] = "$caBase/includes/CentralAuthHooks.php";
$wgAutoloadClasses['CentralAuthSuppressUserJob'] = "$caBase/includes/SuppressUserJob.php";
$wgAutoloadClasses['WikiSet'] = "$caBase/includes/WikiSet.php";
$wgAutoloadClasses['SpecialCentralAutoLogin'] = "$caBase/includes/specials/SpecialCentralAutoLogin.php";
$wgAutoloadClasses['CentralAuthUserArray'] = "$caBase/includes/CentralAuthUserArray.php";
$wgAutoloadClasses['CentralAuthUserArrayFromResult'] = "$caBase/includes/CentralAuthUserArray.php";
$wgAutoloadClasses['SpecialGlobalGroupMembership'] = "$caBase/includes/specials/SpecialGlobalGroupMembership.php";
$wgAutoloadClasses['CentralAuthGroupMembershipProxy'] = "$caBase/includes/CentralAuthGroupMembershipProxy.php";
$wgAutoloadClasses['SpecialGlobalGroupPermissions'] = "$caBase/includes/specials/SpecialGlobalGroupPermissions.php";
$wgAutoloadClasses['SpecialWikiSets'] = "$caBase/includes/specials/SpecialWikiSets.php";
$wgAutoloadClasses['ApiQueryGlobalUserInfo'] = "$caBase/includes/api/ApiQueryGlobalUserInfo.php";
$wgAutoloadClasses['ApiDeleteGlobalAccount'] = "$caBase/includes/api/ApiDeleteGlobalAccount.php";
$wgAutoloadClasses['ApiSetGlobalAccountStatus'] = "$caBase/includes/api/ApiSetGlobalAccountStatus.php";
$wgAutoloadClasses['ApiQueryGlobalGroups'] = "$caBase/includes/api/ApiQueryGlobalGroups.php";
$wgAutoloadClasses['ApiQueryWikiSets'] = "$caBase/includes/api/ApiQueryWikiSets.php";
$wgAutoloadClasses['ApiQueryGlobalAllUsers'] = "$caBase/includes/api/ApiQueryGlobalAllUsers.php";
$wgAutoloadClasses['ApiGlobalUserRights'] = "$caBase/includes/api/ApiGlobalUserRights.php";
$wgAutoloadClasses['ApiCentralAuthToken'] = "$caBase/includes/api/ApiCentralAuthToken.php";
$wgAutoloadClasses['CentralAuthReadOnlyError'] = "$caBase/includes/CentralAuthReadOnlyError.php";
$wgAutoloadClasses['CARCFeedFormatter'] = "$caBase/rcfeed/CARCFeedFormatter.php";
$wgAutoloadClasses['IRCColourfulCARCFeedFormatter'] = "$caBase/rcfeed/IRCColourfulCARCFeedFormatter.php";
$wgAutoloadClasses['JSONCARCFeedFormatter'] = "$caBase/rcfeed/JSONCARCFeedFormatter.php";
$wgAutoloadClasses['LocalRenameJob'] = "$caBase/includes/LocalRenameJob/LocalRenameJob.php";
$wgAutoloadClasses['LocalRenameUserJob'] = "$caBase/includes/LocalRenameJob/LocalRenameUserJob.php";
$wgAutoloadClasses['LocalUserMergeJob'] = "$caBase/includes/LocalRenameJob/LocalUserMergeJob.php";
$wgAutoloadClasses['LocalPageMoveJob'] = "$caBase/includes/LocalRenameJob/LocalPageMoveJob.php";
$wgAutoloadClasses['SpecialGlobalRenameUser'] = "$caBase/includes/specials/SpecialGlobalRenameUser.php";
$wgAutoloadClasses['SpecialGlobalUserMerge'] = "$caBase/includes/specials/SpecialGlobalUserMerge.php";
$wgAutoloadClasses['SpecialGlobalRenameProgress'] = "$caBase/includes/specials/SpecialGlobalRenameProgress.php";
$wgAutoloadClasses['GlobalRenameLogFormatter'] = "$caBase/includes/GlobalRename/GlobalRenameLogFormatter.php";
$wgAutoloadClasses['GlobalUserMergeLogFormatter'] = "$caBase/includes/GlobalRename/GlobalUserMergeLogFormatter.php";

$wgAutoloadClasses['GlobalRenameUser'] = "$caBase/includes/GlobalRename/GlobalRenameUser.php";
$wgAutoloadClasses['GlobalRenameUserStatus'] = "$caBase/includes/GlobalRename/GlobalRenameUserStatus.php";
$wgAutoloadClasses['GlobalRenameRequest'] = "$caBase/includes/GlobalRename/GlobalRenameRequest.php";
$wgAutoloadClasses['GlobalRenameUserValidator'] = "$caBase/includes/GlobalRename/GlobalRenameUserValidator.php";
$wgAutoloadClasses['GlobalRenameUserDatabaseUpdates'] = "$caBase/includes/GlobalRename/GlobalRenameUserDatabaseUpdates.php";
$wgAutoloadClasses['GlobalRenameUserLogger'] = "$caBase/includes/GlobalRename/GlobalRenameUserLogger.php";
$wgAutoloadClasses['GlobalUserMergeLogger'] = "$caBase/includes/GlobalRename/GlobalUserMergeLogger.php";
$wgAutoloadClasses['GlobalUserMerge'] = "$caBase/includes/GlobalRename/GlobalUserMerge.php";
$wgAutoloadClasses['GlobalUserMergeDatabaseUpdates'] = "$caBase/includes/GlobalRename/GlobalUserMergeDatabaseUpdates.php";

$wgAutoloadClasses['CentralAuthTestCaseUsingDatabase'] = "$caBase/tests/CentralAuthTestCaseUsingDatabase.php";
$wgAutoloadClasses['CentralAuthTestUser'] = "$caBase/tests/CentralAuthTestUser.php";

$wgAutoloadClasses['SpecialUsersWhoWillBeRenamed'] = "$caBase/includes/specials/SpecialUsersWhoWillBeRenamed.php";
$wgAutoloadClasses['UsersWhoWillBeRenamedPager'] = "$caBase/includes/specials/SpecialUsersWhoWillBeRenamed.php";
$wgAutoloadClasses['UsersToRenameDatabaseUpdates'] = "$caBase/includes/UsersToRename/UsersToRenameDatabaseUpdates.php";

// only used by maintenance/sendConfirmAndMigrateEmail.php
$wgAutoloadClasses['EmailableUser'] = "$caBase/includes/EmailableUser.php";

$wgMessagesDirs['SpecialCentralAuth'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SpecialCentralAuthAliases'] = "$caBase/CentralAuth.alias.php";
$wgExtensionMessagesFiles['SpecialCentralAuthAliasesNoTranslate'] = "$caBase/CentralAuth.notranslate-alias.php";

$wgJobClasses['crosswikiSuppressUser'] = 'CentralAuthSuppressUserJob';
$wgJobClasses['LocalRenameUserJob'] = 'LocalRenameUserJob';
$wgJobClasses['LocalUserMergeJob'] = 'LocalUserMergeJob';
$wgJobClasses['LocalPageMoveJob'] = 'LocalPageMoveJob';

$wgHooks['SetupAfterCache'][] = 'CentralAuthHooks::onSetupAfterCache';
$wgHooks['AuthPluginSetup'][] = 'CentralAuthHooks::onAuthPluginSetup';
$wgHooks['AddNewAccount'][] = 'CentralAuthHooks::onAddNewAccount';
$wgHooks['GetPreferences'][] = 'CentralAuthHooks::onGetPreferences';
$wgHooks['AbortLogin'][] = 'CentralAuthHooks::onAbortLogin';
$wgHooks['AbortNewAccount'][] = 'CentralAuthHooks::onAbortNewAccount';
$wgHooks['AbortAutoAccount'][] = 'CentralAuthHooks::onAbortAutoAccount';
$wgHooks['UserLoginComplete'][] = 'CentralAuthHooks::onUserLoginComplete';
$wgHooks['UserLoadFromSession'][] = 'CentralAuthHooks::onUserLoadFromSession';
$wgHooks['UserLogout'][] = 'CentralAuthHooks::onUserLogout';
$wgHooks['UserLogoutComplete'][] = 'CentralAuthHooks::onUserLogoutComplete';
$wgHooks['GetCacheVaryCookies'][] = 'CentralAuthHooks::onGetCacheVaryCookies';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'CentralAuthHooks::onResourceLoaderGetConfigVars';
$wgHooks['UserArrayFromResult'][] = 'CentralAuthHooks::onUserArrayFromResult';
$wgHooks['UserGetEmail'][] = 'CentralAuthHooks::onUserGetEmail';
$wgHooks['UserGetEmailAuthenticationTimestamp'][] = 'CentralAuthHooks::onUserGetEmailAuthenticationTimestamp';
$wgHooks['UserGetReservedNames'][] = 'CentralAuthHooks::onUserGetReservedNames';
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
$wgHooks['ApiQueryTokensRegisterTypes'][] = 'CentralAuthHooks::onApiQueryTokensRegisterTypes';
$wgHooks['APIGetAllowedParams'][] = 'CentralAuthHooks::onAPIGetAllowedParams';
$wgHooks['APIGetParamDescription'][] = 'CentralAuthHooks::onAPIGetParamDescription';
$wgHooks['ApiCheckCanExecute'][] = 'CentralAuthHooks::onApiCheckCanExecute';
$wgHooks['TestCanonicalRedirect'][] = 'CentralAuthHooks::onTestCanonicalRedirect';
$wgHooks['LogEventsListGetExtraInputs'][] = 'CentralAuthHooks::onLogEventsListGetExtraInputs';
$wgHooks['SpecialLogAddLogSearchRelations'][] = 'CentralAuthHooks::onSpecialLogAddLogSearchRelations';
$wgHooks['UnitTestsList'][] = 'CentralAuthHooks::onUnitTestsList';
$wgHooks['SpecialContributionsBeforeMainOutput'][] = 'CentralAuthHooks::onSpecialContributionsBeforeMainOutput';
$wgHooks['SpecialPage_initList'][] = 'CentralAuthHooks::onSpecialPage_initList';

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
$wgHooks['OAuthGetCentralIdFromUserName'][] = 'CentralAuthHooks::onOAuthGetCentralIdFromUserName';
$wgHooks['OAuthGetUserNamesFromCentralIds'][] = 'CentralAuthHooks::onOAuthGetUserNamesFromCentralIds';

// For GlobalCssJs
$wgHooks['LoadGlobalCssJs'][] = 'CentralAuthHooks::onLoadGlobalCssJs';

// For GlobalUserPage
$wgHooks['LoadGlobalUserPage'][] = 'CentralAuthHooks::onLoadGlobalUserPage';

// For UserMerge
$wgHooks['DeleteAccount'][] = 'CentralAuthHooks::onDeleteAccount';

$wgAvailableRights[] = 'centralauth-merge';
$wgAvailableRights[] = 'centralauth-unmerge';
$wgAvailableRights[] = 'centralauth-lock';
$wgAvailableRights[] = 'centralauth-oversight';
$wgAvailableRights[] = 'globalgrouppermissions';
$wgAvailableRights[] = 'globalgroupmembership';
$wgAvailableRights[] = 'centralauth-autoaccount';
$wgAvailableRights[] = 'centralauth-rename';
$wgAvailableRights[] = 'centralauth-usermerge';

$wgGroupPermissions['steward']['centralauth-unmerge'] = true;
$wgGroupPermissions['steward']['centralauth-lock'] = true;
$wgGroupPermissions['steward']['centralauth-oversight'] = true;
$wgGroupPermissions['*']['centralauth-merge'] = true;

$wgSpecialPages['CentralAuth'] = 'SpecialCentralAuth';
$wgSpecialPages['CentralLogin'] = 'SpecialCentralLogin';
$wgSpecialPages['CentralAutoLogin'] = 'SpecialCentralAutoLogin';
$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
$wgSpecialPages['GlobalGroupMembership'] = 'SpecialGlobalGroupMembership';
$wgSpecialPages['GlobalGroupPermissions'] = 'SpecialGlobalGroupPermissions';
$wgSpecialPages['WikiSets'] = 'SpecialWikiSets';
$wgSpecialPages['GlobalUsers'] = 'SpecialGlobalUsers';
$wgSpecialPages['MultiLock'] = 'SpecialMultiLock';
$wgSpecialPages['GlobalRenameUser'] = 'SpecialGlobalRenameUser';
$wgSpecialPages['GlobalRenameProgress'] = 'SpecialGlobalRenameProgress';
$wgSpecialPages['GlobalUserMerge'] = 'SpecialGlobalUserMerge';
$wgSpecialPages['UsersWhoWillBeRenamed'] = 'SpecialUsersWhoWillBeRenamed';

$wgAPIModules['deleteglobalaccount'] = 'ApiDeleteGlobalAccount';
$wgAPIModules['setglobalaccountstatus'] = 'ApiSetGlobalAccountStatus';
$wgAPIModules['centralauthtoken'] = 'ApiCentralAuthToken';
$wgAPIModules['globaluserrights'] = 'ApiGlobalUserRights';

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
$wgLogActions['suppress/cadelete'] = 'centralauth-log-entry-delete';

$wgLogTypes[]                          = 'gblrights';
$wgLogTypes[]                          = 'gblrename';
$wgLogNames['gblrights']               = 'centralauth-rightslog-name';
$wgLogHeaders['gblrights']             = 'centralauth-rightslog-header';
$wgLogActions['gblrights/usergroups']  = 'centralauth-rightslog-entry-usergroups';
$wgLogActions['gblrights/groupperms']  = 'centralauth-rightslog-entry-groupperms';
$wgLogActions['gblrights/groupprms2']  = 'centralauth-rightslog-entry-groupperms2';
$wgLogActions['gblrights/groupprms3']  = 'centralauth-rightslog-entry-groupperms3';
$wgLogActionsHandlers['gblrights/grouprename'] = 'CentralAuthHooks::onHandleGrouprenameLogEntry';
$wgLogActionsHandlers['gblrename/rename'] = 'GlobalRenameLogFormatter';
$wgLogActionsHandlers['gblrename/promote'] = 'GlobalRenameLogFormatter';
$wgLogActionsHandlers['gblrename/merge'] = 'GlobalUserMergeLogFormatter';

foreach ( array( 'newset', 'setrename', 'setnewtype', 'setchange', 'deleteset' ) as $type ) {
	$wgLogActionsHandlers["gblrights/{$type}"] = 'CentralAuthHooks::onHandleWikiSetLogEntry';
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
	),
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.centralautologin'] = array(
	'scripts' => 'ext.centralauth.centralautologin.js',
	'styles' => 'ext.centralauth.centralautologin.css',
	'position' => 'top',
	'targets' => array( 'mobile', 'desktop' ),
	'dependencies' => array(
		'mediawiki.notify',
		'mediawiki.jqueryMsg',
	),
) + $commonModuleInfo;
$wgResourceModules['ext.centralauth.centralautologin.clearcookie'] = array(
	'scripts' => 'ext.centralauth.centralautologin.clearcookie.js',
	'position' => 'top',
	'targets' => array( 'mobile', 'desktop' ),
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.noflash'] = array(
	'position' => 'top',
	'styles' => 'ext.centralauth.noflash.css',
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.globaluserautocomplete'] = array(
	'scripts' => 'ext.centralauth.globaluserautocomplete.js',
	'dependencies' => array(
		'jquery.suggestions',
		'mediawiki.api'
	)
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.globalusers'] = array(
	'position' => 'top',
	'styles' => 'ext.centralauth.globalusers.css',
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.globalgrouppermissions'] = array(
	'position' => 'top',
	'styles' => 'ext.centralauth.globalgrouppermissions.css',
) + $commonModuleInfo;

$wgResourceModules['ext.centralauth.globalrenameuser'] = array(
	'scripts' => 'ext.centralauth.globalrenameuser.js',
	'dependencies' => array(
		'mediawiki.util'
	),
	'messages' => array(
		'centralauth-rename-confirm',
		'centralauth-usermerge-confirm',
	)
) + $commonModuleInfo;

// Finish configuration after other extensions and settings are loaded.
$wgExtensionFunctions[] = 'CentralAuthHooks::onRunExtensionFunctions';
