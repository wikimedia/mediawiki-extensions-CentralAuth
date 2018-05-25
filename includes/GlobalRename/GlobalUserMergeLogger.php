<?php

/**
 * Log a global merge into the local log
 *
 * @license GPL-2.0-or-later
 * @author Kunal Mehta
 */
class GlobalUserMergeLogger {
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
	 * @param CentralAuthUser[] $oldNames
	 * @param string $newName
	 * @param string $reason
	 */
	public function log( array $oldNames, $newName, $reason ) {
		$logEntry = new ManualLogEntry( 'gblrename', 'merge' );
		$logEntry->setPerformer( $this->performingUser );

		$logEntry->setTarget(
			Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $newName )
		);

		$imploded = implode( '|',
			array_map( function ( CentralAuthUser $user ) {
				return $user->getName();
			}, $oldNames )
		);

		$logEntry->setComment( $reason );
		$logEntry->setParameters( [
			'4::olduser' => $imploded,
			'5::newuser' => $newName,
		] );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}
}
