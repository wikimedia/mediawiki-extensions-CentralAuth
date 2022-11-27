<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use LogFormatter;
use Message;
use Title;
use WikiMap;

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
			$this->parsedParameters[3] = Message::rawParam( $this->getLocalWikiLink( $params[3], $params[5] ) );
		} else { // rename
			$this->parsedParameters[3] = Message::rawParam( $this->getCentralAuthLink( $params[3] ) );
		}
		$this->parsedParameters[4] = Message::rawParam( $this->getCentralAuthLink( $params[4] ) );

		ksort( $this->parsedParameters );
		return $this->parsedParameters;
	}

	/**
	 * @param string $name
	 * @return string wikitext or html
	 * @return-taint onlysafefor_html
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
	 * @return-taint onlysafefor_html
	 */
	protected function getLocalWikiLink( $name, $wiki ) {
		$text = "User:$name@$wiki";
		if ( $this->plaintext ) {
			return "[[$text]]";
		}

		return WikiMap::foreignUserLink( $wiki, $name, $text );
	}
}
