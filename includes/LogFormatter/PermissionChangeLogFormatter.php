<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - gblrights/groupprms2
 */
class PermissionChangeLogFormatter extends LogFormatter {

	private function makeRightsList( array $ids ): string {
		return $ids !== []
			? $this->formatParameterValue( 'list', $ids )
			: $this->msg( 'rightsnone' )->text();
	}

	protected function getMessageKey() {
		return 'logentry-gblrights-groupprms2';
	}

	protected function extractParameters() {
		if ( $this->entry->isLegacy() ) {
			return parent::extractParameters();
		}

		[ 'addRights' => $addRights, 'removeRights' => $removeRights ] = $this->entry->getParameters();
		return [
			3 => $this->makeRightsList( $addRights ),
			4 => $this->makeRightsList( $removeRights ),
		];
	}

}
