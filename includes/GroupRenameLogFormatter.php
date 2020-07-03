<?php

class GroupRenameLogFormatter extends LogFormatter {

	protected function extractParameters() {
		$params = parent::extractParameters();
		if ( $this->entry->isLegacy() ) {
			if ( isset( $params[4] ) ) {
				$newName = $params[3];
				$oldName = $params[4];
			} else {
				$newName = $this->entry->getTitle()->getSubpageText();
				$oldName = $params[3];
			}
			$new = Title::newFromText( $newName );
			$old = Title::newFromText( $oldName );
		} else {
			[ 'newName' => $newName, 'oldName' => $oldName ] = $this->entry->extractParameters();
			$new = SpecialPage::getTitleFor( 'GlobalGroupPermissions', $newName );
			$old = SpecialPage::getTitleFor( 'GlobalGroupPermissions', $oldName );
		}
		$params[3] = $this->makePageLink( $new );
		$params[4] = $this->makePageLink( $old );
		return $params;
	}

}