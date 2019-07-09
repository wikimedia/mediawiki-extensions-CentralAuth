<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CentralAuth' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CentralAuth'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CentralAuthAlias'] = __DIR__ . '/CentralAuth.alias.php';
	$wgExtensionMessagesFiles['SpecialCentralAuthAliasesNoTranslate'] =
		__DIR__ . '/CentralAuth.notranslate-alias.php';
	// wfWarn(
	// 'Deprecated PHP entry point used for CentralAuth extension. ' .
	// 'Please use wfLoadExtension instead, ' .
	// 'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	// );
	return;
} else {
	die( 'This version of the CentralAuth extension requires MediaWiki 1.25+' );
}

// Global declarations and documentation kept for IDEs and PHP documentors.
// This code is never executed.

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
$wgCentralAuthAutoLoginWikis = [];

/**
 * List of wiki IDs on which an attached local account should be created automatically when the
 * global account is created.
 *
 * The wiki ID is typically the database name, except when table prefixes are
 * used, in which case it is the database name, a hyphen separator, and then
 * the table prefix.
 */
$wgCentralAuthAutoCreateWikis = [];

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
$wgCentralAuthPrefsForUIReload = [ /* See extension.json */ ];

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
 * @example $wgRCFeeds['example'] = [
 *		'uri' => "udp://localhost:1336",
 *	];
 */
$wgCentralAuthRC = [];

/**
 * List of local pages global users may edit while being globally locked.
 */
$wgCentralAuthLockedCanEdit = [];

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
 * Feature flag for Special:UsersWhoWillBeRenamed
 * @var bool
 */
$wgCentralAuthEnableUsersWhoWillBeRenamed = false;

/**
 * Global Password Policies. These are applied like local password policies,
 * the strongest policy applicable to a user is used. Policies can apply to
 * either a local group (if the user is a member of that group on any wiki,
 * the policy will apply to that user) or global group.
 * @var array
 * @see $wgPasswordPolicy
 */
$wgCentralAuthGlobalPasswordPolicies = [];

/**
 * Set false if you really want to use 'local' rather than 'CentralAuth' for
 * $wgCentralIdLookupProvider. This isn't the default because using CentralAuth
 * is almost always what you want if CentralAuth is installed.
 * @var bool
 */
$wgOverrideCentralIdLookupProvider = true;

/**
 * If set, this will be used instead of $wgSessionCacheType for session storage
 * @var string|int|null
 */
$wgCentralAuthSessionCacheType = null;
