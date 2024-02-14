<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Message;

/**
 * Format the gblrename/merge log entries
 *
 * phan-taint-check gets very confused by $this->plaintext changing expected taint types everywhere,
 * so manual annotations are needed. They should be correct for the non-plaintext mode (HTML output).
 */
class GlobalUserMergeLogFormatter extends LogFormatter {
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
		if ( $this->plaintext ) {
			return Title::newFromText( 'CentralAuth/' . $name, NS_SPECIAL )->getPrefixedText();
		} else {
			return $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'CentralAuth', $name ),
				$name
			);
		}
	}
}
