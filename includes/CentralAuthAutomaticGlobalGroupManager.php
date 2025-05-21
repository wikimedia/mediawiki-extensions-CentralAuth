<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Manages the global groups that a user should have automatically, based on their
 * membership of other local and global groups. Automatic groups are defined in the
 * config CentralAuthAutomaticGlobalGroups.
 *
 * @since 1.44
 */
class CentralAuthAutomaticGlobalGroupManager {

	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		CAMainConfigNames::CentralAuthAutomaticGlobalGroups,
	];

	private ServiceOptions $options;

	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * Compare whether an expiry timestamp is earlier (smaller) than another expiry timestamp.
	 * Note that null represents no expiry, so is always later than any defined expiry.
	 *
	 * @param ?string $expiry1
	 * @param ?string $expiry2
	 * @return bool $expiry1 is earlier than $expiry2
	 */
	private function expiryEarlierThan( ?string $expiry1, ?string $expiry2 ): bool {
		if ( $expiry1 === null ) {
			return false;
		} elseif ( $expiry2 === null ) {
			return true;
		}
		return ConvertibleTimestamp::convert( TS_MW, $expiry1 ) <
			ConvertibleTimestamp::convert( TS_MW, $expiry2 );
	}

	/**
	 * Compare expiry timestamps for equality, or equivalence if they are in different formats.
	 *
	 * @param ?string $expiry1
	 * @param ?string $expiry2
	 * @return bool $expiry1 is the same time as $expiry2
	 */
	private function expiryEquals( ?string $expiry1, ?string $expiry2 ): bool {
		if ( $expiry1 === null && $expiry2 === null ) {
			return true;
		} elseif ( $expiry1 === null || $expiry2 === null ) {
			return false;
		}
		return ConvertibleTimestamp::convert( TS_MW, $expiry1 ) ===
			ConvertibleTimestamp::convert( TS_MW, $expiry2 );
	}

	/**
	 * Calculates which groups should be added or removed from a user, based on their
	 * membership of other groups. Updates arrays of groups to be added and removed,
	 * potentially to add or remove automatic groups.
	 *
	 * The caller must handle updating user's groups.
	 *
	 * @param array<string,?string> $assignedGroups Map of the user's groups, local and global, to their
	 *  expiries. An expiry of null indicates that the group does not expire.
	 * @param string[] &$groupsToAdd Global groups that will be added. Automatic global groups
	 *  may be added to or removed from this list. If changing an expiry of a group, it must be added to
	 *  this list too.
	 * @param string[] &$groupsToRemove Global groups that will be removed. Automatic global
	 *  groups may be added to or removed from this list.
	 * @param array<string,?string> &$expiriesToChange Group expiries that will be updated, represented
	 *  as a map of global user groups to their expiries. An expiry of null indicates that the group
	 *  does not expire. Expiries may be added to, removed from, or changed in this list.
	 */
	public function handleAutomaticGlobalGroups(
		array $assignedGroups,
		array &$groupsToAdd,
		array &$groupsToRemove,
		array &$expiriesToChange
	): void {
		$automaticGroups = $this->getAutomaticGlobalGroups();
		foreach ( $automaticGroups as $automaticGroup ) {
			$shouldHaveAutomaticGroupUntil = $this->shouldHaveAutomaticGroupUntil(
				$automaticGroup,
				$assignedGroups,
				$automaticGroups
			);

			// Cannot manually change the expiry of an automatic group.
			unset( $expiriesToChange[$automaticGroup] );

			if ( $shouldHaveAutomaticGroupUntil ) {

				// Group should be assigned, with the correct expiry.
				$expiry = $shouldHaveAutomaticGroupUntil === true ?
					null :
					$shouldHaveAutomaticGroupUntil;

				if ( !array_key_exists( $automaticGroup, $assignedGroups ) ) {
					$groupsToAdd[] = $automaticGroup;
					if ( $expiry !== null ) {
						$expiriesToChange[$automaticGroup] = $expiry;
					}
				} elseif ( !$this->expiryEquals( $assignedGroups[$automaticGroup], $expiry ) ) {
					$groupsToAdd[] = $automaticGroup;
					$expiriesToChange[$automaticGroup] = $expiry;
				}
				$key = array_search( $automaticGroup, $groupsToRemove );
				if ( $key !== false ) {
					unset( $groupsToRemove[$key] );
				}

			} else {

				// Group should not be assigned.
				if ( array_key_exists( $automaticGroup, $assignedGroups ) ) {
					$groupsToRemove[] = $automaticGroup;
				}
				$key = array_search( $automaticGroup, $groupsToAdd );
				if ( $key !== false ) {
					unset( $groupsToAdd[$key] );
				}

			}
		}
	}

	/**
	 * Calculate whether the user should have the automatic group. They should have the group
	 * if and only if at least one of their assigned groups promotes them to it. If only promoted
	 * due to groups that expire, then the automatic group should expire with the latest expiry
	 * time.
	 *
	 * @param string $automaticGroup
	 * @param array<string,?string> $assignedGroups
	 * @param string[] $automaticGroups The automatic global groups
	 * @return bool|int If an int, the expiry time until which the user should have the automatic
	 * group. If true, the user should have the automatic group indefinitely. If false, they
	 * should not have the automatic group.
	 */
	private function shouldHaveAutomaticGroupUntil(
		string $automaticGroup,
		array $assignedGroups,
		array $automaticGroups
	) {
		$config = $this->options->get( CAMainConfigNames::CentralAuthAutomaticGlobalGroups );
		$shouldHaveAutomaticGroupUntil = false;

		foreach ( $assignedGroups as $assignedGroup => $expiry ) {
			if (
				isset( $config[$assignedGroup] ) &&
				in_array( $automaticGroup, $config[$assignedGroup] ) &&
				!in_array( $assignedGroup, $automaticGroups )
			) {
				if ( $expiry === null ) {
					$shouldHaveAutomaticGroupUntil = true;
					// Should have the automatic group indefinitely. No need to keep checking.
					break;
				} else {
					if (
						$shouldHaveAutomaticGroupUntil === false ||
						$this->expiryEarlierThan( $shouldHaveAutomaticGroupUntil, $expiry )
					) {
						$shouldHaveAutomaticGroupUntil = $expiry;
					}
				}
			}
		}

		return $shouldHaveAutomaticGroupUntil;
	}

	/**
	 * Get the names of the global groups that are added and removed automatically.
	 *
	 * @return string[] The automatic global groups
	 */
	public function getAutomaticGlobalGroups() {
		$config = $this->options->get( CAMainConfigNames::CentralAuthAutomaticGlobalGroups );
		return array_unique( array_merge( ...array_values( $config ) ) );
	}
}
