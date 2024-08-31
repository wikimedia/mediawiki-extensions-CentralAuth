<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth;

use CentralAuthSessionProvider;
use ExtensionRegistry;
use MediaWiki\Api\Hook\ApiQueryTokensRegisterTypesHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\PageDisplayHookHandler;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralAutoLogin;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUserArrayFromResult;
use MediaWiki\Hook\GetLogTypesOnUserHook;
use MediaWiki\Hook\TestCanonicalRedirectHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Hook\UserGetRightsHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Request\ContentSecurityPolicy;
use MediaWiki\Request\WebRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderForeignApiModulesHook;
use MediaWiki\Session\CookieSessionProvider;
use MediaWiki\Session\Hook\SessionCheckInfoHook;
use MediaWiki\Session\SessionInfo;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\Hook\InvalidateEmailCompleteHook;
use MediaWiki\User\Hook\SpecialPasswordResetOnSubmitHook;
use MediaWiki\User\Hook\UserArrayFromResultHook;
use MediaWiki\User\Hook\UserGetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserGetEmailHook;
use MediaWiki\User\Hook\UserGetReservedNamesHook;
use MediaWiki\User\Hook\UserIsBotHook;
use MediaWiki\User\Hook\UserIsLockedHook;
use MediaWiki\User\Hook\UserSaveSettingsHook;
use MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserSetEmailHook;
use MediaWiki\User\User;
use MediaWiki\User\UserArrayFromResult;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MobileContext;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\IconWidget;
use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthHooks implements
	ApiQueryTokensRegisterTypesHook,
	MakeGlobalVariablesScriptHook,
	TestCanonicalRedirectHook,
	UserGetRightsHook,
	GetPreferencesHook,
	ResourceLoaderForeignApiModulesHook,
	SessionCheckInfoHook,
	GetLogTypesOnUserHook,
	InvalidateEmailCompleteHook,
	SpecialPasswordResetOnSubmitHook,
	UserArrayFromResultHook,
	UserGetEmailAuthenticationTimestampHook,
	UserGetEmailHook,
	UserGetReservedNamesHook,
	UserIsBotHook,
	UserIsLockedHook,
	UserSaveSettingsHook,
	UserSetEmailAuthenticationTimestampHook,
	UserSetEmailHook
{

	/**
	 * Called right after configuration variables have been set.
	 */
	public static function onRegistration() {
		global $wgCentralAuthDatabase, $wgSessionProviders,
			$wgCentralIdLookupProvider, $wgVirtualDomainsMapping;

		if (
			// Test against the local database
			defined( 'MW_PHPUNIT_TEST' )
			// Install tables to the local database in CI
			// TODO: configure this in CI
			|| defined( 'MW_QUIBBLE_CI' )
		) {
			$wgCentralAuthDatabase = false;
			unset( $wgVirtualDomainsMapping['virtual-centralauth'] );
		} else {
			if ( !isset( $wgVirtualDomainsMapping['virtual-centralauth'] ) ) {
				$wgVirtualDomainsMapping['virtual-centralauth'] = [ 'db' => $wgCentralAuthDatabase ?? false ];
			}
		}

		// CentralAuthSessionProvider is supposed to replace core
		// CookieSessionProvider, so remove the latter if both are configured
		if ( isset( $wgSessionProviders[CookieSessionProvider::class] ) &&
			isset( $wgSessionProviders[CentralAuthSessionProvider::class] )
		) {
			unset( $wgSessionProviders[CookieSessionProvider::class] );
		}

		// Assume they want CentralAuth as the default central ID provider, unless
		// already configured otherwise.
		if ( $wgCentralIdLookupProvider === 'local' ) {
			$wgCentralIdLookupProvider = 'CentralAuth';
		}
	}

	/**
	 * Add a little pretty to the preferences user info section
	 *
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public function onGetPreferences( $user, &$preferences ) {
		// Possible states:
		// - account not merged at all
		// - global accounts exists, but this local account is unattached
		// - this local account is attached, but migration incomplete
		// - all local accounts are attached (no $message shown)

		$global = CentralAuthUser::getInstance( $user );
		$unattached = count( $global->listUnattached() );
		if ( $global->exists() ) {
			if ( $global->isAttached() && $unattached ) {
				// Migration incomplete - unattached accounts at other wikis
				$attached = count( $global->listAttached() );
				$message = wfMessage( 'centralauth-prefs-unattached' )->parse() .
					'<br />' .
					wfMessage( 'centralauth-prefs-count-attached' )
						->numParams( $attached )->parse() .
					'<br />' .
					wfMessage( 'centralauth-prefs-count-unattached' )
						->numParams( $unattached )->parse();
			} elseif ( !$global->isAttached() ) {
				// Global account exists but the local account is not attached
				$message = wfMessage( 'centralauth-prefs-detail-unattached' )->parse();
			}
		} else {
			// No global account
			$message = wfMessage( 'centralauth-prefs-not-managed' )->parse();
		}

		$manageButtons = [];

		if ( $unattached && $user->isAllowed( 'centralauth-merge' ) ) {
			// Add "Manage your global account" button
			$manageButtons[] = new ButtonWidget( [
				'href' => SpecialPage::getTitleFor( 'MergeAccount' )->getLinkURL(),
				'label' => wfMessage( 'centralauth-prefs-manage' )->text(),
			] );
		}

		// Add "View your global account info" button
		$manageButtons[] = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'CentralAuth', $user->getName() )->getLinkURL(),
			'label' => wfMessage( 'centralauth-prefs-view' )->text(),
		] );

		$manageLinkList = (string)( new HorizontalLayout( [ 'items' => $manageButtons ] ) );

		$preferences['globalaccountstatus'] = [
			'section' => 'personal/info',
			'label-message' => 'centralauth-prefs-status',
			'type' => 'info',
			'raw' => true,
			'default' => $manageLinkList
		];

		// Display a notice about the user account status with an alert icon
		if ( isset( $message ) ) {
			$messageIconWidget = (string)new IconWidget( [
				'icon' => 'alert',
				'flags' => [ 'destructive' ]
			] );
			$preferences['globalaccountstatus']['default'] = $messageIconWidget
				. "$message<br>$manageLinkList";
		}

		return true;
	}

	/**
	 * Show a nicer error when the user account does not exist on the local wiki, but
	 * does exist globally
	 * @param User[] &$users
	 * @param array $data
	 * @param string &$error
	 * @return bool
	 */
	public function onSpecialPasswordResetOnSubmit( &$users, $data, &$error ) {
		$firstUser = reset( $users );
		if ( !( $firstUser instanceof UserIdentity ) ) {
			// We can't handle this
			return true;
		}

		if ( !$firstUser->getId() ) {
			$centralUser = CentralAuthUser::getInstance( $firstUser );
			if ( $centralUser->exists() ) {
				$error = [ 'centralauth-account-exists-reset', $centralUser->getName() ];
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the HTML for an <img> element used to perform edge login, autologin (no-JS), or central logout.
	 *
	 * @param string $wikiID Target wiki
	 * @param string $page Target page, should be a Special:CentralAutoLogin subpage
	 * @param array $params URL query parameters. Some also affect the generated HTML:
	 *   - 'type': when set to '1x1', generate an invisible pixel image, instead of a visible icon
	 *   - 'mobile': when set, use target wiki's mobile domain URL instead of canonical URL
	 * @param ContentSecurityPolicy|null $csp If provided, it will be modified to allow requests to
	 *   the target wiki. Otherwise, that must be done in 'ContentSecurityPolicyDefaultSource' hook.
	 * @return string HTML
	 */
	public static function getAuthIconHtml(
		string $wikiID, string $page, array $params, ?ContentSecurityPolicy $csp
	): string {
		// Use WikiMap to avoid localization of the 'Special' namespace, see T56195.
		$wiki = WikiMap::getWiki( $wikiID );
		$url = wfAppendQuery(
			$wiki->getCanonicalUrl( $page ),
			$params
		);
		if ( isset( $params['mobile'] ) ) {
			// Do autologin on the mobile domain for each wiki
			$url = MobileContext::singleton()->getMobileUrl( $url );
		}
		if ( $csp ) {
			$csp->addDefaultSrc( wfParseUrl( $url )['host'] );
		}

		$type = $params['type'];
		return Html::element( 'img', [
			'src' => $url,
			'alt' => '',
			'width' => $type === '1x1' ? 1 : 20,
			'height' => $type === '1x1' ? 1 : 20,
			'style' => $type === '1x1' ? 'border: none; position: absolute;' : 'border: 1px solid #ccc;',
		] );
	}

	/**
	 * Get autologin wikis, in the same format as $wgCentralAuthAutoLoginWikis, but with the
	 * current domain removed.
	 * @return string[]
	 */
	public static function getAutoLoginWikis(): array {
		global $wgServer, $wgCentralAuthAutoLoginWikis, $wgCentralAuthCookieDomain;
		$autoLoginWikis = $wgCentralAuthAutoLoginWikis;
		if ( $wgCentralAuthCookieDomain ) {
			unset( $autoLoginWikis[$wgCentralAuthCookieDomain] );
		} else {
			$serverParts = MediaWikiServices::getInstance()->getUrlUtils()->parse( $wgServer );
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			unset( $autoLoginWikis[ $serverParts['host'] ] );
		}
		return $autoLoginWikis;
	}

	/**
	 * @return bool
	 */
	public static function isMobileDomain() {
		return ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' )
			&& MobileContext::singleton()->usingMobileDomain();
	}

	/**
	 * @param UserArrayFromResult|null &$userArray
	 * @param IResultWrapper $res
	 * @return bool
	 */
	public function onUserArrayFromResult( &$userArray, $res ) {
		$userArray = new CentralAuthUserArrayFromResult( $res );
		return true;
	}

	/**
	 * @param User $user
	 * @param string &$email
	 * @return bool
	 */
	public function onUserGetEmail( $user, &$email ) {
		if ( MediaWikiServices::getInstance()->getUserNameUtils()->getCanonical( $user->getName() ) === false ) {
			return true;
		}
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$email = $ca->getEmail();
		}
		return true;
	}

	/**
	 * @param User $user
	 * @param string|null &$timestamp
	 * @return bool
	 */
	public function onUserGetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			if ( $ca->isLocked() ) {
				// Locked users shouldn't be receiving email (T87559)
				$timestamp = null;
			} else {
				$timestamp = $ca->getEmailAuthenticationTimestamp();
			}
		}
		return true;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function onInvalidateEmailComplete( $user ) {
		$ca = CentralAuthUser::getPrimaryInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmail( '' );
			$ca->setEmailAuthenticationTimestamp( null );
			$ca->saveSettings();
		}
		return true;
	}

	/**
	 * @param User $user
	 * @param string &$email
	 * @return bool
	 */
	public function onUserSetEmail( $user, &$email ) {
		$ca = CentralAuthUser::getPrimaryInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->setEmail( $email );
			$ca->saveSettings();
		}
		return true;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function onUserSaveSettings( $user ) {
		$ca = CentralAuthUser::getPrimaryInstance( $user );
		if ( $ca->isAttached() ) {
			$ca->saveSettings();
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param ?string &$timestamp
	 * @return bool
	 */
	public function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ) {
		$ca = CentralAuthUser::getInstance( $user );
		if ( $ca->isAttached() ) {
			$latestCa = CentralAuthUser::newPrimaryInstanceFromId( $ca->getId() );
			if ( $latestCa->isAttached() ) {
				$latestCa->setEmailAuthenticationTimestamp( $timestamp );
				$latestCa->saveSettings();
			}
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param string[] &$rights
	 * @return bool
	 */
	public function onUserGetRights( $user, &$rights ) {
		// checking rights not just for registered users but also for
		// anon (local) users based on name only will allow autocreation of
		// local account based on global rights, see T316303
		$anonUserOK = MediaWikiServices::getInstance()->getMainConfig()->get( 'CentralAuthStrict' );
		if ( MediaWikiServices::getInstance()->getUserNameUtils()->getCanonical( $user->getName() ) !== false ) {
			$centralUser = CentralAuthUser::getInstance( $user );

			if ( $centralUser->exists()
				 && ( $centralUser->isAttached() || ( $anonUserOK && !$user->isRegistered() ) ) ) {
				$extraRights = $centralUser->getGlobalRights();

				$rights = array_merge( $extraRights, $rights );
			}
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param bool &$isLocked
	 * @return bool
	 */
	public function onUserIsLocked( $user, &$isLocked ) {
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists()
			&& ( $centralUser->isAttached() || !$user->isRegistered() )
			&& $centralUser->isLocked()
		) {
			$isLocked = true;
			return false;
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param bool &$isBot
	 * @return bool
	 */
	public function onUserIsBot( $user, &$isBot ) {
		// No need to check global groups if the user is already marked as a bot,
		// and no global groups for unregistered user
		if ( !$isBot && $user->isRegistered() ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			if ( $centralUser->exists()
				&& $centralUser->isAttached()
				&& array_intersect( [ 'bot', 'global-bot' ], $centralUser->getGlobalGroups() )
				&& in_array( 'bot', $centralUser->getGlobalRights() )
			) {
				$isBot = true;
			}
		}

		return true;
	}

	/**
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$user = $out->getUser();
		if ( $user->isRegistered() ) {
			$centralUser = CentralAuthUser::getInstance( $user );
			$vars['wgGlobalGroups'] = ( $centralUser->exists() && $centralUser->isAttached() )
				? $centralUser->getActiveGlobalGroups()
				: [];
		}
	}

	/**
	 * Data to be serialised as JSON for the 'ext.centralauth.centralautologin' module.
	 * @return array
	 */
	public static function getCentralautologinJsData() {
		global $wgCentralAuthLoginWiki;
		$data = [];
		if ( $wgCentralAuthLoginWiki && $wgCentralAuthLoginWiki !== WikiMap::getCurrentWikiId() ) {
			$url = WikiMap::getForeignURL(
				$wgCentralAuthLoginWiki, 'Special:CentralAutoLogin/checkLoggedIn'
			);
			if ( $url !== false ) {
				$params = [
					'type' => 'script',
					'wikiid' => WikiMap::getCurrentWikiId(),
				];
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$data['checkLoggedInURL'] = wfAppendQuery( $url, $params );
			}
		}
		return $data;
	}

	/**
	 * Get a HTML fragment that will trigger central autologin, i.e. try to log in the user on
	 * each of $wgCentralAuthAutoLoginWikis in the background by embedding invisible pixel images
	 * which point to Special:CentralAutoLogin on each of those wikis.
	 *
	 * It also calls Special:CentralAutoLogin/refreshCookies on the central wiki, to refresh
	 * central session cookies if needed (e.g. because the "remember me" setting changed).
	 *
	 * This is typically used on the next page view after a successful login (by setting the
	 * CentralAuthDoEdgeLogin session flag).
	 *
	 * @return string
	 *
	 * @see SpecialCentralAutoLogin
	 * @see PageDisplayHookHandler::onBeforePageDisplay()
	 */
	public static function getEdgeLoginHTML() {
		global $wgCentralAuthLoginWiki;

		$html = '';

		foreach ( self::getAutoLoginWikis() as $domain => $wikiID ) {
			$params = [
				'type' => '1x1',
				'from' => WikiMap::getCurrentWikiId(),
			];
			if ( self::isMobileDomain() ) {
				$params['mobile'] = 1;
			}
			$html .= self::getAuthIconHtml( $wikiID, 'Special:CentralAutoLogin/start', $params, null );
		}

		if ( $wgCentralAuthLoginWiki ) {
			$html .= self::getAuthIconHtml( $wgCentralAuthLoginWiki, 'Special:CentralAutoLogin/refreshCookies', [
				'type' => '1x1',
				'wikiid' => WikiMap::getCurrentWikiId(),
			], null );
		}

		return $html;
	}

	/**
	 * Check whether the user's preferences are such that a UI reload is
	 * recommended.
	 * @param User $user
	 * @return bool
	 */
	public static function isUIReloadRecommended( User $user ) {
		global $wgCentralAuthPrefsForUIReload;
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

		foreach ( $wgCentralAuthPrefsForUIReload as $pref ) {
			if (
				$userOptionsLookup->getOption( $user, $pref ) !==
				$userOptionsLookup->getDefaultOption( $pref, $userFactory->newAnonymous() )
			) {
				return true;
			}
		}

		$hookRunner = new CentralAuthHookRunner( MediaWikiServices::getInstance()->getHookContainer() );

		$recommendReload = false;
		$hookRunner->onCentralAuthIsUIReloadRecommended( $user, $recommendReload );
		return $recommendReload;
	}

	/**
	 * Prevent "canonicalization" of Special:CentralAutoLogin to a localized
	 * Special namespace name. See T56195.
	 * @param WebRequest $request
	 * @param Title $title
	 * @param OutputPage $output
	 * @return bool
	 */
	public function onTestCanonicalRedirect( $request, $title, $output ) {
		return $title->getNamespace() !== NS_SPECIAL ||
			!str_starts_with( $request->getVal( 'title', '' ), 'Special:CentralAutoLogin/' );
	}

	/**
	 * Handler for UserGetReservedNames
	 * @param array &$reservedUsernames
	 */
	public function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = 'Global rename script';
	}

	/**
	 * @param array &$salts
	 * @return bool
	 */
	public function onApiQueryTokensRegisterTypes( &$salts ) {
		$salts += [
			'setglobalaccountstatus' => 'setglobalaccountstatus',
			'deleteglobalaccount' => 'deleteglobalaccount',
		];
		return true;
	}

	/**
	 * @param string[] &$dependencies
	 * @param RL\Context|null $context
	 * @return void
	 */
	public function onResourceLoaderForeignApiModules(
		&$dependencies,
		$context = null
	): void {
		$dependencies[] = 'ext.centralauth.ForeignApi';
	}

	/**
	 * Hook function to prevent logged-in sessions when a user is being
	 * renamed.
	 * @param string &$reason Failure reason to log
	 * @param SessionInfo $info
	 * @param WebRequest $request
	 * @param array|bool $metadata
	 * @param array|bool $data
	 * @return bool
	 */
	public function onSessionCheckInfo(
		&$reason,
		$info,
		$request,
		$metadata,
		$data
	) {
		$name = $info->getUserInfo()->getName();
		if ( $name !== null ) {
			$centralUser = CentralAuthUser::getInstanceByName( $name );
			if ( $centralUser->renameInProgress() ) {
				$reason = 'CentralAuth rename in progress';
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array &$types
	 */
	public function onGetLogTypesOnUser( &$types ) {
		$types[] = 'gblrights';
	}
}
