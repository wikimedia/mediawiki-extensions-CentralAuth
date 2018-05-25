<?php

/**
 * Log a global rename into the local log
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserLogger {
	/**
	 * @var User
	 */
	private $performingUser;

	/**
	 * @param User $performingUser
	 */
	public function __construct( User $performingUser ) {
		$this->performingUser = $performingUser;
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @param array $options
	 */
	public function log( $oldName, $newName, $options ) {
		$logEntry = new ManualLogEntry( 'gblrename', 'rename' );
		$logEntry->setPerformer( $this->performingUser );

		$logEntry->setTarget(
			Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $newName )
		);

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
		$logEntry = new ManualLogEntry( 'gblrename', 'promote' );
		$logEntry->setPerformer( $this->performingUser );

		$logEntry->setTarget(
			Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $newName )
		);

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
