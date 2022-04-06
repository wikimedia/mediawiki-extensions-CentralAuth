<?php

use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameQueue;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\Special\SpecialUsersWhoWillBeRenamed;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionInfo;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthHooks implements
	MediaWiki\Api\Hook\APIGetAllowedParamsHook,
	MediaWiki\Api\Hook\ApiQueryTokensRegisterTypesHook,
	MediaWiki\Auth\Hook\LocalUserCreatedHook,
	MediaWiki\Block\Hook\GetUserBlockHook,
	MediaWiki\Hook\BeforePageDisplayHook,
	MediaWiki\Hook\ContentSecurityPolicyDefaultSourceHook,
	MediaWiki\Hook\ContentSecurityPolicyScriptSourceHook,
	MediaWiki\Hook\ImportHandleUnknownUserHook,
	MediaWiki\Hook\LogEventsListGetExtraInputsHook,
	MediaWiki\Hook\MakeGlobalVariablesScriptHook,
	MediaWiki\Hook\OtherBlockLogLinkHook,
	MediaWiki\Hook\PasswordPoliciesForUserHook,
	MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook,
	MediaWiki\Hook\SpecialLogAddLogSearchRelationsHook,
	MediaWiki\Hook\TestCanonicalRedirectHook,
	MediaWiki\Hook\UserLoginCompleteHook,
	MediaWiki\Hook\UserLogoutCompleteHook,
	MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook,
	MediaWiki\Permissions\Hook\GetUserPermissionsErrorsExpensiveHook,
	MediaWiki\Permissions\Hook\UserGetRightsHook,
	MediaWiki\Preferences\Hook\GetPreferencesHook,
	MediaWiki\ResourceLoader\Hook\ResourceLoaderForeignApiModulesHook,
	MediaWiki\Session\Hook\SessionCheckInfoHook,
	MediaWiki\SpecialPage\Hook\SpecialPage_initListHook,
	MediaWiki\Hook\GetLogTypesOnUserHook,
	MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook,
	MediaWiki\Hook\UnitTestsBeforeDatabaseTeardownHook,
	MediaWiki\User\Hook\InvalidateEmailCompleteHook,
	MediaWiki\User\Hook\SpecialPasswordResetOnSubmitHook,
	MediaWiki\User\Hook\UserArrayFromResultHook,
	MediaWiki\User\Hook\UserGetEmailAuthenticationTimestampHook,
	MediaWiki\User\Hook\UserGetEmailHook,
	MediaWiki\User\Hook\UserGetReservedNamesHook,
	MediaWiki\User\Hook\UserIsBotHook,
	MediaWiki\User\Hook\UserIsLockedHook,
	MediaWiki\User\Hook\UserLogoutHook,
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
				'path' => "{$caBase}/CentralAuth.php",
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
				'path' => "{$caBase}/CentralAuth.php",
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
				'path' => "{$caBase}/CentralAuth.php",
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
				]
			];

			$wgExtensionCredits['specialpage'][] = [
				'path' => "{$caBase}/CentralAuth.php",
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
					'CentralAuth.CentralAuthUIService',
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
	 * @param string $type Unused
	 * @param string $action
	 * @param Title $title
	 * @param Skin|null $skin If null, we want to use the wiki content language,
	 *   since that will go to the IRC feed.
	 * @param array $params
	 * @param bool $filterWikilinks
	 * @return string
	 */
	public static function onHandleWikiSetLogEntry(
		$type, $action, $title, $skin, $params, $filterWikilinks = false
	) {
		if ( $skin ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$link = $linkRenderer->makeLink( $title, $params[0] );
		} else {
			$link = $params[0];
		}

		switch ( $action ) {
			case 'newset':
				$args = [ WikiSet::formatType( $params[1] ), $params[2] ];
				break;
			case 'setrename':
				$args = [ $params[1] ];
				break;
			case 'setnewtype':
				$args = [ WikiSet::formatType( $params[1] ), WikiSet::formatType( $params[2] ) ];
				break;
			case 'setchange':
				$args = [
					$params[1] ?: wfMessage( 'rightsnone' )->escaped(),
					$params[2] ?: wfMessage( 'rightsnone' )->escaped()
				];
				break;
			default: // 'deleteset'
				$args = [];
		}

		// Give grep a chance to find the usages:
		// centralauth-rightslog-entry-newset, centralauth-rightslog-entry-setrename,
		// centralauth-rightslog-entry-setnewtype, centralauth-rightslog-entry-setchange,
		// centralauth-rightslog-entry-deleteset
		$msg = wfMessage( "centralauth-rightslog-entry-{$action}", $link )->params( $args );
		if ( $skin ) {
			return $msg->text();
		} else {
			return $msg->inContentLanguage()->text();
		}
	}

	/**
	 * Format global group rename log entries
	 *
	 * @param string $type Unused
	 * @param string $action Unused
	 * @param Title $title Unused
	 * @param Skin|null $skin If null, we want to use the wiki content language, since that will
	 *   go to the IRC feed.
	 * @param array $params
	 * @param bool $filterWikilinks Unused
	 *
	 * @return string
	 */
	public static function onHandleGrouprenameLogEntry(
		$type, $action, $title, $skin, $params, $filterWikilinks = false
	) {
		if ( isset( $params[1] ) ) {
			// current log format
			$newName = $params[0];
			$oldName = $params[1];
		} else {
			// old log format
			$newName = $title->getSubpageText();
			$oldName = $params[0];
		}
		$newTitle = Title::newFromText( $newName );
		$oldTitle = Title::newFromText( $oldName );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		if ( $skin && $newTitle ) {
			$new = $linkRenderer->makeLink(
				$newTitle,
				$newName
			);
		} else {
			$new = htmlspecialchars( $newName );
		}
		if ( $skin && $oldTitle ) {
			$old = $linkRenderer->makeLink(
				$oldTitle,
				$oldName
			);
		} else {
			$old = htmlspecialchars( $oldName );
		}

		$msg = wfMessage( 'centralauth-rightslog-entry-grouprename' )
			->rawParams( $new, $old );
		if ( $skin ) {
			return $msg->text();
		} else {
			return $msg->inContentLanguage()->text();
		}
	}

	/**
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		global $wgCentralAuthEnableUsersWhoWillBeRenamed;
		global $wgCentralAuthEnableGlobalRenameRequest;
		if ( $wgCentralAuthEnableUsersWhoWillBeRenamed ) {
			$list['UsersWhoWillBeRenamed'] = [
				'class' => SpecialUsersWhoWillBeRenamed::class,
			];
		}
		if ( $wgCentralAuthEnableGlobalRenameRequest ) {
			$list['GlobalRenameRequest'] = [
				'class' => SpecialGlobalRenameRequest::class,
				'services' => [
					'CentralAuth.GlobalRenameDenylist',
					'UserNameUtils',
				]
			];
			$list['GlobalRenameQueue'] = [
				'class' => SpecialGlobalRenameQueue::class,
				'services' => [
					'UserNameUtils',
					'DBLoadBalancerFactory',
					'CentralAuth.CentralAuthUIService',
				],
			];
		}
	}

	/**
	 * Make sure migration information in localuser table is populated
	 * on local account creation
	 * @param User $user
	 * @param bool $autocreated
	 * @return bool
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		$centralUser = CentralAuthUser::getPrimaryInstance( $user );

		// If some other AuthManager PrimaryAuthenticationProvider is creating
		// the user, we should still create a central user for them so
		// CentralAuthIdLookup can have an ID for this new user right away.
		if ( !$centralUser->exists() && !$centralUser->listUnattached() ) {
			if ( $centralUser->register( null, $user->getEmail() ) ) {
				$centralUser->attach( wfWikiID(), 'new' );
				CentralAuthUtils::getCentralDB()->onTransactionCommitOrIdle(
					static function () use ( $centralUser ) {
						CentralAuthUtils::scheduleCreationJobs( $centralUser );
					},
					__METHOD__
				);
			}
		}

		$centralUser->addLocalName( wfWikiID() );

		return true;
	}

	/**
	 * Auto-create a user on import
	 * @param string $name
	 * @return bool
	 */
	public function onImportHandleUnknownUser( $name ) {
		$user = User::newFromName( $name );
		if ( $user ) {
			$centralUser = CentralAuthUser::getPrimaryInstance( $user );

			if ( $centralUser->exists() && CentralAuthUtils::autoCreateUser( $user )->isGood() ) {
				$centralUser->invalidateCache();
				return false;
			}
		}

		return true;
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
	 * @param string &$inject_html
	 * @param bool|null $direct Was this directly after a login? (see T140853)
	 * @return bool
	 */
	public function onUserLoginComplete( $user, &$inject_html, $direct = null ) {
		global $wgCentralAuthCookies;

		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		if ( $direct === null ) { // B/C
			$direct = RequestContext::getMain()->getRequest()->wasPosted();
		}

		// Redirect to the central wiki and back to complete login, if necessary
		$centralUser = CentralAuthUser::getInstance( $user );
		self::doCentralLoginRedirect( $user, $centralUser, $inject_html, $direct );

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
					'from' => wfWikiID(),
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
				'wikiid' => wfWikiID(),
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

	/**
	 * @param User $user
	 * @param CentralAuthUser $centralUser
	 * @param string &$inject_html
	 * @param bool $direct Was this directly after a login? (see T140853)
	 * @return bool
	 */
	protected static function doCentralLoginRedirect(
		User $user, CentralAuthUser $centralUser, &$inject_html, $direct
	) {
		global $wgCentralAuthLoginWiki, $wgSecureLogin, $wgForceHTTPS;

		$context = RequestContext::getMain();
		$request = $context->getRequest();

		if ( !$wgCentralAuthLoginWiki || defined( 'MW_API' ) ) {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
			return true;
		}

		// Check that this is actually for a special login page view
		$title = $context->getTitle();
		if ( $direct && $title && ( $title->isSpecial( 'Userlogin' ) ||
			$title->isSpecial( 'CreateAccount' ) )
		) {
			// User will be redirected to Special:CentralLogin/start (central wiki),
			// then redirected back to Special:CentralLogin/complete (this wiki).
			// Sanity check that "returnto" is not one of the central login pages. If it
			// is, then clear the "returnto" options (LoginForm will use the main page).
			$returnTo = $request->getVal( 'returnto', '' );
			$returnToQuery = $request->getVal( 'returntoquery', '' );
			$returnToTitle = Title::newFromText( $returnTo );
			if ( $returnToTitle && $returnToTitle->isSpecial( 'CentralLogin' ) ) {
				$returnTo = '';
				$returnToQuery = '';
			}

			// Determine the final protocol of page, after login
			if ( $wgForceHTTPS ) {
				$finalProto = 'https';
				$secureCookies = true;
			} else {
				$finalProto = WebRequest::detectProtocol();
				$secureCookies = ( $finalProto === 'https' );

				if ( $wgSecureLogin ) {
					$finalProto = 'http';
					$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

					if ( $request->getBool( 'wpForceHttps', false ) ||
						$request->getSession()->shouldForceHTTPS() ||
						$userOptionsLookup->getBoolOption( $user, 'prefershttps' )
					) {
						$finalProto = 'https';
					}

					$secureCookies = ( ( $finalProto === 'https' ) &&
						$userOptionsLookup->getBoolOption( $user, 'prefershttps' ) );
				}
			}

			$remember = $request->getSession()->shouldRememberUser();
			$type = $title->isSpecial( 'CreateAccount' ) ? 'signup' : '';

			// When POSTs triggered from Special:CentralLogin/start are sent back to
			// this wiki, the token will be checked to see if it was signed with this.
			// This is needed as Special:CentralLogin/start only takes a token argument
			// and we need to make sure an agent requesting such a URL actually initiated
			// the login request that spawned that token server-side.
			$secret = MWCryptRand::generateHex( 32 );
			$request->setSessionData( 'CentralAuth:autologin:current-attempt', [
				'secret'        => $secret,
				'remember'      => $remember,
				'returnTo'      => $returnTo,
				'returnToQuery' => $returnToQuery,
				// cookies set secure or not (local CentralAuth cookies)
				'stickHTTPS'    => $secureCookies,
				'finalProto'    => $finalProto, // final page http or https
				'type'          => $type
			] );

			// Create a new token to pass to Special:CentralLogin/start (central wiki)
			$tokenStore = CentralAuthUtils::getTokenStore();
			$token = MWCryptRand::generateHex( 32 );
			$key = CentralAuthUtils::memcKey( 'central-login-start-token', $token );
			$data = [
				'secret'        => $secret,
				'name'          => $centralUser->getName(),
				'guid'          => $centralUser->getId(),
				'wikiId'        => wfWikiID(),
				'secureCookies' => $secureCookies, // (bool) cookies secure or not
				'finalProto'    => $finalProto, // http or https for very final page
				// current proto (in case login is https, but final page is http)
				'currentProto'  => WebRequest::detectProtocol()
			];
			Hooks::run( 'CentralAuthLoginRedirectData', [ $centralUser, &$data ] );
			$tokenStore->set( $key, $data, $tokenStore::TTL_MINUTE );

			$query = [ 'token' => $token ];

			$wiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
			$context->getOutput()->redirect( // expands to PROTO_CURRENT
				wfAppendQuery( $wiki->getFullUrl( 'Special:CentralLogin/start' ), $query )
			);
			// Set $inject_html to some text to bypass the LoginForm redirection
			$inject_html .= '<!-- do CentralAuth redirect -->';
		} else {
			// Mark the session to include edge login imgs on the next pageview
			$request->setSessionData( 'CentralAuthDoEdgeLogin', true );
		}

		return true;
	}

	private static function isMobileDomain() {
		return ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' )
			&& MobileContext::singleton()->usingMobileDomain();
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function onUserLogout( $user ) {
		global $wgCentralAuthCookies;

		if ( !$wgCentralAuthCookies ) {
			// Use local sessions only.
			return true;
		}

		$username = $user->getName();
		DeferredUpdates::addCallableUpdate( static function () use ( $username ) {
			$centralUser = CentralAuthUser::getPrimaryInstanceByName( $username );
			if ( $centralUser->exists() ) {
				$centralUser->resetAuthToken();
			}
		} );

		return true;
	}

	/**
	 * @param User $user
	 * @param string &$inject_html
	 * @param string $oldName Unused
	 * @return bool
	 */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		global $wgCentralAuthCookies, $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		$wikis = $wgCentralAuthAutoLoginWikis;
		if ( $wgCentralAuthLoginWiki ) {
			$wikis[$wgCentralAuthLoginWiki] = $wgCentralAuthLoginWiki;
		}

		$csp = RequestContext::getMain()->getOutput()->getCSP();
		// No other domains
		if ( !$wikis ) {
			$inject_html = wfMessage( 'centralauth-logout-no-others' )->escaped();
		} else {
			$inject_html = '<div class="centralauth-logout-box"><p>' .
				wfMessage( 'centralauth-logout-progress' )
					->params( $user->getName() )
					->numParams( count( $wikis ) )
					->escaped() . "</p>\n<p>";
			foreach ( $wikis as $alt => $wikiID ) {
				$wiki = WikiMap::getWiki( $wikiID );
				// Use WikiReference::getFullUrl(), returns a protocol-relative URL if needed
				$url = wfAppendQuery(
					$wiki->getFullUrl( 'Special:CentralAutoLogin/deleteCookies' ), [
						'type' => 'icon',
					]
				);
				$csp->addDefaultSrc( wfParseUrl( $wiki->getCanonicalServer() )['host'] );
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

		return true;
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
	 * @param int $id User ID
	 * @param User $user
	 * @param SpecialPage $sp
	 * @return bool
	 */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( !$user->isRegistered() ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached()
			|| !$centralUser->isLocked() || $centralUser->isHidden()
		) {
			return true;
		}

		$out = $sp->getOutput();
		$count = LogEventsList::showLogExtract(
			$out,
			[ 'globalauth' ],
			MWNamespace::getCanonicalName( NS_USER ) . ":{$user}@global",
			'',
			[
				'lim' => 1,
				'showIfEmpty' => false,
				'msgKey' => [
					'centralauth-contribs-locked-log',
					$user->getName()
				],
				'offset' => '',
			]
		);

		if ( $count === 0 ) { // we couldn't load the log entry
			$out->wrapWikiMsg( '<div class="warningbox mw-warning-with-logexcerpt">$1</div>',
				[ 'centralauth-contribs-locked', $user ]
			);
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
		if ( $wgCentralAuthLoginWiki && $wgCentralAuthLoginWiki !== wfWikiID() ) {
			$url = WikiMap::getForeignURL(
				$wgCentralAuthLoginWiki, 'Special:CentralAutoLogin/checkLoggedIn'
			);
			if ( $url !== false ) {
				$params = [
					'type' => 'script',
					'wikiid' => wfWikiID(),
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
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param string &$result Message key
	 * @return bool
	 */
	public function onGetUserPermissionsErrorsExpensive( $title, $user, $action, &$result ) {
		global $wgCentralAuthLockedCanEdit, $wgDisableUnmergedEditing;
		if ( $action == 'read' || !$user->isRegistered() ) {
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );

		if ( $wgDisableUnmergedEditing
			&& ( $action === 'edit' || $action === 'delete' )
			&& !$centralUser->exists()
			&& !$title->inNamespaces( NS_USER_TALK, NS_PROJECT_TALK )
		) {
			$result = 'centralauth-error-unmerged';
			return false;
		}

		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}
		if (
			$centralUser->isOversighted() || // Oversighted users should *never* be able to edit
			( $centralUser->isLocked() &&
				!in_array( $title->getPrefixedText(), $wgCentralAuthLockedCanEdit ) )
		) {
			$result = 'centralauth-error-locked';
			return false;
		}
		return true;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @todo Add 1x1 images somewhere besides page content
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		global $wgCentralAuthLoginWiki, $wgCentralAuthUseEventLogging;

		if ( $out->getRequest()->getSession()->getProvider()
			instanceof CentralAuthTokenSessionProvider
		) {
			// Prevent user scripts and styles when centralauthtoken is in use
			$out->reduceAllowedModules(
				ResourceLoaderModule::TYPE_SCRIPTS, ResourceLoaderModule::ORIGIN_USER_SITEWIDE
			);
			$out->reduceAllowedModules(
				ResourceLoaderModule::TYPE_STYLES, ResourceLoaderModule::ORIGIN_USER_SITEWIDE
			);
		}

		if ( !$out->getUser()->isRegistered() ) {
			if ( $wgCentralAuthLoginWiki && wfWikiID() !== $wgCentralAuthLoginWiki ) {
				// Let the frontend know if this is a mobile domain, T100413
				$out->addJsConfigVars(
					'wgCentralAuthMobileDomain',
					self::isMobileDomain()
				);
				$out->addModules( 'ext.centralauth.centralautologin' );

				// For non-JS clients. Use WikiMap to avoid localization of the
				// 'Special' namespace, see bug 54195.
				$wiki = WikiMap::getWiki( wfWikiID() );

				$loginWiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
				if ( $wiki->getCanonicalServer() !== $loginWiki->getCanonicalServer() ) {
					$out->addHeadItem( 'centralauth-dns-prefetch', Html::element( 'link', [
						'rel' => 'dns-prefetch',
						'href' => preg_replace( '/^https?:/', '', $loginWiki->getCanonicalServer() ),
					] ) );
				}

				$params = [
					'type' => '1x1',
				];
				if ( self::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$url = wfAppendQuery(
					$wiki->getFullUrl( 'Special:CentralAutoLogin/start' ),
					$params
				);
				$out->addHTML( '<noscript>' . Xml::element( 'img',
					[
						'src' => $url,
						'alt' => '',
						'title' => '',
						'width' => 1,
						'height' => 1,
						'style' => 'border: none; position: absolute;',
					]
				) . '</noscript>' );
			}
		} else {
			$centralUser = CentralAuthUser::getInstance( $out->getUser() );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$out->addModules( 'ext.centralauth.centralautologin.clearcookie' );
			}

			if ( $out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' ) ) {
				$out->getRequest()->setSessionData( 'CentralAuthDoEdgeLogin', null );
				$out->addHTML( self::getEdgeLoginHTML() );

				if ( $wgCentralAuthUseEventLogging ) {
					// Need to correlate user_id across wikis
					EventLogging::logEvent( 'CentralAuth', 5690875,
						[ 'version' => 1,
							'userId' => $centralUser->getId(),
							'action' => 'sul2-autologin-login'
						]
					);
				}
			}
		}
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
				'from' => wfWikiID(),
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
				'wikiid' => wfWikiID(),
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
	 * Add other domains as CSP source if auto-login is going to be attempted
	 *
	 * This is adding domains for images (and other req types), not scripts.
	 *
	 * @note We can't do $out->getCSP()->addDefaultSrc in onBeforePageDisplay,
	 * because that hook runs after the header is already outputted.
	 * @param string[] &$defaultSrc Array of allowed CSP sources.
	 * @param array $policyConfig
	 * @param string $mode
	 */
	public function onContentSecurityPolicyDefaultSource(
		&$defaultSrc,
		$policyConfig,
		$mode
	) {
		global $wgCentralAuthLoginWiki, $wgCentralAuthAutoLoginWikis;
		$out = RequestContext::getMain()->getOutput();
		// So possibilities:
		// * We are doing edge login because initial login was via API and this is next request.
		// * We are doing edge login because JS loaded Special:CentralAutoLogin/start or /checkCookies
		//   and user is logged in on a different wiki, which eventually loads edge html.
		if (
			!$out->getUser()->isRegistered() ||
				$out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' )
		) {
			foreach ( $wgCentralAuthAutoLoginWikis as $wiki ) {
				$wiki = WikiMap::getWiki( $wiki );
				if ( self::isMobileDomain() ) {
					$url = MobileContext::singleton()->getMobileUrl(
						$wiki->getFullUrl( 'Special:CentralAutoLogin/start' )
					);
					$defaultSrc[] = wfParseUrl( $url )['host'];
				} else {
					$defaultSrc[] = wfParseUrl( $wiki->getCanonicalServer() )['host'];
				}
			}
		}

		if ( !$out->getUser()->isRegistered() && $wgCentralAuthLoginWiki ) {
			// For the non-js case, there is local image loaded, but it redirects to
			// central wiki, so include it.
			$loginWiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			if ( self::isMobileDomain() ) {
				$url = MobileContext::singleton()->getMobileUrl(
					$loginWiki->getFullUrl( 'Special:CentralAutoLogin/checkLoggedIn' )
				);
				$defaultSrc[] = wfParseUrl( $url )['host'];
			} else {
				$defaultSrc[] = wfParseUrl( $loginWiki->getCanonicalServer() )['host'];
			}
		}
	}

	/**
	 * Add other domains as CSP source if auto-login is going to be attempted
	 *
	 * This is adding domains for scripts but not images.
	 *
	 * This is basically for ext.centralauth.centralautologin and corresponds
	 * to self::getCentralautologinJsData
	 *
	 * @note We can't do $out->getCSP()->addScriptSrc() in onBeforePageDisplay,
	 * because that hook runs after the header is already outputted.
	 * @param string[] &$scriptSrc Array of allowed CSP sources.
	 * @param array $policyConfig
	 * @param string $mode
	 */
	public function onContentSecurityPolicyScriptSource(
		&$scriptSrc,
		$policyConfig,
		$mode
	) {
		global $wgCentralAuthLoginWiki;
		$out = RequestContext::getMain()->getOutput();
		if ( $wgCentralAuthLoginWiki && !$out->getUser()->isRegistered() ) {
			$loginWiki = WikiMap::getWiki( $wgCentralAuthLoginWiki );
			$scriptSrc[] = wfParseUrl( $loginWiki->getCanonicalServer() )['host'];
		}
	}

	/**
	 * @param mixed $auth Unused
	 * @param User $user
	 * @param array &$params
	 * @return bool
	 */
	public static function onSecurePoll_GetUserParams( $auth, $user, &$params ) {
		if ( !$user->isRegistered() ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !( $centralUser->exists() && $centralUser->isAttached() ) ) {
			return true;
		}

		$wikiID = $centralUser->getHomeWiki();
		if ( strval( $wikiID ) === '' ) {
			return true;
		}

		$wiki = WikiMap::getWiki( $wikiID );
		$wikiUrl = $wiki->getUrl( '' );
		$parts = explode( '/', $wikiUrl );
		if ( isset( $parts[2] ) ) {
			$params['properties']['ca-local-domain'] = $params['domain'];
			$params['domain'] = $parts[2];
		}
		$params['properties']['ca-local-url'] = $params['url'];
		$params['url'] = $wiki->getUrl(
			MWNamespace::getCanonicalName( NS_USER ) . ':' . $user->getTitleKey()
		);
		return true;
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
		if ( $caUser->isLocked() && in_array( wfWikiID(), $caUser->listAttached() ) ) {
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
			$caUser->adminUnattach( [ wfWikiID() ] );
		}

		// Clean up localnames table.
		$caUser->removeLocalName( wfWikiID() );

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
	 * Inject the "centralauthtoken" parameter into the API
	 * @param ApiBase $module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags
	 * @return bool
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		global $wgCentralAuthCookies;
		if ( !$wgCentralAuthCookies ) {
			return true;
		}

		if ( $module instanceof ApiMain ) {
			$params['centralauthtoken'] = [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_SENSITIVE => true,
			];
		}
		return true;
	}

	/**
	 * @param string $type
	 * @param WebRequest $request
	 * @param string[] &$qc
	 * @return bool
	 */
	public function onSpecialLogAddLogSearchRelations( $type, $request, &$qc ) {
		if ( $type === 'gblrename' ) {
			$services = MediaWikiServices::getInstance();
			$userNameUtils = $services->getUserNameUtils();
			$oldname = trim( $request->getText( 'oldname' ) );
			$canonicalOldname = $userNameUtils->getCanonical( $oldname );
			if ( $oldname !== '' ) {
				$qc = [ 'ls_field' => 'oldname', 'ls_value' => $canonicalOldname ];

				$hiddenBits = 0;
				$user = $request->getSession()->getUser();
				if ( !$user->isAllowed( 'deletedhistory' ) ) {
					$hiddenBits = LogPage::DELETED_ACTION;
				} elseif ( !$user->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
					$hiddenBits = LogPage::DELETED_ACTION | LogPage::DELETED_RESTRICTED;
				}
				if ( $hiddenBits ) {
					$bitfield = $services->getDBLoadBalancerFactory()
						->getMainLB()
						->getConnection( DB_REPLICA )
						->bitAnd( 'log_deleted', $hiddenBits );
					$qc[] = "$bitfield != $hiddenBits";
				}
			}
		}

		return true;
	}

	/**
	 * @param string $type
	 * @param LogEventsList $list
	 * @param string &$input HTML
	 * @param array &$formDescriptor Form descriptor
	 */
	public function onLogEventsListGetExtraInputs(
		$type, $list, &$input, &$formDescriptor
	) {
		if ( $type === 'gblrename' ) {
			$value = $list->getRequest()->getVal( 'oldname' );
			if ( $value !== null ) {
				$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
				$name = $userNameUtils->getCanonical( $value );
				$value = $name !== false ? $name : '';
			}
			$formDescriptor = [
				'type' => 'text',
				'label-message' => 'centralauth-log-gblrename-oldname',
				'name' => 'oldname',
				'id' => 'mw-log-gblrename-oldname',
				'default' => $value,
			];
		}
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
	 * Apply global password policies when calculating the effective policy for
	 * a user.
	 * @param User $user
	 * @param array &$effectivePolicy
	 * @return bool
	 * @throws Exception
	 */
	public function onPasswordPoliciesForUser( $user, &$effectivePolicy ) {
		global $wgCentralAuthGlobalPasswordPolicies;
		$central = CentralAuthUser::getInstance( $user );

		if ( $central->exists() ) {
			$localPolicyGroups = array_intersect(
				array_keys( $wgCentralAuthGlobalPasswordPolicies ),
				$central->getLocalGroups()
			);

			$effectivePolicy = UserPasswordPolicy::getPoliciesForGroups(
				$wgCentralAuthGlobalPasswordPolicies,
				array_merge( $central->getGlobalGroups(), $localPolicyGroups ),
				$effectivePolicy
			);
		}
		return true;
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
	 * Create databases for WMF Jenkins unit tests
	 * @param DatabaseUpdater $updater
	 * @return true
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		global $wgWikimediaJenkinsCI;

		if ( !empty( $wgWikimediaJenkinsCI ) ) {
			$updater->addExtensionTable( 'globaluser', __DIR__ . '/../central-auth.sql' );
		}

		return true;
	}

	/**
	 * UnitTestsAfterDatabaseSetup hook handler
	 *
	 * Setup the centralauth tables in the current DB, so we don't have
	 * to worry about rights on another database. The first time it's called
	 * we have to set the DB prefix ourselves, and reset it back to the original
	 * so that CloneDatabase will work. On subsequent runs, the prefix is already
	 * set up for us.
	 *
	 * @param IMaintainableDatabase $db
	 * @param string $prefix
	 */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		global $wgCentralAuthDatabase;
		$wgCentralAuthDatabase = false;

		$originalPrefix = $db->tablePrefix();
		$db->tablePrefix( $prefix );
		if ( !$db->tableExists( 'globaluser', __METHOD__ ) ) {
			$db->sourceFile( __DIR__ . '/../central-auth.sql' );
		}
		$db->tablePrefix( $originalPrefix );
	}

	/** @var string[] */
	public static $centralauthTables = [
		'global_group_permissions',
		'global_group_restrictions',
		'global_user_groups',
		'globalnames',
		'globaluser',
		'localnames',
		'localuser',
		'wikiset',
		'renameuser_status',
		'renameuser_queue',
		'users_to_rename',
	];

	/**
	 * UnitTestsBeforeDatabaseTeardown hook handler
	 * Cleans up tables created by onUnitTestsAfterDatabaseSetup() above
	 */
	public function onUnitTestsBeforeDatabaseTeardown() {
		$db = wfGetDB( DB_PRIMARY );
		foreach ( self::$centralauthTables as $table ) {
			$db->dropTable( $table );
		}
	}

	/**
	 * @param array &$types
	 */
	public function onGetLogTypesOnUser( &$types ) {
		$types[] = 'gblrights';
	}
}
