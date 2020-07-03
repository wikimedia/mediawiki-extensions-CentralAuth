<?php

/**
 * Handles the following log types:
 * - gblrights/deleteset
 * - gblrights/newset
 * - gblrights/setchange
 * - gblrights/setnewtype
 * - gblrights/setrename
 */
class WikiSetLogFormatter extends LogFormatter {

	private function formatWikis( $wikis ) : string {
		if ( !$wikis ) {
			return $this->msg( 'rightsnone' )->text();
		} elseif ( is_array( $wikis ) ) {
			// TODO: investigate i18n
			return implode( ', ', $wikis );
		} else {
			return $wikis;
		}
	}

	protected function extractParameters() {
		$params = parent::extractParameters();
		if ( !$this->plaintext ) {
			$params[3] = $this->makePageLink( $this->entry->getTarget(), htmlspecialchars( $params[3] ) );
		}
		// Give grep a chance to find the usages:
		// logentry-gblrights-newset, logentry-gblrights-setrename,
		// logentry-gblrights-setnewtype, logentry-gblrights-setchange,
		// logentry-gblrights-deleteset
		switch ( $this->entry->getAction() ) {
			case 'newset':
				$params[4] = WikiSet::formatType( $params[4] );
				if ( $this->entry->isLegacy() ) {
					$params[5] = $this->formatWikis( $params[5] );
				} else {
					$params[5] = $this->formatWikis( $this->entry->getParameters()['wiki'] );
				}
				break;
			case 'setnewtype':
				$params[4] = WikiSet::formatType( $params[4] );
				$params[5] = WikiSet::formatType( $params[5] );
				break;
			case 'setchange':
				if ( $this->entry->isLegacy() ) {
					[ 4 => $added, 5 => $removed ] = $params;
				} else {
					[ 'added' => $added, 'removed' => $removed ] = $this->entry->getParameters();
				}
				$params[4] = $this->formatWikis( $added );
				$params[5] = $this->formatWikis( $removed );
				break;
			case 'deleteset':
			case 'setrename':
			default:
				break;
		}
		return $params;
	}

}