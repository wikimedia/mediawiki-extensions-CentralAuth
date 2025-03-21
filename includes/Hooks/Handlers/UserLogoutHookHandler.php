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

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralDomainUtils;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\User\Hook\UserLogoutHook;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

class UserLogoutHookHandler implements
	UserLogoutCompleteHook,
	UserLogoutHook
{

	private Config $config;
	private SharedDomainUtils $sharedDomainUtils;

	public function __construct(
		Config $config,
		SharedDomainUtils $sharedDomainUtils
	) {
		$this->config = $config;
		$this->sharedDomainUtils = $sharedDomainUtils;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function onUserLogout( $user ) {
		if ( !$this->config->get( CAMainConfigNames::CentralAuthCookies ) ) {
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
		if ( !$this->config->get( CAMainConfigNames::CentralAuthCookies ) ) {
			return true;
		}

		$wikis = CentralAuthHooks::getAutoLoginWikis();
		$loginWiki = $this->config->get( CAMainConfigNames::CentralAuthLoginWiki );
		if ( $loginWiki && $loginWiki !== WikiMap::getCurrentWikiId() ) {
			$wikis[$loginWiki] = $loginWiki;
		}

		if ( !$this->sharedDomainUtils->isSharedDomain() &&
			$this->sharedDomainUtils->isSul3Enabled( $user->getRequest() )
		) {
			$wikis[CentralDomainUtils::CENTRAL_DOMAIN_ID] = CentralDomainUtils::CENTRAL_DOMAIN_ID;
		}

		$request = RequestContext::getMain()->getRequest();
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
			foreach ( $wikis as $wikiID ) {
				$params = [
					'type' => 'icon',
					'usesul3' => $this->sharedDomainUtils->isSul3Enabled( $request ) ? 1 : 0,
				];
				$inject_html .= CentralAuthHooks::getAuthIconHtml(
					$wikiID, 'Special:CentralAutoLogin/deleteCookies', $params, $csp
				);
			}
			$inject_html .= "</p></div>\n";
		}

		return true;
	}
}
