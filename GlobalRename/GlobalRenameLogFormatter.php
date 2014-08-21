<?php

/**
 * Make the gblrename/rename log entry look pretty
 */
class GlobalRenameLogFormatter extends LogFormatter {
	protected function getMessageParameters() {
		parent::getMessageParameters();
		$params = $this->extractParameters();

		$this->parsedParameters[3] = $this->getCentralAuthLink( $params[3] );
		$this->parsedParameters[4] = $this->getCentralAuthLink( $params[4] );

		ksort( $this->parsedParameters );
		return $this->parsedParameters;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	protected function getCentralAuthLink( $name ) {
		if ( $this->plaintext ) {
			return Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $name );
		}
		return Message::rawParam( Linker::link(
				Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $name ),
				htmlspecialchars( $name )
		) );
	}
}
