<?php

/**
 * Log a global rename into the local log
 *
 * @license GNU GPL v2+
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
	 * @param string $reason
	 */
	public function log( $oldName, $newName, $reason ) {
		$logEntry = new ManualLogEntry( 'gblrename', 'rename' );
		$logEntry->setPerformer( $this->performingUser );

		$logEntry->setTarget(
			Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $newName )
		);

		$logEntry->setComment( $reason );
		$logEntry->setParameters( array(
			'4::olduser' => $oldName,
			'5::newuser' => $newName,
		) );

		$logEntry->setRelations( array(
			'oldname' => $oldName,
		) );

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
		$logEntry->setParameters( array(
			'4::olduser' => $oldName,
			'5::newuser' => $newName,
			'6::oldwiki' => $wiki,
		) );

		$logEntry->setRelations( array(
			'oldname' => $oldName,
		) );

		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}
}
