<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use MediaWiki\Message\Message;
use UnexpectedValueException;

/**
 * Handles the following log types:
 * - gblrights/deleteset
 * - gblrights/newset
 * - gblrights/setchange
 * - gblrights/setnewtype
 * - gblrights/setrename
 *
 * phan-taint-check gets very confused by $this->plaintext changing expected taint types everywhere,
 * so manual annotations are needed. They should be correct for the non-plaintext mode (HTML output).
 */
class WikiSetLogFormatter extends LogFormatter {

	/**
	 * @param string $name
	 * @param-taint $name none
	 * @return string wikitext or html
	 * @return-taint onlysafefor_html
	 */
	private function formatWikiSetLink( $name ) {
		if ( !$this->plaintext ) {
			return $this->makePageLink( $this->entry->getTarget(), [], htmlspecialchars( $name ) );
		}
		return $name;
	}

	/**
	 * @param string $type
	 *
	 * @return string
	 */
	private function formatType( $type ): string {
		// Give grep a chance to find the usages:
		// centralauth-rightslog-set-optin, centralauth-rightslog-set-optout
		return $this->msg( "centralauth-rightslog-set-$type" )->text();
	}

	/**
	 * @param string[] $wikis
	 * @param-taint $wikis none
	 * @return string Plain text
	 * @return-taint tainted
	 */
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
					// 4::name
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ),
				];
			case 'newset':
				$wikis = $this->entry->isLegacy()
					// shouldn't be empty
					? explode( ', ', $params[5] )
					: $this->entry->getParameters()['wikis'];
				return [
					// 4::name
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ),
					// 5::type
					4 => $this->formatType( $params[4] ),
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
					// 4::name
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ),
					4 => $this->formatWikis( $added ),
					5 => $this->formatWikis( $removed ),
					6 => Message::numParam( count( $added ) ),
					7 => Message::numParam( count( $removed ) ),
				];
			case 'setnewtype':
				return [
					// 4::name
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ),
					// 5::oldType
					4 => $this->formatType( $params[4] ),
					// 6::type
					5 => $this->formatType( $params[5] ),
				];
			case 'setrename':
				return [
					// 4::name
					3 => Message::rawParam( $this->formatWikiSetLink( $params[3] ) ),
					// 5::oldName
					4 => $params[4],
				];
			default:
				throw new UnexpectedValueException( "Invalid log action: gblrights/$action" );
		}
	}

}
