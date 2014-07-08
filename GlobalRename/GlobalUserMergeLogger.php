<?php

/**
 * Log a global merge into the local log
 *
 * @license GNU GPL v2+
 * @author Kunal Mehta
 */

class GlobalUserMergeLogger implements IGlobalRenameUserLogger {
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
		$logEntry = new ManualLogEntry( 'gblrename', 'merge' );
		$logEntry->setPerformer( $this->performingUser );

		$logEntry->setTarget(
			Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $newName )
		);

		$logEntry->setComment( $reason );
		$logEntry->setParameters( array(
			'4::olduser' => $oldName,
			'5::newuser' => $newName,
		) );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}
}