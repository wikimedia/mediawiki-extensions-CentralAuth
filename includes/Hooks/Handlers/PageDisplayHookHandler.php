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

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use CentralAuthTokenSessionProvider;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\Special\SpecialCentralAutoLogin;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\ResourceLoader\Module;
use MediaWiki\WikiMap\WikiMap;
use Skin;

class PageDisplayHookHandler implements BeforePageDisplayHook {

	private Config $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * This does several things; the most important one is to trigger autologin if the user
	 * is not logged in.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @todo Add 1x1 images somewhere besides page content
	 *
	 * @see SpecialCentralAutoLogin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$logger = LoggerFactory::getInstance( 'CentralAuth' );

		if ( $out->getRequest()->getSession()->getProvider()
			instanceof CentralAuthTokenSessionProvider
		) {
			// Prevent user scripts and styles when centralauthtoken is in use
			$out->reduceAllowedModules(
				Module::TYPE_SCRIPTS, Module::ORIGIN_USER_SITEWIDE
			);
			$out->reduceAllowedModules(
				Module::TYPE_STYLES, Module::ORIGIN_USER_SITEWIDE
			);
		}

		if ( !$out->getUser()->isRegistered() ) {
			$wikiId = WikiMap::getCurrentWikiId();
			$loginWikiId = $this->config->get( 'CentralAuthLoginWiki' );
			if ( $loginWikiId && $wikiId !== $loginWikiId ) {
				// Let the frontend know if this is a mobile domain, T100413
				$out->addJsConfigVars(
					'wgCentralAuthMobileDomain',
					CentralAuthHooks::isMobileDomain()
				);

				$logger->debug( 'CentralAutoLogin triggered in BeforePageDisplay' );

				$out->addModules( 'ext.centralauth.centralautologin' );

				$wiki = WikiMap::getWiki( $wikiId );
				$loginWiki = WikiMap::getWiki( $loginWikiId );
				if ( $wiki->getCanonicalServer() !== $loginWiki->getCanonicalServer() ) {
					$out->addHeadItem( 'centralauth-dns-prefetch', Html::element( 'link', [
						'rel' => 'dns-prefetch',
						'href' => preg_replace( '/^https?:/', '', $loginWiki->getCanonicalServer() ),
					] ) );
				}

				// For non-JS clients.
				$params = [
					'type' => '1x1',
				];
				if ( CentralAuthHooks::isMobileDomain() ) {
					$params['mobile'] = 1;
				}
				$out->addHTML( '<noscript>' . CentralAuthHooks::getAuthIconHtml(
					$loginWikiId, 'Special:CentralAutoLogin/start', $params, null
				) . '</noscript>' );
			}
		} else {
			$centralUser = CentralAuthUser::getInstance( $out->getUser() );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$out->addModules( 'ext.centralauth.centralautologin.clearcookie' );
			}

			if ( $out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' ) ) {
				$logger->debug( 'Edge login triggered in BeforePageDisplay' );
				$out->getRequest()->setSessionData( 'CentralAuthDoEdgeLogin', null );
				$out->addHTML( CentralAuthHooks::getEdgeLoginHTML() );
			}
		}
	}
}
