<?php

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthWikiListHook;
use MediaWiki\HookContainer\HookContainer;

/**
 * Run hooks provided by the CentralAuth extension.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthHookRunner implements CentralAuthWikiListHook {
	/** @var HookContainer */
	private $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	public function onCentralAuthWikiList( ?array &$wikiList ) : void {
		$this->container->run(
			'CentralAuthWikiList',
			[ &$wikiList ]
		);
	}
}
