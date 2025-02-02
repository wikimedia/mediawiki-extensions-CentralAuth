<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;
use RightsLogFormatter;

/**
 * Handles the following log types:
 * - gblrights/usergroups
 */
class GroupMembershipChangeLogFormatter extends RightsLogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		return 'logentry-gblrights-usergroups';
	}

	/** @inheritDoc */
	protected function shouldProcessParams( $params ) {
		return true;
	}

	/** @inheritDoc */
	protected function getOldGroups( $params ) {
		$allParams = $this->entry->getParameters();

		$groupNames = $allParams['oldGroups'] ?? $params[3] ?? [];
		$metadata = $allParams['oldMetadata'] ?? [];

		$groups = $this->joinGroupsWithExpiries( $groupNames, $metadata );
		// Legacy entries had (none) literal as a placeholder
		unset( $groups['(none)'] );
		return $groups;
	}

	/** @inheritDoc */
	protected function getNewGroups( $params ) {
		$allParams = $this->entry->getParameters();

		$groupNames = $allParams['newGroups'] ?? $params[4] ?? [];
		$metadata = $allParams['newMetadata'] ?? [];

		$groups = $this->joinGroupsWithExpiries( $groupNames, $metadata );
		unset( $groups['(none)'] );
		return $groups;
	}

	/** @inheritDoc */
	protected function replaceGroupsWithMemberNames( &$groupNames ) {
		// Do nothing, to display the internal group ids
	}

	/** @inheritDoc */
	protected function getParametersForApi() {
		// gblrights/usergroups log entries are returned in a raw form from
		// the database, without trying to normalize legacy ones. Keep that
		// behavior by pinpointing to the base LogFormatter class.
		return LogFormatter::getParametersForApi();
	}

	/** @inheritDoc */
	public function formatParametersForApi() {
		return LogFormatter::formatParametersForApi();
	}
}
