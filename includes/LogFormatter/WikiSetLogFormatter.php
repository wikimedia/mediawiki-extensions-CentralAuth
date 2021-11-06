<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use Message;
use MWException;

/**
 * Handles the following log types:
 * - gblrights/deleteset
 * - gblrights/newset
 * - gblrights/setchange
 * - gblrights/setnewtype
 * - gblrights/setrename
 */
class WikiSetLogFormatter extends LogFormatter {

	private function formatWikiSetLink( $name ) {
		if ( !$this->plaintext ) {
			$link = $this->makePageLink( $this->entry->getTarget(), [], htmlspecialchars( $name ) );
			return Message::rawParam( $link );
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
					3 => $this->formatWikiSetLink( $params[3] ), // 4::name
				];
			case 'newset':
				return [
					3 => $this->formatWikiSetLink( $params[3] ), // 4::name
					4 => $this->formatType( $params[4] ), // 5::type
					5 => $this->entry->isLegacy()
						? $params[5]
						: $this->formatWikis( $this->entry->getParameters()['wikis'] ),
				];
			case 'setchange':
				// TODO: plural support for "added"/"removed"
				if ( $this->entry->isLegacy() ) {
					return [
						3 => $this->formatWikiSetLink( $params[3] ),
						4 => $params[4],
						5 => $params[5],
					];
				} else {
					[ 'added' => $added, 'removed' => $removed ] = $this->entry->getParameters();
					return [
						3 => $this->formatWikiSetLink( $params[3] ), // 4::name
						4 => $this->formatWikis( $added ),
						5 => $this->formatWikis( $removed ),
					];
				}
			case 'setnewtype':
				return [
					3 => $this->formatWikiSetLink( $params[3] ), // 4::name
					4 => $this->formatType( $params[4] ), // 5::oldType
					5 => $this->formatType( $params[5] ), // 6::type
				];
			case 'setrename':
				return [
					3 => $this->formatWikiSetLink( $params[3] ), // 4::name
					4 => $params[4], // 5::oldName
				];
			default:
				throw new MWException( "Invalid log action: gblrights/$action" );
		}
	}

}
