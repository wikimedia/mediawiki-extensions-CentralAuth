<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IReadableDatabase;

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

	/**
	 * This method does not check log_deleted, and thus will reveal the existence of hidden rename log
	 * entries. This seems unavoidable: we have to reveal that the username can't be registered when
	 * someone tries to register it, and there is no other plausible error message we could give.
	 * Please review whether that's appropriate for your use case when calling it.
	 */
	public static function isPreviouslyRenamedAccount( string $username, IReadableDatabase $db ): bool {
		return (bool)$db->newSelectQueryBuilder()
			->from( 'logging' )
			->join( 'log_search', null, 'ls_log_id=log_id' )
			->where( [
				'ls_field' => 'oldname',
				'ls_value' => strtr( $username, '_', ' ' ),
				'log_type' => 'gblrename',
				'log_namespace' => NS_SPECIAL,
			] )
			->field( '1' )
			->caller( __METHOD__ )
			->fetchField();
	}
}
