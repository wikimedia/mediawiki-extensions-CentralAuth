<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use Message;
use UnexpectedValueException;

/**
 * Handles the following log types:
 * - gblrights/deleteset
 * - gblrights/newset
 * - gblrights/setchange
 * - gblrights/setnewtype
 * - gblrights/setrename
 */
class WikiSetLogFormatter extends LogFormatter {

	/**
	 * @param string $name
	 * @return string wikitext or html
	 * @return-taint onlysafefor_html
	 */
	private function formatWikiSetLink( $name ) {
		if ( !$this->plaintext ) {
			return $this->makePageLink( $this->entry->getTarget(), [], htmlspecialchars( $name ) );
		}
		return $name;
	}

	private function formatType( $type ): string {
		// Give grep a chance to find the usages:
		// centralauth-rightslog-set-optin, centralauth-rightslog-set-optout
		return $this->msg( "centralauth-rightslog-set-$type" )->text();
	}

	private function formatWikis( array $wikis ): string {
		if ( $wikis !== [] ) {
			return $this->formatParameterValue( 'list', $wikis );
		}
		return $this->msg( 'rightsnone' )->text();
	}

	protected function extractParameters() {
		$params = parent::extractParameters();
		$action = $this->entry->getSubtype();

		// Give grep a chance to find the usages:
		// logentry-gblrights-deleteset, logentry-gblrights-newset
		// logentry-gblrights-setchange, logentry-gblrights-setnewtype
		// logentry-gblrights-setrename
		switch ( $action ) {
			case 'deleteset':
				return [
					// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ), // 4::name
				];
			case 'newset':
				$wikis = $this->entry->isLegacy()
					? explode( ', ', $params[5] ) // shouldn't be empty
					: $this->entry->getParameters()['wikis'];
				return [
					// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ), // 4::name
					4 => $this->formatType( $params[4] ), // 5::type
					// @phan-suppress-next-line SecurityCheck-XSS
					5 => $this->formatWikis( $wikis ),
					6 => Message::numParam( count( $wikis ) ),
				];
			case 'setchange':
				if ( $this->entry->isLegacy() ) {
					$added = $params[4] !== '' ? explode( ', ', $params[4] ) : [];
					$removed = $params[5] !== '' ? explode( ', ', $params[5] ) : [];
				} else {
					[ 'added' => $added, 'removed' => $removed ] = $this->entry->getParameters();
				}
				return [
					// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ), // 4::name
					// @phan-suppress-next-line SecurityCheck-XSS
					4 => $this->formatWikis( $added ),
					// @phan-suppress-next-line SecurityCheck-XSS
					5 => $this->formatWikis( $removed ),
					6 => Message::numParam( count( $added ) ),
					7 => Message::numParam( count( $removed ) ),
				];
			case 'setnewtype':
				return [
					// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ), // 4::name
					4 => $this->formatType( $params[4] ), // 5::oldType
					5 => $this->formatType( $params[5] ), // 6::type
				];
			case 'setrename':
				return [
					// @phan-suppress-next-line SecurityCheck-XSS,SecurityCheck-DoubleEscaped
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ), // 4::name
					4 => $params[4], // 5::oldName
				];
			default:
				throw new UnexpectedValueException( "Invalid log action: gblrights/$action" );
		}
	}

}
