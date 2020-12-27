<?php

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserMergedHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthIsUIReloadRecommendedHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthLoginRedirectDataHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthPostLoginRedirectHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthSilentLoginRedirectHook;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthWikiListHook;
use MediaWiki\HookContainer\HookContainer;

/**
 * Run hooks provided by the CentralAuth extension.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthHookRunner implements
	CentralAuthGlobalUserMergedHook,
	CentralAuthIsUIReloadRecommendedHook,
	CentralAuthLoginRedirectDataHook,
	CentralAuthPostLoginRedirectHook,
	CentralAuthSilentLoginRedirectHook,
	CentralAuthWikiListHook
{
	/** @var HookContainer */
	private $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	public function onCentralAuthGlobalUserMerged( string $oldName, string $newName, int $oldId, int $newId ) : void {
		$this->container->run(
			'CentralAuthGlobalUserMerged',
			[ $oldName, $newName, $oldId, $newId ],
			[ 'abortable' => false ]
		);
	}

	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload ) : void {
		$this->container->run(
			'CentralAuthIsUIReloadRecommended',
			[ $user, &$recommendReload ],
			[ 'abortable' => false ]
		);
	}

	public function onCentralAuthLoginRedirectData( CentralAuthUser $user, array &$data ) : void {
		$this->container->run(
			'CentralAuthLoginRedirectData',
			[ $user, &$data ],
			[ 'abortable' => false ]
		);
	}

	public function onCentralAuthPostLoginRedirect(
		string &$returnTo,
		string &$returnToQuery,
		bool $stickHttps,
		string $type,
		string &$injectHtml
	) : void {
		$this->container->run(
			'CentralAuthPostLoginRedirect',
			[ &$returnTo, &$returnToQuery, $stickHttps, $type, &$injectHtml ],
			[ 'abortable' => false ]
		);
	}

	public function onCentralAuthSilentLoginRedirect( CentralAuthUser $centralUser, string &$url, array $info ) : void {
		$this->container->run(
			'CentralAuthSilentLoginRedirect',
			[ $centralUser, &$url, $info ],
			[ 'abortable' => false ]
		);
	}

	public function onCentralAuthWikiList( ?array &$wikiList ) {
		$this->container->run(
			'CentralAuthWikiList',
			[ &$wikiList ],
		);
	}
}
