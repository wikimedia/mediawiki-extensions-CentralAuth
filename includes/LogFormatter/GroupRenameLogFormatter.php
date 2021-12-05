<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use Message;
use SpecialPage;
use Title;

/**
 * Handles the following log types:
 * - gblrights/grouprename
 */
class GroupRenameLogFormatter extends LogFormatter {

	protected function getMessageKey() {
		return 'logentry-gblrights-grouprename';
	}

	protected function extractParameters() {
		if ( $this->entry->isLegacy() ) {
			$params = parent::extractParameters();
			if ( isset( $params[4] ) ) {
				$newName = $params[3];
				$oldName = $params[4];
			} else {
				$newName = $this->entry->getTarget()->getSubpageText();
				$oldName = $params[3];
			}
			$new = Title::newFromText( $newName );
			$old = Title::newFromText( $oldName );
		} else {
			[ 'newName' => $newName, 'oldName' => $oldName ] = $this->entry->getParameters();
			$new = SpecialPage::getTitleFor( 'GlobalGroupPermissions', $newName );
			$old = SpecialPage::getTitleFor( 'GlobalGroupPermissions', $oldName );
		}

		return [
			3 => Message::rawParam( $this->makePageLink( $new ) ),
			4 => Message::rawParam( $this->makePageLink( $old ) ),
		];
	}

}
