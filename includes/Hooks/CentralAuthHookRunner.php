<?php

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\HookContainer\HookContainer;

/**
 * Run hooks provided by CentralAuth.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthHookRunner implements
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
	public function onCentralAuthWikiList( ?array &$wikiList ) {
		$this->hookContainer->run(
			'CentralAuthWikiList',
			[ &$wikiList ]
		);
	}
}
