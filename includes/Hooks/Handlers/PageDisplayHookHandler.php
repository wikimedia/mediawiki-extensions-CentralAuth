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

use CentralAuthHooks;
use CentralAuthTokenSessionProvider;
use CentralAuthUser;
use Config;
use EventLogging;
use Html;
use MediaWiki\Hook\BeforePageDisplayHook;
use OutputPage;
use ResourceLoaderModule;
use Skin;
use WikiMap;
use Xml;

class PageDisplayHookHandler implements
	BeforePageDisplayHook
{
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @todo Add 1x1 images somewhere besides page content
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
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
			if ( $this->config->get( 'CentralAuthLoginWiki' ) &&
				wfWikiID() !== $this->config->get( 'CentralAuthLoginWiki' )
			) {
				// Let the frontend know if this is a mobile domain, T100413
				$out->addJsConfigVars(
					'wgCentralAuthMobileDomain',
					CentralAuthHooks::isMobileDomain()
				);
				$out->addModules( 'ext.centralauth.centralautologin' );

				// For non-JS clients. Use WikiMap to avoid localization of the
				// 'Special' namespace, see bug 54195.
				$wiki = WikiMap::getWiki( wfWikiID() );

				$loginWiki = WikiMap::getWiki( $this->config->get( 'CentralAuthLoginWiki' ) );
				if ( $wiki->getCanonicalServer() !== $loginWiki->getCanonicalServer() ) {
					$out->addHeadItem( 'centralauth-dns-prefetch', Html::element( 'link', [
						'rel' => 'dns-prefetch',
						'href' => preg_replace( '/^https?:/', '', $loginWiki->getCanonicalServer() ),
					] ) );
				}

				$params = [
					'type' => '1x1',
				];
				if ( CentralAuthHooks::isMobileDomain() ) {
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
				$out->addHTML( CentralAuthHooks::getEdgeLoginHTML() );

				if ( $this->config->get( 'CentralAuthUseEventLogging' ) ) {
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
}
