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

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\WikiPageFactory;
use Psr\Log\LoggerInterface;

/**
 * Utility class to deal with global rename blacklist.
 *
 * @author Martin Urbanec <martin.urbanec@wikimedia.cz>
 * @copyright © 2020 Martin Urbanec
 */
class GlobalRenameBlacklist {
	/** @var string|Title|null Source of the blacklist, url to fetch it from, or null */
	private $file = null;

	/** @var bool whether the blacklist should be treated as a bunch of regexs */
	private $blacklistRegex;

	/** @var string[]|null Content of blacklist */
	private $blacklist = null;

	/** @var LoggerInterface */
	private $logger;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param LoggerInterface $logger
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param string|Title|null $blacklistSource Page with blacklist, url to fetch it from,
	 *   or null for no list ($wgGlobalRenameBlacklist)
	 * @param bool $blacklistRegex ($wgGlobalRenameBlacklistRegex)
	 */
	public function __construct(
		LoggerInterface $logger,
		HttpRequestFactory $httpRequestFactory,
		WikiPageFactory $wikiPageFactory,
		$blacklistSource,
		bool $blacklistRegex
	) {
		$this->logger = $logger;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->file = $blacklistSource;
		$this->blacklistRegex = $blacklistRegex;
	}

	/**
	 * Is global rename blacklist enabled?
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->file !== null;
	}

	/**
	 * Internal method for fetching blacklist.
	 *
	 * Blacklist is fetched and parsed into $blacklist. Blacklist source is
	 * either an URL on the internet, or a wiki page.
	 * $url has to be already set.
	 *
	 * @throws MWException
	 */
	private function fetchBlacklist() {
		if ( $this->blacklist !== null && count( $this->blacklist ) !== 0 ) {
			throw new MWException(
				'GlobalRenameBlacklist::fetchBlacklist called on already fully initialized class'
			);
		}

		if ( $this->file instanceof Title ) {
			$this->logger->debug( 'GlobalRenameBlacklist is fetching blacklist from a wikipage' );
			$wikipage = $this->wikiPageFactory->newFromTitle( $this->file );
			$content = $wikipage->getContent();
			if ( $content === null ) {
				throw new MWException(
					'GlobalRenameBlacklist::fetchBlacklist was called with non-existent wikipage'
				);
			}
			if ( !$content instanceof WikitextContent ) {
				throw new MWException(
					'Page used with GlobalRenameBlacklist has invalid content model'
				);
			}
			$text = $content->getText();
		} else {
			$this->logger->debug( 'GlobalRenameBlacklist is fetching blacklist from the internet' );
			if ( $this->file === null ) {
				$this->logger->info( 'GlobalRenameBlacklist is not specified, not fetching anything' );
				return;
			}
			$text = $this->httpRequestFactory->get( $this->file, [], __METHOD__ );
			if ( $text === null ) {
				$this->logger->warning( 'GlobalRenameBlacklist failed to fetch global rename blacklist.' );
				return;
			}
		}

		$rows = explode( "\n", $text );
		$this->blacklist = [];
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
	 * Checks if $userName can request a global rename
	 *
	 * @param string $userName
	 * @return bool
	 */
	public function checkUser( string $userName ) {
		if ( !$this->isEnabled() ) {
			$this->logger->debug( 'GlobalRenameBlacklist::checkUser() returns true, blacklist is disabled' );
			return true;
		}

		if ( $this->blacklist === null ) {
			$this->logger->debug( 'GlobalRenameBlacklist::checkUser() fetches blacklist, null found' );
			$this->fetchBlacklist();
		}

		if ( !$this->blacklistRegex ) {
			$res = !in_array( $userName, $this->blacklist, true );
		} else {
			$res = true;
			foreach ( $this->blacklist as $row ) {
				$row = preg_replace( '!(\\\\\\\\)*(\\\\)?/!', '$1\/', $row );
				$regex = "/$row/u";
				if ( !StringUtils::isValidPCRERegex( $regex ) ) {
					continue; // Skip invalid regex
				}
				$regexRes = preg_match( $regex, $userName );
				if ( $regexRes === 1 ) {
					$res = false;
					break;
				}
			}
		}
		$this->logger->debug(
			'GlobalRenameBlacklist returns {result} for {username}',
			[
				'username' => $userName,
				'result' => $res,
			]
		);
		return $res;
	}
}
