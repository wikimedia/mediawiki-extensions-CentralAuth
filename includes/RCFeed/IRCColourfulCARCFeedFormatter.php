<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\RCFeed;

use IRCColourfulRCFeedFormatter;
use Title;

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
