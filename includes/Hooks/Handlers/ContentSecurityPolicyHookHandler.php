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
use Config;
use MediaWiki\Hook\ContentSecurityPolicyDefaultSourceHook;
use MediaWiki\Hook\ContentSecurityPolicyScriptSourceHook;
use RequestContext;
use WikiMap;

class ContentSecurityPolicyHookHandler implements
	ContentSecurityPolicyDefaultSourceHook,
	ContentSecurityPolicyScriptSourceHook
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
	 * Add other domains as CSP source if auto-login is going to be attempted
	 *
	 * This is adding domains for images (and other req types), not scripts.
	 *
	 * @note We can't do $out->getCSP()->addDefaultSrc in onBeforePageDisplay,
	 * because that hook runs after the header is already outputted.
	 * @param string[] &$defaultSrc Array of allowed CSP sources.
	 * @param array $policyConfig
	 * @param int $mode
	 */
	public function onContentSecurityPolicyDefaultSource(
		&$defaultSrc,
		$policyConfig,
		$mode
	) {
		$out = RequestContext::getMain()->getOutput();
		// So possibilities:
		// * We are doing edge login because initial login was via API and this is next request.
		// * We are doing edge login because JS loaded Special:CentralAutoLogin/start or /checkCookies
		//   and user is logged in on a different wiki, which eventually loads edge html.
		if (
			!$out->getUser()->isRegistered() ||
				$out->getRequest()->getSessionData( 'CentralAuthDoEdgeLogin' )
		) {
			foreach ( $this->config->get( 'CentralAuthAutoLoginWikis' ) as $wiki ) {
				$wiki = WikiMap::getWiki( $wiki );
				if ( CentralAuthHooks::isMobileDomain() ) {
					$url = \MobileContext::singleton()->getMobileUrl(
						$wiki->getFullUrl( 'Special:CentralAutoLogin/start' )
					);
					$defaultSrc[] = wfParseUrl( $url )['host'];
				} else {
					$defaultSrc[] = wfParseUrl( $wiki->getCanonicalServer() )['host'];
				}
			}
		}

		if ( !$out->getUser()->isRegistered() && $this->config->get( 'CentralAuthLoginWiki' ) ) {
			// For the non-js case, there is local image loaded, but it redirects to
			// central wiki, so include it.
			$loginWiki = WikiMap::getWiki( $this->config->get( 'CentralAuthLoginWiki' ) );
			if ( CentralAuthHooks::isMobileDomain() ) {
				$url = \MobileContext::singleton()->getMobileUrl(
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
	 * @param int $mode
	 */
	public function onContentSecurityPolicyScriptSource(
		&$scriptSrc,
		$policyConfig,
		$mode
	) {
		$out = RequestContext::getMain()->getOutput();
		if ( $this->config->get( 'CentralAuthLoginWiki' ) && !$out->getUser()->isRegistered() ) {
			$loginWiki = WikiMap::getWiki( $this->config->get( 'CentralAuthLoginWiki' ) );
			$scriptSrc[] = wfParseUrl( $loginWiki->getCanonicalServer() )['host'];
		}
	}
}
