<?php

namespace MediaWiki\Extension\CentralAuth;

use Hooks;
use MediaWiki\Config\ServiceOptions;

/**
 * Handle knowledge of other wikis in this CentralAuth farm.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthWikiListService {
	/** @var ServiceOptions */
	private $options;

	/** @var string[]|null Cached array of known wikis. */
	private $wikiList = null;

	/** @internal Only public for ServiceWiring use */
	public const CONSTRUCTOR_OPTIONS = [
		'LocalDatabases',
	];

	/**
	 * @param ServiceOptions $options Options used by this service, containing keys in ::CONSTRUCTOR_OPTIONS
	 */
	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * @return string[] all database names of wikis in this CentralAuth farm
	 */
	public function getWikiList(): array {
		if ( $this->wikiList === null ) {
			$list = null;
			Hooks::run( 'CentralAuthWikiList', [ &$list ] );

			// @phan-suppress-next-line PhanSuspiciousValueComparison May set by hook
			if ( $list === null ) {
				$list = $this->options->get( 'LocalDatabases' );
			}

			$this->wikiList = $list;
		}

		return $this->wikiList;
	}
}
