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

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use Psr\Log\LoggerInterface;
use StringUtils;
use Title;
use WikitextContent;

/**
 * Utility class to deal with global rename denylist.
 *
 * @author Martin Urbanec <martin.urbanec@wikimedia.cz>
 * @copyright © 2020 Martin Urbanec
 */
class GlobalRenameDenylist {
	/** @var string|Title|null Source of the denylist, url to fetch it from, or null */
	private $file = null;

	/** @var bool whether the denylist should be treated as a bunch of regexs */
	private $denylistRegex;

	/** @var string[]|null Content of the denylist */
	private $denylist = null;

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
	 * @param string|Title|null $denylistSource Page with denylist, url to fetch it from,
	 *   or null for no list ($wgGlobalRenameDenylist)
	 * @param bool $denylistRegex ($wgGlobalRenameDenylistRegex)
	 */
	public function __construct(
		LoggerInterface $logger,
		HttpRequestFactory $httpRequestFactory,
		WikiPageFactory $wikiPageFactory,
		$denylistSource,
		bool $denylistRegex
	) {
		$this->logger = $logger;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->file = $denylistSource;
		$this->denylistRegex = $denylistRegex;
	}

	/**
	 * Is global rename denylist enabled?
	 *
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->file !== null;
	}

	/**
	 * Internal method for fetching denylist.
	 *
	 * Denylist is fetched and parsed into denylist. Denylist source is
	 * either an URL on the internet, or a wiki page.
	 * $url has to be already set.
	 *
	 * @throws MWException
	 */
	private function fetchList() {
		if ( $this->denylist !== null && count( $this->denylist ) !== 0 ) {
			throw new MWException(
				'GlobalRenameDenylist::fetchList called on already fully initialized class'
			);
		}

		if ( $this->file instanceof Title ) {
			$this->logger->debug( 'GlobalRenameDenylist is fetching denylist from a wikipage' );
			$wikipage = $this->wikiPageFactory->newFromTitle( $this->file );
			$content = $wikipage->getContent();
			if ( $content === null ) {
				throw new MWException(
					'GlobalRenameDenylist::fetchList was called with non-existent wikipage'
				);
			}
			if ( !$content instanceof WikitextContent ) {
				throw new MWException(
					'Page used with GlobalRenameDenylist has invalid content model'
				);
			}
			$text = $content->getText();
		} else {
			$this->logger->debug( 'GlobalRenameDenylist is fetching denylist from the internet' );
			if ( $this->file === null ) {
				$this->logger->info( 'GlobalRenameDenylist is not specified, not fetching anything' );
				return;
			}
			$text = $this->httpRequestFactory->get( $this->file, [], __METHOD__ );
			if ( $text === null ) {
				$this->logger->warning( 'GlobalRenameDenylist failed to fetch global rename denylist.' );
				return;
			}
		}

		$rows = explode( "\n", $text );
		$this->denylist = [];
		foreach ( $rows as $row ) {
			$trimmedRow = trim( $row );
			if ( $trimmedRow === "" ) { // Empty line
				continue;
			}
			if ( $trimmedRow[0] === "#" ) { // Comment
				continue;
			}
			// TODO: Check user existance, if applicable
			$this->denylist[] = $trimmedRow;
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
			$this->logger->debug( 'GlobalRenameDenylist::checkUser() returns true, denylist is disabled' );
			return true;
		}

		if ( $this->denylist === null ) {
			$this->logger->debug( 'GlobalRenameDenylist::checkUser() fetches denylist, null found' );
			$this->fetchList();
		}

		if ( !$this->denylistRegex ) {
			$res = !in_array( $userName, $this->denylist, true );
		} else {
			$res = true;
			foreach ( $this->denylist as $row ) {
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
			'GlobalRenameDenylist returns {result} for {username}',
			[
				'username' => $userName,
				'result' => $res,
			]
		);
		return $res;
	}
}
