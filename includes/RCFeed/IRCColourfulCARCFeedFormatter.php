<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\RCFeed;

use MediaWiki\RCFeed\IRCColourfulRCFeedFormatter;
use MediaWiki\Title\Title;

/**
 * Generates a colourful notification intended for humans on IRC.
 */
class IRCColourfulCARCFeedFormatter implements CARCFeedFormatter {

	/**
	 * Generate an IRC line corresponding to user unification/creation
	 * @param Title $userpage
	 * @param string $wikiID
	 * @return string
	 */
	public function getLine( $userpage, $wikiID ) {
		$title = IRCColourfulRCFeedFormatter::cleanupForIRC( $userpage->getPrefixedText() );
		$wikiID = IRCColourfulRCFeedFormatter::cleanupForIRC( $wikiID );
		$url = $userpage->getCanonicalURL();
		$user = IRCColourfulRCFeedFormatter::cleanupForIRC( $userpage->getText() );
		# see http://www.irssi.org/documentation/formats for some colour codes. prefix is \003,
		# no colour (\003) switches back to the term default
		return "\00314[[\00307$title\00314]]\0034@$wikiID\00310 " .
		"\00302$url\003 \0035*\003 \00303$user\003 \0035*\003\n";
	}
}
