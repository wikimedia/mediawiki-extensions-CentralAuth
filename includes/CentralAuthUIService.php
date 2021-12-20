<?php

namespace MediaWiki\Extension\CentralAuth;

use HTMLForm;
use IContextSource;
use LogEventsList;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MessageLocalizer;
use TitleFactory;

/**
 * Shared utilities for building UIs in CentralAuth
 *
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class CentralAuthUIService {
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * Format a given CentralAuthUser::HIDDEN_* constant to a string.
	 * @param MessageLocalizer $localizer
	 * @param string|int $level one of the CentralAuthUser::HIDDEN_* constants
	 * @return string Already html escaped
	 */
	public function formatHiddenLevel( MessageLocalizer $localizer, $level ): string {
		// PHP typing fun: without this if, invalid strings would match the hidden_normalize_none block,
		// as 'any string' == 0 (yes, really!)
		if ( is_int( $level ) ) {
			switch ( $level ) {
				case CentralAuthUser::HIDDEN_LEVEL_NONE:
					return $localizer->msg( 'centralauth-admin-no' )->escaped();
				case CentralAuthUser::HIDDEN_LEVEL_LISTS:
					return $localizer->msg( 'centralauth-admin-hidden-list' )->escaped();
				case CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED:
					return $localizer->msg( 'centralauth-admin-hidden-oversight' )->escaped();
			}
		} else {
			switch ( $level ) {
				case CentralAuthUser::HIDDEN_NONE:
					return $localizer->msg( 'centralauth-admin-no' )->escaped();
				case CentralAuthUser::HIDDEN_LISTS:
					return $localizer->msg( 'centralauth-admin-hidden-list' )->escaped();
				case CentralAuthUser::HIDDEN_OVERSIGHT:
					return $localizer->msg( 'centralauth-admin-hidden-oversight' )->escaped();
			}
		}

		return '';
	}

	/**
	 * Format a number of seconds into a human-readable timespan ("XX hours ago").
	 * @param MessageLocalizer $localizer
	 * @param int $time in seconds
	 * @return string
	 */
	public function prettyTimespan( MessageLocalizer $localizer, int $time ): string {
		// map all units for how many times they fit in the next unit
		$units = [
			'seconds' => 60,
			'minutes' => 60,
			'hours' => 24,
			'days' => 30.417,
			'months' => 12,
			'years' => 1
		];

		// Used messaged (to make sure that grep finds them):
		// 'centralauth-seconds-ago', 'centralauth-minutes-ago', 'centralauth-hours-ago'
		// 'centralauth-days-ago', 'centralauth-months-ago', 'centralauth-years-ago'

		// check each unit individually, to find a suitable unit to display
		foreach ( $units as $unit => $chunk ) {
			// if it's less than two times the size of the next unit, use this unit
			// for example: 6 seconds uses seconds, 61 seconds uses seconds, 119 seconds uses seconds,
			// but 121 seconds is detected that seconds isn't a useful unit anymore, and it goes to
			// the next unit (minutes), and then it outputs 2 minutes (2 is under 2*60, it won't go to hours)
			if ( $time < 2 * $chunk ) {
				return $localizer->msg( "centralauth-$unit-ago" )->numParams( $time )->text();
			}

			// convert to the next unit, eg. seconds into minutes, minutes into hours, ...
			$time = intval( $time / $chunk );
		}

		// if the timespan is so long that it's more than two times the size of the last unit,
		// use the last unit (years) anyways
		return $localizer->msg( "centralauth-years-ago" )->numParams( $time )->text();
	}

	/**
	 * Append an extract of the global rename log for the specific username.
	 * @param IContextSource $context
	 * @param string $name
	 */
	public function showRenameLogExtract( IContextSource $context, string $name ): void {
		$caTitle = $this->titleFactory->makeTitle( NS_SPECIAL, 'CentralAuth/' . $name );

		$logs = '';
		LogEventsList::showLogExtract( $logs, 'gblrename', $caTitle, '', [
			'showIfEmpty' => true,
		] );

		$formDescriptor = [
			'logs' => [
				'type' => 'info',
				'raw' => true,
				'default' => $logs,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm->suppressDefaultSubmit()
			->setWrapperLegendMsg( 'centralauth-rename-progress-logs-fieldset' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Format antispoof conflicts, change hidden ones to a generic text and link others to Special:CentralAuth.
	 * @param MessageLocalizer $localizer
	 * @param string $oldName User's old (current) name
	 * @param string[] $conflicts Conflicting usernames
	 * @return string[] Usernames formatted as wikitext, either saying that it's hidden or
	 * linking to Special:CentralAuth
	 */
	public function processAntiSpoofConflicts(
		MessageLocalizer $localizer,
		string $oldName,
		array $conflicts
	): array {
		$display = [];

		foreach ( $conflicts as $name ) {
			if ( $name === $oldName ) {
				// Not a conflict since the old usage will go away
				continue;
			}
			$ca = CentralAuthUser::getPrimaryInstanceByName( $name );
			if ( $ca->isHidden() ) {
				$display[] = $localizer->msg( 'centralauth-rename-conflict-hidden' )->text();
			} else {
				$display[] = "[[Special:CentralAuth/$name|$name]]";
			}
		}

		return $display;
	}
}
