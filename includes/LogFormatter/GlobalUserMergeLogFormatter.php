<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Format the gblrename/merge log entries
 *
 * phan-taint-check gets very confused by $this->plaintext changing expected taint types everywhere,
 * so manual annotations are needed. They should be correct for the non-plaintext mode (HTML output).
 */
class GlobalUserMergeLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function extractParameters() {
		$lang = $this->context->getLanguage();
		$params = parent::extractParameters();

		$from = [];
		foreach ( explode( '|', $params[3] ) as $name ) {
			$from[] = $this->getCentralAuthLink( $name );
		}

		return [
			3 => Message::rawParam( $lang->commaList( $from ) ),
			4 => Message::rawParam( $this->getCentralAuthLink( $params[4] ) ),
		];
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
			return $title->getPrefixedText();
		} else {
			return $this->getLinkRenderer()->makeKnownLink( $title, $name );
		}
	}
}
