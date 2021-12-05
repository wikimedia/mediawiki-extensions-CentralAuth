<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - gblrights/usergroups
 */
class GroupMembershipChangeLogFormatter extends LogFormatter {

	private function makeGroupsList( array $groups, array $metadata ): string {
		$groupNames = [];

		$groups = array_combine( $groups, $metadata );

		// Ensure temporary groups are displayed first, to avoid ambiguity like
		// "first, second (expires at some point)" (unclear if only second expires or if both expire)
		uasort( $groups, static function ( $first, $second ) {
			if ( !$first['expiry'] && $second['expiry'] ) {
				return 1;
			} elseif ( $first['expiry'] && !$second['expiry'] ) {
				return -1;
			} else {
				return 0;
			}
		} );

		$language = $this->context->getLanguage();
		$user = $this->context->getUser();

		foreach ( $groups as $group => $metadata ) {
			$name = $group;

			if ( $metadata['expiry'] ) {
				$name = $this->msg( 'rightslogentry-temporary-group' )
					->params( $name, $language->userTimeAndDate( $metadata['expiry'], $user ) )
					->escaped();
			}

			$groupNames[] = $name;
		}

		return $groups !== []
			? $this->formatParameterValue( 'list', $groupNames )
			: $this->msg( 'rightsnone' )->text();
	}

	private function makeGroupsListWithoutMetadata( array $groups ) {
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

		$params = $this->entry->getParameters();

		if ( isset( $params['oldMetadata'] ) ) {
			return [
				3 => $this->makeGroupsList( $params['oldGroups'], $params['oldMetadata'] ),
				4 => $this->makeGroupsList( $params['newGroups'], $params['newMetadata'] ),
			];
		}

		return [
			3 => $this->makeGroupsListWithoutMetadata( $params['oldGroups'] ),
			4 => $this->makeGroupsListWithoutMetadata( $params['newGroups'] ),
		];
	}

	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		// remove "User:" prefix
		$params[2] = $this->formatParameterValue( 'user-link', $this->entry->getTarget()->getText() );
		return $params;
	}
}
