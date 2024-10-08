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
