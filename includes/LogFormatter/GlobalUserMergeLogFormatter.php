<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use Message;
use SpecialPage;
use Title;

/**
 * Format the gblrename/merge log entries
 */
class GlobalUserMergeLogFormatter extends LogFormatter {
	protected function extractParameters() {
		$lang = $this->context->getLanguage();
		$params = parent::extractParameters();

		return [
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
	 * @return string
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
