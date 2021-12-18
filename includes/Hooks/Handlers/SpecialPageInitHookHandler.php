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

use Config;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameQueue;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\Special\SpecialUsersWhoWillBeRenamed;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class SpecialPageInitHookHandler implements
	SpecialPage_initListHook
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
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->config->get( 'CentralAuthEnableUsersWhoWillBeRenamed' ) ) {
			$list['UsersWhoWillBeRenamed'] = [
				'class' => SpecialUsersWhoWillBeRenamed::class,
			];
		}
		if ( $this->config->get( 'CentralAuthEnableGlobalRenameRequest' ) ) {
			$list['GlobalRenameRequest'] = [
				'class' => SpecialGlobalRenameRequest::class,
				'services' => [
					'CentralAuth.GlobalRenameDenylist',
					'UserNameUtils',
					'CentralAuth.GlobalRenameRequestStore',
				]
			];
			$list['GlobalRenameQueue'] = [
				'class' => SpecialGlobalRenameQueue::class,
				'services' => [
					'UserNameUtils',
					'DBLoadBalancerFactory',
					'CentralAuth.CentralAuthDatabaseManager',
					'CentralAuth.CentralAuthUIService',
					'CentralAuth.GlobalRenameRequestStore',
				],
			];
		}
	}
}
