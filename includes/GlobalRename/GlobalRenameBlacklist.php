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
	/** @var string|Title|null Source of the blacklist, set to $wgGlobalRenameBlacklist */
	private $file = null;
	/** @var string[]|null Content of blacklist */
	private $blacklist = null;
	/** @var \Psr\Log\LoggerInterface|null */
	private $logger = null;

	/**
	 * Constructor
	 *
	 * @param string|null $file Source of blacklist to use, or null to use $wgGlobalRenameBlacklist
	 */
	public function __construct( $file = null ) {
		global $wgGlobalRenameBlacklist;
		$this->logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'CentralAuthRename' );
		if ( $file === null ) {
			$this->file = $wgGlobalRenameBlacklist;
		} else {
			$this->file = $file;
		}
	}

	/**
	 * Internal method for fetching blacklist.
	 *
	 * Blacklist is fetched and parsed into $blacklist. Blacklist source is
	 * either an URL on the internet, or a wiki page.
	 * $url has to be already set.
	 *
	 * @throws MWException
	 * @return null
	 */
	private function fetchBlacklist() {
		if ( count( $this->blacklist ) != 0 ) {
			throw new MWException(
				'GlobalRenameBlacklist::fetchBlacklist called on already fully initialized class'
			);
		}

		if ( $this->file instanceof Title ) {
			$this->logger->debug( 'GlobalRenameBlacklist is fetching blacklist from a wikipage' );
			$wikipage = WikiPage::factory( $this->file );
			$content = $wikipage->getContent();
			if ( $content === null ) {
				throw new MWException( 'GlobalRenameBlacklist::fetchBlacklist was called with non-existent wikipage' );
			}
			$text = $content->getText();
		} else {
			$this->logger->debug( 'GlobalRenameBlacklist is fetching blacklist from the internet' );
			if ( $this->file === null ) {
				$this->logger->info( 'GlobalRenameBlacklist is not specified, not fetching anything' );
				return;
			}
			$text = Http::get( $this->file );
			if ( $text === false ) {
				$this->logger->warning( 'GlobalRenameBlacklist failed to fetch global rename blacklist.' );
				return;
			}
		}

		$rows = explode( "\n", $text );
		foreach ( $rows as $row ) {
			$trimmedRow = trim( $row );
			if ( $trimmedRow === "" ) { // Empty line
				continue;
			}
			if ( $trimmedRow[0] === "#" ) { // Comment
				continue;
			}
			// TODO: Check user existance, if applicable
			$this->blacklist[] = $trimmedRow;
		}
	}

	/**
	 * Returns fetched blacklist
	 *
	 * @return string[]
	 */
	public function getBlacklist() {
		if ( $this->blacklist === null ) {
			$this->logger->debug( 'GlobalRenameBlacklist::getBlacklist() fetches blacklist, null found' );
			$this->fetchBlacklist();
		}
		return $this->blacklist;
	}

	/**
	 * Checks if $user can request a global rename
	 *
	 * @param User $user User who is to be checked
	 * @return bool
	 */
	public function checkUser( User $user ) {
		global $wgGlobalRenameBlacklistRegex;

		if ( $this->blacklist === null ) {
			$this->logger->debug( 'GlobalRenameBlacklist::checkUser() fetches blacklist, null found' );
			$this->fetchBlacklist();
		}

		if ( !$wgGlobalRenameBlacklistRegex ) {
			$res = !in_array( $user->getName(), $this->blacklist, true );
		} else {
			$res = true;
			foreach ( $this->blacklist as $row ) {
				$regexRes = preg_match( "/$row/u", $user->getName() );
				if ( $regexRes === 1 ) {
					$res = false;
					break;
				}
			}
		}
		$this->logger->debug(
			'GlobalRenameBlacklist returns {result} for {username}',
			[
				'username' => $user->getName(),
				'result' => $res,
			]
		);
		return $res;
	}
}
