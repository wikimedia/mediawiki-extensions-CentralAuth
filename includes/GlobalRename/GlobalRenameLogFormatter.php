<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use LogFormatter;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;
use Message;

/**
 * Handles the following log types:
 *  - gblrename/rename
 *  - gblrename/promote
 *
 * phan-taint-check gets very confused by $this->plaintext changing expected taint types everywhere,
 * so manual annotations are needed. They should be correct for the non-plaintext mode (HTML output).
 */
class GlobalRenameLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageParameters() {
		parent::getMessageParameters();
		$params = $this->extractParameters();

		if ( $this->entry->getSubtype() === 'promote' ) {
			$this->parsedParameters[3] = Message::rawParam( $this->getLocalWikiLink( $params[3], $params[5] ) );
		} else {
			// rename
			$this->parsedParameters[3] = Message::rawParam( $this->getCentralAuthLink( $params[3] ) );
		}
		$this->parsedParameters[4] = Message::rawParam( $this->getCentralAuthLink( $params[4] ) );

		ksort( $this->parsedParameters );
		return $this->parsedParameters;
	}

	/**
	 * @param string $name
	 * @param-taint $name none
	 * @return string wikitext or html
	 * @return-taint onlysafefor_html
	 */
	protected function getCentralAuthLink( $name ) {
		$title = SpecialPage::getTitleFor( 'CentralAuth', $name );
		if ( $this->plaintext ) {
			return '[[' . $title->getPrefixedText() . ']]';
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
