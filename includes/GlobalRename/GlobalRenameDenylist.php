<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use BadMethodCallException;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use StringUtils;

/**
 * Utility class to deal with global rename denylist.
 *
 * @author Martin Urbanec <martin.urbanec@wikimedia.cz>
 * @copyright © 2020 Martin Urbanec
 */
class GlobalRenameDenylist {

	/** @var string|Title|null Source of the denylist, url to fetch it from, or null */
	private $file = null;

	/** @var string[]|null Content of the denylist */
	private $denylist = null;

	private LoggerInterface $logger;
	private HttpRequestFactory $httpRequestFactory;
	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param LoggerInterface $logger
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param string|Title|null $denylistSource Page with denylist, url to fetch it from,
	 *   or null for no list ($wgGlobalRenameDenylist)
	 */
	public function __construct(
		LoggerInterface $logger,
		HttpRequestFactory $httpRequestFactory,
		WikiPageFactory $wikiPageFactory,
		$denylistSource
	) {
		$this->logger = $logger;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->file = $denylistSource;
	}

	/**
	 * Is global rename denylist enabled?
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
	 */
	private function fetchList() {
		if ( $this->denylist !== null && count( $this->denylist ) !== 0 ) {
			throw new BadMethodCallException(
				'GlobalRenameDenylist::fetchList called on already fully initialized class'
			);
		}

		if ( $this->file instanceof Title ) {
			$this->logger->debug( 'GlobalRenameDenylist is fetching denylist from a wikipage' );
			$wikipage = $this->wikiPageFactory->newFromTitle( $this->file );
			$content = $wikipage->getContent();
			if ( $content === null ) {
				throw new BadMethodCallException(
					'GlobalRenameDenylist::fetchList was called with non-existent wikipage'
				);
			}
			if ( !$content instanceof WikitextContent ) {
				throw new BadMethodCallException(
					'Page used with GlobalRenameDenylist has invalid content model'
				);
			}
			$text = $content->getText();
		} else {
			$this->logger->debug( 'GlobalRenameDenylist is fetching denylist from the internet' );
			if ( $this->file === null ) {
				$this->logger->info( 'GlobalRenameDenylist is not specified, not fetching anything' );
				$this->denylist = [];
				return;
			}
			$text = $this->httpRequestFactory->get( $this->file, [], __METHOD__ );
			if ( $text === null ) {
				$this->logger->error( 'GlobalRenameDenylist failed to fetch global rename denylist.' );
				$this->denylist = [];
				return;
			}
		}

		$rows = explode( "\n", $text );
		$this->denylist = [];
		foreach ( $rows as $row ) {
			$trimmedRow = trim( $row );
			// Empty line
			if ( $trimmedRow === "" ) {
				continue;
			}
			// Comment
			if ( $trimmedRow[0] === "#" ) {
				continue;
			}
			// @TODO: Check user existence, if applicable
			$this->denylist[] = $trimmedRow;
		}
	}

	/**
	 * Checks if $userName can request a global rename
	 *
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

		$res = true;
		foreach ( $this->denylist as $row ) {
			$row = preg_replace( '!(\\\\\\\\)*(\\\\)?/!', '$1\/', $row );
			$regex = "/$row/u";
			if ( !StringUtils::isValidPCRERegex( $regex ) ) {
				// Skip invalid regex
				continue;
			}
			$regexRes = preg_match( $regex, $userName );
			if ( $regexRes === 1 ) {
				$res = false;
				break;
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
