<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\RCFeed;

use MediaWiki\Title\Title;

/**
 * Interface for CentralAuth RC feed formatters
 */
interface CARCFeedFormatter {

	/**
	 * Generate an IRC line corresponding to user unification/creation
	 * @param Title $userpage
	 * @param string $wikiID
	 * @return string
	 */
	public function getLine( $userpage, $wikiID );
}
