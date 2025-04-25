<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use MediaWiki\Logging\LogFormatter;

/**
 * Handles the following log types:
 * - gblrights/groupperms
 * - gblrights/groupprms2
 * Messages used here:
 * - logentry-gblrights-groupperms
 * - logentry-gblrights-groupprms2
 */
class PermissionChangeLogFormatter extends LogFormatter {

	private function makeRightsList( array $ids ): string {
		return $ids !== []
			? $this->formatParameterValue( 'list', $ids )
			: $this->msg( 'rightsnone' )->text();
	}

	/** @inheritDoc */
	protected function extractParameters() {
		if ( $this->entry->getSubtype() === 'groupprms2' ) {
			if ( $this->entry->isLegacy() ) {
				return parent::extractParameters();
			}

			[ 'addRights' => $addRights, 'removeRights' => $removeRights ] = $this->entry->getParameters();
			return [
				3 => $this->makeRightsList( $addRights ),
				4 => $this->makeRightsList( $removeRights ),
			];
		} else {
			$params = parent::extractParameters();
			$oldRights = $params[3] === '(none)' ? [] : explode( ', ', $params[3] );
			$newRights = $params[4] === '(none)' ? [] : explode( ', ', $params[4] );
			return [
				// False positive
				// @phan-suppress-next-line SecurityCheck-XSS
				3 => $this->makeRightsList( $oldRights ),
				// False positive
				// @phan-suppress-next-line SecurityCheck-XSS
				4 => $this->makeRightsList( $newRights ),
			];
		}
	}

}
