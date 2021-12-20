<?php

use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameQueue;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionInfo;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthHooks implements
	MediaWiki\Api\Hook\ApiQueryTokensRegisterTypesHook,
	MediaWiki\Block\Hook\GetUserBlockHook,
	MediaWiki\Hook\MakeGlobalVariablesScriptHook,
	MediaWiki\Hook\OtherBlockLogLinkHook,
	MediaWiki\Hook\TestCanonicalRedirectHook,
	MediaWiki\Permissions\Hook\UserGetRightsHook,
	MediaWiki\Preferences\Hook\GetPreferencesHook,
	MediaWiki\ResourceLoader\Hook\ResourceLoaderForeignApiModulesHook,
	MediaWiki\Session\Hook\SessionCheckInfoHook,
	MediaWiki\Hook\GetLogTypesOnUserHook,
	MediaWiki\User\Hook\InvalidateEmailCompleteHook,
	MediaWiki\User\Hook\SpecialPasswordResetOnSubmitHook,
	MediaWiki\User\Hook\UserArrayFromResultHook,
	MediaWiki\User\Hook\UserGetEmailAuthenticationTimestampHook,
	MediaWiki\User\Hook\UserGetEmailHook,
	MediaWiki\User\Hook\UserGetReservedNamesHook,
	MediaWiki\User\Hook\UserIsBotHook,
	MediaWiki\User\Hook\UserIsLockedHook,
	MediaWiki\User\Hook\UserSaveSettingsHook,
	MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook,
	MediaWiki\User\Hook\UserSetEmailHook
{

	/**
	 * Called right after configuration variables have been set.
	 */
	public static function onRegistration() {
		global $wgWikimediaJenkinsCI, $wgCentralAuthDatabase, $wgDBname, $wgSessionProviders,
			$wgCentralIdLookupProvider, $wgOverrideCentralIdLookupProvider;

		// Override $wgCentralAuthDatabase for Wikimedia Jenkins.
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI ) {
			$wgCentralAuthDatabase = $wgDBname;
		}

		// CentralAuthSessionProvider is supposed to replace core
		// CookieSessionProvider, so remove the latter if both are configured
		if ( isset( $wgSessionProviders[MediaWiki\Session\CookieSessionProvider::class] ) &&
			isset( $wgSessionProviders[CentralAuthSessionProvider::class] )
		) {
			unset( $wgSessionProviders[MediaWiki\Session\CookieSessionProvider::class] );
		}

		// Assume they want CentralAuth as the default central ID provider, unless
		// already configured otherwise.
		if ( $wgCentralIdLookupProvider === 'local' && $wgOverrideCentralIdLookupProvider ) {
			$wgCentralIdLookupProvider = 'CentralAuth';
		}
	}

	/**
	 * Callback to register with $wgExtensionFunctions to complete configuration
	 * after other initial configuration has completed. This can be used to
	 * avoid extension ordering issues and do things that are dependent on
	 * feature flags.
	 */
	public static function onRunExtensionFunctions() {
		global $wgAutoloadClasses, $wgExtensionCredits, $wgHooks;
		global $wgSpecialPages, $wgResourceModules;
		global $wgCentralAuthEnableGlobalRenameRequest;
		$caBase = __DIR__ . '/..';

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Renameuser' ) ) {
			// Credits should only appear on wikis with Extension:Renameuser
			// installed
			$wgExtensionCredits['specialpage'][] = [
				'path' => "{$caBase}/extension.json",
				'name' => 'Renameuser for CentralAuth',
				'url' => 'https://www.mediawiki.org/wiki/Extension:CentralAuth',
				'author' => [ 'Kunal Mehta', 'Marius Hoch', 'Chris Steipp' ],
				'descriptionmsg' => 'centralauth-rename-desc',
				'license-name' => 'GPL-2.0-or-later',
			];
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'AntiSpoof' ) ) {
			// If AntiSpoof is installed, we can do some AntiSpoof stuff for CA
			$wgExtensionCredits['antispam'][] = [
				'path' => "{$caBase}/extension.json",
				'name' => 'AntiSpoof for CentralAuth',
				'url' => 'https://www.mediawiki.org/wiki/Extension:CentralAuth',
				'author' => 'Sam Reed',
				'descriptionmsg' => 'centralauth-antispoof-desc',
				'license-name' => 'GPL-2.0-or-later',
			];
			$wgAutoloadClasses['CentralAuthSpoofUser'] =
				"$caBase/AntiSpoof/CentralAuthSpoofUser.php";
			$wgAutoloadClasses['CentralAuthAntiSpoofHooks'] =
				"$caBase/AntiSpoof/CentralAuthAntiSpoofHooks.php";

			$wgHooks['LocalUserCreated'][] =
				'CentralAuthAntiSpoofHooks::asLocalUserCreated';
			$wgHooks['RenameUserComplete'][] =
				'CentralAuthAntiSpoofHooks::asAddRenameUserHook';
		}

		if ( $wgCentralAuthEnableGlobalRenameRequest ) {
			$wgExtensionCredits['specialpage'][] = [
				'path' => "{$caBase}/extension.json",
				'name' => 'GlobalRenameRequest',
				'author' => 'Bryan Davis',
				'url' => '//www.mediawiki.org/wiki/Extension:CentralAuth',
				'descriptionmsg' => 'globalrenamerequest-desc',
				'license-name' => 'GPL-2.0-or-later',
			];
			$wgSpecialPages['GlobalRenameRequest'] = [
				'class' => SpecialGlobalRenameRequest::class,
				'services' => [
					'CentralAuth.GlobalRenameDenylist',
					'UserNameUtils',
					'CentralAuth.GlobalRenameRequestStore',
				]
			];

			$wgExtensionCredits['specialpage'][] = [
				'path' => "{$caBase}/extension.json",
				'name' => 'GlobalRenameQueue',
				'author' => 'Bryan Davis',
				'url' => '//www.mediawiki.org/wiki/Extension:CentralAuth',
				'descriptionmsg' => 'globalrenamequeue-desc',
				'license-name' => 'GPL-2.0-or-later',
			];
			$wgSpecialPages['GlobalRenameQueue'] = [
				'class' => SpecialGlobalRenameQueue::class,
				'services' => [
					'UserNameUtils',
					'DBLoadBalancerFactory',
					'CentralAuth.CentralAuthDatabaseManager',
					'CentralAuth.CentralAuthUIService',
					'CentralAuth.GlobalRenameRequestStore',
				],
			];
			$wgResourceModules['ext.centralauth.globalrenamequeue'] = [
				'scripts'        => 'ext.centralauth.globalrenamequeue.js',
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
				'messages' => [
					'centralauth-rename-confirm',
					'centralauth-rename-deny'
				]
			];
			$wgResourceModules['ext.centralauth.globalrenamequeue.styles'] = [
				'position'		=> 'top',
				'styles'        => 'ext.centralauth.globalrenamequeue.less',
				'localBasePath' => "{$caBase}/modules",
				'remoteExtPath' => 'CentralAuth/modules',
			];
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
			$manageButtons[] = new \OOUI\ButtonWidget( [
				'href' => SpecialPage::getTitleFor( 'MergeAccount' )->getLinkURL(),
				'label' => wfMessage( 'centralauth-prefs-manage' )->text(),
			] );
		}

		// Add "View your global account info" button
		$manageButtons[] = new \OOUI\ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'CentralAuth', $user->getName() )->getLinkURL(),
			'label' => wfMessage( 'centralauth-prefs-view' )->text(),
		] );

		$manageLinkList = (string)( new \OOUI\HorizontalLayout( [ 'items' => $manageButtons ] ) );

		$preferences['globalaccountstatus'] = [
			'section' => 'personal/info',
			'label-message' => 'centralauth-prefs-status',
			'type' => 'info',
			'raw' => true,
			'default' => $manageLinkList
		];

		// Display a notice about the user account status with an alert icon
		if ( isset( $message ) ) {
			$messageIconWidget = (string)new \OOUI\IconWidget( [
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
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @param ContentSecurityPolicy $csp From OutputPage::getCsp
	 * @return string
	 */
	public static function getDomainAutoLoginHtml(
		User $user,
		CentralAuthUser $centralUser,
		ContentSecurityPolicy $csp
	) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;

		// No other domains
		if ( !$wgCentralAuthAutoLoginWikis ) {
			$inject_html = wfMessage( 'centralauth-login-no-others' )->escaped();
		} else {
			$inject_html = '<div class="centralauth-login-box"><p>' .
				wfMessage( 'centralauth-login-progress' )
					->params( $user->getName() )
					->numParams( count( $wgCentralAuthAutoLoginWikis ) )
					->escaped() . "</p>\n<p>";
			foreach ( $wgCentralAuthAutoLoginWikis as $alt => $wikiID ) {
				$wiki = WikiMap::getWiki( $wikiID );
				// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
				$params = [
					'type' => 'icon',
					'from' => WikiMap::getCurrentWikiId(),
				];
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$url = wfAppendQuery(
					$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
					$params
				);
				$csp->addDefaultSrc( wfParseUrl( $url )['host'] );
				$inject_html .= Xml::element( 'img',
					[
						'src' => $url,
						'alt' => $alt,
						'title' => $alt,
						'width' => 20,
						'height' => 20,
						'style' => 'border: 1px solid #ccc;',
					]
				);
			}
			$inject_html .= "</p></div>\n";
		}

		if ( $wgCentralAuthLoginWiki ) {
			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/refreshCookies' ), [
				'type' => '1x1',
				'wikiid' => WikiMap::getCurrentWikiId(),
				'proto' => RequestContext::getMain()->getRequest()->detectProtocol(),
			] );
			$csp->addDefaultSrc( wfParseUrl( $url )['host'] );
			$inject_html .= Xml::element( 'img',
				[
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none; position: absolute;',
				]
			);
		}

		return $inject_html;
	}

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
		if ( $user->isRegistered() ) {
			$centralUser = CentralAuthUser::getInstance( $user );

			if ( $centralUser->exists() && $centralUser->isAttached() ) {
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
	 * Make sure a user is hidden if their global account is hidden.
	 * If a user's global account is hidden (suppressed):
	 * - if locally blocked and hidden, do nothing
	 * - if not blocked, add a system block with a suppression
	 * - if blocked but not hidden, make a new composite block
	 *   containing the existing blocks plus a system block with a
	 *   suppression
	 *
	 * @param User $user
	 * @param string|null $ip
	 * @param AbstractBlock|null &$block
	 * @return bool
	 */
	public function onGetUserBlock( $user, $ip, &$block ) {
		if ( $block && $block->getHideName() ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists()
			&& ( $centralUser->isAttached() || !$user->isRegistered() )
			&& $centralUser->getHiddenLevel() === CentralAuthUser::HIDDEN_OVERSIGHT
		) {
			$hideUserBlock = new SystemBlock( [
				'address' => $user,
				'hideName' => true,
				'systemBlock' => 'hideuser',
			] );

			if ( $block === null ) {
				$block = $hideUserBlock;
				return false;
			}

			$blocks = $block instanceof CompositeBlock ?
				$block->getOriginalBlocks() :
				[ $block ];

			$blocks[] = $hideUserBlock;
			$block = new CompositeBlock( [
				'address' => $ip,
				'reason' => new Message( 'blockedtext-composite-reason' ),
				'originalBlocks' => $blocks,
			] );

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
		if ( $user->isRegistered() ) {
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
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$vars['wgGlobalGroups'] = $centralUser->getGlobalGroups();
			}
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
	 * Build the HTML containing the 1x1 images
	 * @return string
	 */
	public static function getEdgeLoginHTML() {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;

		// Put images inside a div so that other code that manipulates page content can
		// explicitly ignore them.
		$html = Xml::openElement( 'div',
			[
				'id' => 'central-auth-images',
				'style' => 'position: absolute;',
			]
		);

		foreach ( $wgCentralAuthAutoLoginWikis as $wiki ) {
			$wiki = WikiMap::getWiki( $wiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$params = [
				'type' => '1x1',
				'from' => WikiMap::getCurrentWikiId(),
			];
			$url = wfAppendQuery(
				$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
				$params
			);
			if ( self::isMobileDomain() ) {
				$params['mobile'] = 1;
				// Do autologin on the mobile domain for each wiki
				$url = MobileContext::singleton()->getMobileUrl(
					wfAppendQuery(
						$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
						$params
					)
				);
			}
			$html .= Xml::element( 'img',
				[
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none;',
				]
			);
		}

		if ( $wgCentralAuthLoginWiki ) {
			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$url = wfAppendQuery( $wiki->getFullUrl( 'Special:CentralAutoLogin/refreshCookies' ), [
				'type' => '1x1',
				'wikiid' => WikiMap::getCurrentWikiId(),
				'proto' => RequestContext::getMain()->getRequest()->detectProtocol(),
			] );
			$html .= Xml::element( 'img',
				[
					'src' => $url,
					'alt' => '',
					'title' => '',
					'width' => 1,
					'height' => 1,
					'style' => 'border: none;',
				]
			);
		}

		$html .= Xml::closeElement( 'div' );

		return $html;
	}

	/**
	 * Creates a link to the global lock log
	 * @param array &$otherBlockLink Message with a link to the global block log
	 * @param string $user The username to be checked
	 * @return bool true
	 */
	public function onOtherBlockLogLink( &$otherBlockLink, $user ) {
		if ( IPUtils::isIPAddress( $user ) ) {
			return true; // Return if it is an IP as only usernames can be locked.
		}

		$caUser = CentralAuthUser::getInstanceByName( $user );
		if ( $caUser->isLocked() && in_array( WikiMap::getCurrentWikiId(), $caUser->listAttached() ) ) {
			$otherBlockLink[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-centralauth-lock-loglink plainlinks' ],
				wfMessage( 'centralauth-block-already-locked', $user )->parse()
			);
		}
		return true;
	}

	/**
	 * Check whether the user's preferences are such that a UI reload is
	 * recommended.
	 * @param User $user
	 * @return bool
	 */
	public static function isUIReloadRecommended( User $user ) {
		global $wgCentralAuthPrefsForUIReload;
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

		foreach ( $wgCentralAuthPrefsForUIReload as $pref ) {
			if ( $userOptionsLookup->getOption( $user, $pref ) !== $userOptionsLookup->getDefaultOption( $pref ) ) {
				return true;
			}
		}

		$recommendReload = false;
		Hooks::run( 'CentralAuthIsUIReloadRecommended', [ $user, &$recommendReload ] );
		return $recommendReload;
	}

	/**
	 * Prevent "canonicalization" of Special:CentralAutoLogin to a localized
	 * Special namespace name. See bug 54195.
	 * @param WebRequest $request
	 * @param Title $title
	 * @param OutputPage $output
	 * @return bool
	 */
	public function onTestCanonicalRedirect( $request, $title, $output ) {
		return $title->getNamespace() !== NS_SPECIAL ||
			strncmp( $request->getVal( 'title', '' ), 'Special:CentralAutoLogin/', 25 ) !== 0;
	}

	/**
	 * Hook for UserMerge extension after an account is deleted
	 * @param User &$user account that was just deleted
	 * @return bool
	 */
	public static function onDeleteAccount( User &$user ) {
		$caUser = CentralAuthUser::getPrimaryInstance( $user );

		if ( $caUser->isAttached() ) {
			// Clean up localuser table.
			$caUser->adminUnattach( [ WikiMap::getCurrentWikiId() ] );
		}

		// Clean up localnames table.
		$caUser->removeLocalName( WikiMap::getCurrentWikiId() );

		return true;
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
	 * @param ResourceLoaderContext|null $context
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
