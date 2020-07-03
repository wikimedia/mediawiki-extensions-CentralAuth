<?php

/**
 * Handles the following log types:
 * - gblrights/groupprms2
 */
class PermissionChangeLogFormatter extends LogFormatter {

	private function makeRightsList( array $ids ) : string {
		return $ids !== []
			// TODO: investigate i18n
			? implode( ', ', $ids )
			: $this->msg( 'rightsnone' )->text();
	}

	protected function getMessageKey() {
		return 'logentry-gblrights-groupprms2';
	}

	protected function extractParameters() {
		$params = parent::extractParameters();
		if ( !$this->entry->isLegacy() ) {
			[ 'addRights' => $addRights, 'removeRights' => $removeRights ] = $this->entry->getParameters();
			$params[3] = $this->makeRightsList( $addRights );
			$params[4] = $this->makeRightsList( $removeRights );
		}
		return $params;
	}

}
