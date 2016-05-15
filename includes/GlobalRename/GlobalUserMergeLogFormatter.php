<?php

/**
 * Format the gblrename/merge log entries
 */
class GlobalUserMergeLogFormatter extends LogFormatter {
	protected function getMessageParameters() {
		parent::getMessageParameters();
		$params = $this->extractParameters();
		$lang = $this->context->getLanguage();

		$this->parsedParameters[3] = Message::rawParam( $lang->commaList(
			array_map(
				array( $this, 'getCentralAuthLink' ),
				explode( '|', $params[3] )
			)
		) );
		$this->parsedParameters[4] = Message::rawParam( $this->getCentralAuthLink( $params[4] ) );

		ksort( $this->parsedParameters );
		return $this->parsedParameters;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	protected function getCentralAuthLink( $name ) {
		if ( $this->plaintext ) {
			return Title::newFromText( 'CentralAuth/' . $name, NS_SPECIAL );
		} else {
			return Linker::linkKnown(
				SpecialPage::getTitleFor( 'CentralAuth', $name ),
				htmlspecialchars( $name )
			);
		}
	}
}
