<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\HookContainer\HookContainer;

/**
 * Handle knowledge of other wikis in this CentralAuth farm.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthWikiListService {
	/** @var ServiceOptions */
	private $options;

	/** @var CentralAuthHookRunner */
	private $hookRunner;

	/** @var string[]|null Cached array of known wikis. */
	private $wikiList = null;

	/** @internal Only public for ServiceWiring use */
	public const CONSTRUCTOR_OPTIONS = [
		'LocalDatabases',
	];

	/**
	 * @param ServiceOptions $options Options used by this service, containing keys in ::CONSTRUCTOR_OPTIONS
	 * @param HookContainer $hookContainer
	 */
	public function __construct( ServiceOptions $options, HookContainer $hookContainer ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->hookRunner = new CentralAuthHookRunner( $hookContainer );
	}

	/**
	 * @return string[] all database names of wikis in this CentralAuth farm
	 */
	public function getWikiList(): array {
		if ( $this->wikiList === null ) {
			$list = null;

			$this->hookRunner->onCentralAuthWikiList( $list );

			if ( $list === null ) {
				$list = $this->options->get( 'LocalDatabases' );
			}

			$this->wikiList = $list;
		}

		return $this->wikiList;
	}
}
