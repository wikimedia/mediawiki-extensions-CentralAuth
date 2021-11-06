<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - globalauth/setstatus
 * - suppress/setstatus
 */
class ChangeStatusLogFormatter extends LogFormatter {

	private function formatStatuses( array $array ): string {
		if ( $array !== [] ) {
			$values = array_map( function ( $key ) {
				return $this->msg( 'centralauth-log-status-' . $key )->text();
			}, $array );
			return $this->formatParameterValue( 'list', $values );
		}
		return $this->msg( 'centralauth-log-status-none' )->text();
	}

	protected function getMessageKey() {
		return 'logentry-globalauth-setstatus';
	}

	protected function extractParameters() {
		if ( $this->entry->isLegacy() ) {
			return parent::extractParameters();
		}

		[ 'added' => $added, 'removed' => $removed ] = $this->entry->getParameters();
		return [
			3 => $this->formatStatuses( $added ),
			4 => $this->formatStatuses( $removed ),
		];
	}

}
