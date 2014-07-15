<?php
/**
 * Basic tests for CentralAuthHooks
 * @group CentralAuthDB
 */
class CentralAuthHooksUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {


	/**
	 * @dataProvider provideAbortNewAccount
	 * @covers CentralAuthHooks::onAbortNewAccount
	 */
	public function testOnAbortNewAccount( $user, $result) {
		$error = '';
		$hookResult = CentralAuthHooks::onAbortNewAccount( $user, $error );
		$this->assertSame( $result, $hookResult );
	}

	public function provideAbortNewAccount() {
		$userExists = User::newFromName( 'GlobalUser' );
		$userNotExists = User::newFromName( 'UserNotExists' );
		return array(
			array( $userNotExists, true ),
			array( $userExists, false ),
		);
	}

	/**
	 * @dataProvider provideAbortLogin
	 * @covers CentralAuthHooks::onAbortLogin
	 */
	public function testOnAbortLogin( $user, $result, $test) {
		$retval = 0;
		$msg = '';
		$hookResult = CentralAuthHooks::onAbortLogin( $user, '', $retval, $msg );
		$this->assertEquals( $result, $hookResult, $test );
	}

	public function provideAbortLogin() {
		$user = User::newFromName( 'GlobalUser' );
		$lockedUser = User::newFromName( 'GlobalLockedUser' );

		// We can fake out CentralAuthUser::getInstance() by adding centralAuthObj
		$noUser = User::newFromName( 'NoUser' );
		$noUser->centralAuthObj = CentralAuthUser::newUnattached( 'NoUser' );

		return array(
			array( $user, true, 'Attached user can login' ),
			array( $noUser, true, 'Unattached user can login' ),
			array( $lockedUser, false, 'Locked User cannot login' ),
		);
	}

}
