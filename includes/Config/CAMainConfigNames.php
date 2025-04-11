<?php

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
// phpcs:disable Generic.Files.LineLength.TooLong
namespace MediaWiki\Extension\CentralAuth\Config;

/**
 * Keep in sync with extension.json #config map. We do not want to
 * duplicate documentation, so the docs for each setting is in the
 * extension.json file, refer there instead.
 *
 * This is a convenient class just like that in core for referencing
 * config settings. NOTE: This can use the IDE static analysis rules
 * to determine if the constant is used or not which can help in catching
 * unused settings.
 *
 * @note When a constant is caught by the IDE here as on unused, check
 *   and verify if it's not used as a global variable. The ones used
 *   as global variables have been marked to inform the developer. If
 *   we move globals to proper settings to be injected via service options,
 *   then those can be referenced using these constants.
 */
class CAMainConfigNames {
	/** @deprecated Use MainConfigNames DatabaseVirtualDomains in core. */
	public const CentralAuthDatabase = 'CentralAuthDatabase';
	/** @note Used as a global variable */
	public const CentralAuthAutoMigrate = 'CentralAuthAutoMigrate';
	/** @note Used as a global variable */
	public const CentralAuthAutoMigrateNonGlobalAccounts = 'CentralAuthAutoMigrateNonGlobalAccounts';
	public const CentralAuthStrict = 'CentralAuthStrict';
	public const CentralAuthDryRun = 'CentralAuthDryRun';
	public const CentralAuthCookies = 'CentralAuthCookies';
	public const CentralAuthLoginWiki = 'CentralAuthLoginWiki';
	public const CentralAuthSharedDomainCallback = 'CentralAuthSharedDomainCallback';
	public const CentralAuthRestrictSharedDomain = 'CentralAuthRestrictSharedDomain';
	public const CentralAuthCookieDomain = 'CentralAuthCookieDomain';
	public const CentralAuthCookiePrefix = 'CentralAuthCookiePrefix';
	public const CentralAuthCookiePath = 'CentralAuthCookiePath';
	/** @note Used as a global variable */
	public const CentralAuthAutoLoginWikis = 'CentralAuthAutoLoginWikis';
	public const CentralAuthAutoCreateWikis = 'CentralAuthAutoCreateWikis';
	public const CentralAuthLoginIcon = 'CentralAuthLoginIcon';
	public const CentralAuthAutomaticGlobalGroups = 'CentralAuthAutomaticGlobalGroups';
	public const CentralAuthPrefsForUIReload = 'CentralAuthPrefsForUIReload';
	/** @note Used as a global variable */
	public const CentralAuthRC = 'CentralAuthRC';
	/** @note Used as a global variable */
	public const CentralAuthWikisPerSuppressJob = 'CentralAuthWikisPerSuppressJob';
	public const CentralAuthReadOnly = 'CentralAuthReadOnly';
	public const CentralAuthEnableGlobalRenameRequest = 'CentralAuthEnableGlobalRenameRequest';
	public const CentralAuthGlobalPasswordPolicies = 'CentralAuthGlobalPasswordPolicies';
	/** @note Used as a global variable */
	public const CentralAuthGlobalBlockInterwikiPrefix = 'CentralAuthGlobalBlockInterwikiPrefix';
	public const CentralAuthOldNameAntiSpoofWiki = 'CentralAuthOldNameAntiSpoofWiki';
	public const GlobalRenameDenylist = 'GlobalRenameDenylist';
	public const CentralAuthSessionCacheType = 'CentralAuthSessionCacheType';
	public const CentralAuthRejectVanishUserNotification = 'CentralAuthRejectVanishUserNotification';
	public const CentralAuthAutomaticVanishPerformer = 'CentralAuthAutomaticVanishPerformer';
	public const CentralAuthAutomaticVanishWiki = 'CentralAuthAutomaticVanishWiki';
	public const CentralAuthBlockAppealWikidataIds = 'CentralAuthBlockAppealWikidataIds';
	public const CentralAuthWikidataApiUrl = 'CentralAuthWikidataApiUrl';
	public const CentralAuthFallbackAppealUrl = 'CentralAuthFallbackAppealUrl';
	public const CentralAuthFallbackAppealTitle = 'CentralAuthFallbackAppealTitle';
	public const CentralAuthEnableSul3 = 'CentralAuthEnableSul3';
	public const CentralAuthSul3SharedDomainRestrictions = 'CentralAuthSul3SharedDomainRestrictions';
}
