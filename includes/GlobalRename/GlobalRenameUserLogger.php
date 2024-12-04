<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;

/**
 * Log a global rename into the local log
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserLogger {

	private UserIdentity $performingUser;

	public function __construct( UserIdentity $performingUser ) {
		$this->performingUser = $performingUser;
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @param array $options
	 */
	public function log( $oldName, $newName, $options ) {
		// The following message is generated here:
		// * logentry-gblrename-rename
		$logEntry = new ManualLogEntry( 'gblrename', 'rename' );
		$logEntry->setPerformer( $this->performingUser );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'CentralAuth', $newName ) );
		$logEntry->setComment( $options['reason'] );
		$logEntry->setParameters( [
			'4::olduser' => $oldName,
			'5::newuser' => $newName,
			'movepages' => $options['movepages'],
			'suppressredirects' => $options['suppressredirects'],
		] );

		$logEntry->setRelations( [
			'oldname' => $oldName,
		] );

		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}

	/**
	 * Log the promotion of a local unattached to a global
	 *
	 * @param string $oldName
	 * @param string $wiki
	 * @param string $newName
	 * @param string $reason
	 */
	public function logPromotion( $oldName, $wiki, $newName, $reason ) {
		// The following message is generated here:
		// * logentry-gblrename-promote
		$logEntry = new ManualLogEntry( 'gblrename', 'promote' );
		$logEntry->setPerformer( $this->performingUser );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'CentralAuth', $newName ) );
		$logEntry->setComment( $reason );
		$logEntry->setParameters( [
			'4::olduser' => $oldName,
			'5::newuser' => $newName,
			'6::oldwiki' => $wiki,
		] );

		$logEntry->setRelations( [
			'oldname' => $oldName,
		] );

		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}
}
