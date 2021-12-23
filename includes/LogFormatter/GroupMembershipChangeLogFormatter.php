<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - gblrights/usergroups
 */
class GroupMembershipChangeLogFormatter extends LogFormatter {

	private function makeGroupsList( array $groups ): string {
		return $groups !== []
			? $this->formatParameterValue( 'list', $groups )
			: $this->msg( 'rightsnone' )->text();
	}

	protected function getMessageKey() {
		return 'logentry-gblrights-usergroups';
	}

	protected function extractParameters() {
		if ( $this->entry->isLegacy() ) {
			return parent::extractParameters();
		}

		[ 'oldGroups' => $oldGroups, 'newGroups' => $newGroups ] = $this->entry->getParameters();
		return [
			3 => $this->makeGroupsList( $oldGroups ),
			4 => $this->makeGroupsList( $newGroups ),
		];
	}

	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		// remove "User:" prefix
		$params[2] = $this->formatParameterValue( 'user-link', $this->entry->getTarget()->getText() );
		return $params;
	}
}
