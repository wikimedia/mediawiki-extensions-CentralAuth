<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use LogFormatter;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Message;

/**
 * Handles the following log types:
 *  - gblrename/rename
 *  - gblrename/promote
 */
class GlobalRenameLogFormatter extends LogFormatter {
	protected function getMessageParameters() {
		parent::getMessageParameters();
		$params = $this->extractParameters();

		if ( $this->entry->getSubtype() === 'promote' ) {
			// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
			$this->parsedParameters[3] = Message::rawParam( $this->getLocalWikiLink( $params[3], $params[5] ) );
		} else { // rename
			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$this->parsedParameters[3] = Message::rawParam( $this->getCentralAuthLink( $params[3] ) );
		}
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$this->parsedParameters[4] = Message::rawParam( $this->getCentralAuthLink( $params[4] ) );

		ksort( $this->parsedParameters );
		return $this->parsedParameters;
	}

	/**
	 * @param string $name
	 * @return string wikitext or html
	 */
	protected function getCentralAuthLink( $name ) {
		$title = Title::makeTitle( NS_SPECIAL, 'CentralAuth/' . $name );
		if ( $this->plaintext ) {
			return "[[{$title->getPrefixedText()}]]";
		}

		return $this->getLinkRenderer()->makeLink( $title, $name );
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 * @return string wikitext or html
	 */
	protected function getLocalWikiLink( $name, $wiki ) {
		$text = "User:$name@$wiki";
		if ( $this->plaintext ) {
			return "[[$text]]";
		}

		return WikiMap::foreignUserLink( $wiki, $name, $text );
	}
}
