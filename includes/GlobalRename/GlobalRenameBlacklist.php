<?php
/**
 * @section LICENSE
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

/**
 * Utility class to deal with global rename blacklist.
 *
 * @author Martin Urbanec <martin.urbanec@wikimedia.cz>
 * @copyright © 2019 Martin Urbanec and Wikimedia Foundation.
 */
class GlobalRenameBlacklist {
	/** @var string URL of the blacklist, set by constructor to $wgGlobalRenameBlacklist */
	private $url = null;
	/** @var array Content of blacklist */
	private $blacklist = [];
	private $logger = null;

	public function __construct() {
		global $wgGlobalRenameBlacklist;
		$this->url = $wgGlobalRenameBlacklist;
		$this->fetchBlacklist();
		$this->logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'CentralAuthRename' );
	}

	/**
	 * Internal method for fetching blacklist.
	 *
	 * Blacklist is fetched from the internet using Http::get().
	 * $url has to be already set.
	 */
	private function fetchBlacklist() {
		$this->blacklist = [];

		if ( $this->url === null ) {
			$this->logger->info( 'GlobalRenameBlacklist is not specified, not fetching anything' );
			return;
		}
		$text = Http::get( $this->url );
		if ( $text === false ) {
			$this->logger->warning( 'GlobalRenameBlacklist failed to fetch global rename blacklist.' );
			return;
		}

		$rows = explode( "\n", $text );
		foreach ( $rows as $row ) {
			$trimmedRow = trim( $row );
			if ( $trimmedRow == "" ) { // Empty line
				continue;
			}
			if ( $trimmedRow[0] == "#" ) { // Comment
				continue;
			}
			// TODO: Check user existance
			array_push( $this->blacklist, $trimmedRow );
		}
	}

	/**
	 * Checks if $user can request a global rename
	 *
	 * @param User $user User who is to be checked
	 */
	public function checkUser( User $user ) {
		$res = !in_array( $user->getName(), $this->blacklist );
		$this->logger->debug(
			'GlobalRenameBlacklist returns {result} for {username}',
			[
				'username' => $user->getName(),
				'res' => $res,
			]
		);
		return $res;
	}
}
