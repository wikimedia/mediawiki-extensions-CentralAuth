<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use MediaWiki\Title\Title;
use Message;
use SpecialPage;

/**
 * Format the gblrename/merge log entries
 */
class GlobalUserMergeLogFormatter extends LogFormatter {
	protected function extractParameters() {
		$lang = $this->context->getLanguage();
		$params = parent::extractParameters();

		return [
			// @phan-suppress-next-line SecurityCheck-XSS
			3 => Message::rawParam( $lang->commaList(
				array_map(
					[ $this, 'getCentralAuthLink' ],
					explode( '|', $params[3] )
				)
			) ),
			4 => Message::rawParam( $this->getCentralAuthLink( $params[4] ) ),
		];
	}

	/**
	 * @param string $name
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
