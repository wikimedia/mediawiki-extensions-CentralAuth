<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameQueue;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalVanishRequest;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class SpecialPageInitHookHandler implements SpecialPage_initListHook {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->config->get( CAMainConfigNames::CentralAuthEnableGlobalRenameRequest ) ) {
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
					'JobQueueGroupFactory',
					'CentralAuth.CentralAuthAntiSpoofManager',
					'CentralAuth.GlobalRenameFactory',
					'UserIdentityLookup',
				],
			];
			$list['GlobalVanishRequest'] = [
				'class' => SpecialGlobalVanishRequest::class,
				'services' => [
					'CentralAuth.GlobalRenameDenylist',
					'CentralAuth.GlobalRenameRequestStore',
					'CentralAuth.GlobalRenameFactory',
					'JobQueueGroupFactory',
					'HttpRequestFactory',
					'UserIdentityLookup',
				]
			];
		}
	}
}
