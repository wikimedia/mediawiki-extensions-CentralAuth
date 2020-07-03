<?php

class ChangeStatusLogFormatter extends LogFormatter {

	private function formatStatuses( array $array ) : string {
		if ( $array !== [] ) {
			return $this->formatParameterValue( 'list', array_map( function ( $key ) {
				return $this->msg( 'centralauth-log-status-' . $key )->text();
			}, $array ) );
		} else {
			return $this->msg( 'centralauth-log-status-none' )->text();
		}
	}

	protected function getMessageKey() {
		return 'logentry-globalauth-setstatus';
	}

	protected function extractParameters() {
		$params = parent::extractParameters();
		if ( !$this->entry->isLegacy() ) {
			[ 'added' => $added, 'removed' => $removed ] = $this->entry->extractParameters();
			$params[3] = $this->formatStatuses( $added );
			$params[4] = $this->formatStatuses( $removed );
		}
		return $params;
	}

}