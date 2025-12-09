<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\RCFeed;

use MediaWiki\Json\FormatJson;
use MediaWiki\Title\Title;

class JSONCARCFeedFormatter implements CARCFeedFormatter {

	/**
	 * Generates a user unification/creation entry that can be easily interpreted by a machine.
	 * @param Title $userpage
	 * @param string $wikiID
	 * @return string
	 */
	public function getLine( $userpage, $wikiID ) {
		$packet = [
			'title' => $userpage->getPrefixedText(),
			'wikiid' => $wikiID,
			'user' => $userpage->getText(),
			'url' => $userpage->getCanonicalURL()
		];
		return FormatJson::encode( $packet );
	}
}
