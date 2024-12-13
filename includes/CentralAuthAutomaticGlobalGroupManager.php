<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;

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
	 * Calculates which groups should be added or removed from a user, based on their
	 * membership of other groups. Updates arrays of groups to be added and removed,
	 * potentially to add or remove automatic groups.
	 *
	 * The caller must handle updating user's groups.
	 *
	 * @param string[] $assignedGroups The user's groups, local and global
	 * @param string[] &$groupsToAdd Global groups that will be added. Automatic global groups
	 *  may be added to or removed from this list.
	 * @param string[] &$groupsToRemove Global groups that will be removed. Automatic global
	 *  groups may be added to or removed from this list.
	 */
	public function handleAutomaticGlobalGroups(
		array $assignedGroups,
		array &$groupsToAdd,
		array &$groupsToRemove
	): void {
		$automaticGroups = $this->getAutomaticGlobalGroups();
		foreach ( $automaticGroups as $automaticGroup ) {
			$shouldHaveAutomaticGroup = $this->shouldHaveAutomaticGroup(
				$automaticGroup,
				$assignedGroups
			);

			if ( $shouldHaveAutomaticGroup ) {
				if ( !in_array( $automaticGroup, $assignedGroups ) ) {
					$groupsToAdd[] = $automaticGroup;
				}
				$key = array_search( $automaticGroup, $groupsToRemove );
				if ( $key !== false ) {
					unset( $groupsToRemove[$key] );
				}
			} else {
				if ( in_array( $automaticGroup, $assignedGroups ) ) {
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
	 * @param string $automaticGroup
	 * @param string[] $assignedGroups
	 * @return bool The user should have the automatic group. They should have the group
	 *  if and only if at least one of their assigned groups promotes them to it.
	 */
	private function shouldHaveAutomaticGroup(
		string $automaticGroup,
		array $assignedGroups
	) {
		$config = $this->options->get( CAMainConfigNames::CentralAuthAutomaticGlobalGroups );
		$shouldHaveGlobalGroup = false;

		foreach ( $assignedGroups as $assignedGroup ) {
			if (
				isset( $config[$assignedGroup] ) &&
				in_array( $automaticGroup, $config[$assignedGroup] )
			) {
				$shouldHaveGlobalGroup = true;
				break;
			}
		}

		return $shouldHaveGlobalGroup;
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
