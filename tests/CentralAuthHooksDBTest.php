<?php
/**
 * Basic tests for CentralAuthHooks
 * @group CentralAuthDB
 */
class CentralAuthHooksDBTest extends CentralAuthDBTest {


	/**
	 * @dataProvider provideAbortNewAccount
	 * @covers CentralAuthHooks::onAbortNewAccount
	 */
	public function testOnAbortNewAccount( $user, $result) {
		$error = '';
		$hookResult = CentralAuthHooks::onAbortNewAccount( $user, $error );
		$this->assertEquals( $hookResult, $result );
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
		$this->assertEquals( $hookResult, $result, $test );
	}

	public function provideAbortLogin() {
		$User = User::newFromName( 'GlobalUser' );
		$LockedUser = User::newFromName( 'GlobalLockedUser' );

		// We can fake out CentralAuthUser::getInstance() by adding centralAuthObj
		$NoUser = User::newFromName( 'NoUser' );
		$NoUser->centralAuthObj = CentralAuthUser::newUnattached( 'NoUser' );

		return array(
			array( $User, true, 'Attached user can login' ),
			array( $NoUser, true, 'Unattached user can login' ),
			array( $LockedUser, false, 'Locked User cannot login' ),
		);
	}

}
