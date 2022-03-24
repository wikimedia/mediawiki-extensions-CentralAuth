<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HookContainer\HookContainer;
use User;

/**
 * Run hooks provided by CentralAuth.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthHookRunner implements
	CentralAuthIsUIReloadRecommendedHook,
	CentralAuthPostLoginRedirectHook,
	CentralAuthLoginRedirectDataHook,
	CentralAuthSilentLoginRedirectHook,
	CentralAuthWikiListHook
{
	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload ) {
		$this->hookContainer->run(
			'CentralAuthIsUIReloadRecommended',
			[ $user, &$recommendReload ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo,
		string &$returnToQuery,
		bool $stickHTTPS,
		string $type,
		string &$injectedHtml
	) {
		$this->hookContainer->run(
			'CentralAuthPostLoginRedirect',
			[ &$returnTo, &$returnToQuery, $stickHTTPS, $type, &$injectedHtml ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthLoginRedirectData( CentralAuthUser $centralAuthUser, array &$data ) {
		$this->hookContainer->run(
			'CentralAuthLoginRedirectData',
			[ $centralAuthUser, &$data ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthSilentLoginRedirect( CentralAuthUser $centralAuthUser, string &$url, array $data ) {
		$this->hookContainer->run(
			'CentralAuthLoginRedirectData',
			[ $centralAuthUser, &$url, $data ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralAuthWikiList( ?array &$wikiList ) {
		$this->hookContainer->run(
			'CentralAuthWikiList',
			[ &$wikiList ]
		);
	}
}
