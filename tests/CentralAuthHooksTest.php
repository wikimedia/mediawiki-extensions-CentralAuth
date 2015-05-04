<?php
/**
 * Tests for CentralAuthHooks. Only tests that do not
 * require the database to be set up.
 *
 * @group CentralAuth
 */
class CentralAuthHooksTest extends MediaWikiTestCase {

	/**
	 * @covers CentralAuthHooks::onPasswordPoliciesForUser
	 * @dataProvider provideOnPasswordPoliciesForUser
	 */
	public function testOnPasswordPoliciesForUser( $localgroups, $globalgroups, $expected ) {
		$this->setMwGlobals( array(
			'wgCentralAuthGlobalPasswordPolicies' => array(
				'bureaucrat' => array(
					'MinimalPasswordLength' => 10,
					'MinimumPasswordLengthToLogin' => 1,
					'PasswordCannotMatchUsername' => true,
				),
				'sysop' => array(
					'MinimalPasswordLength' => 9,
					'MinimumPasswordLengthToLogin' => 1,
					'PasswordCannotMatchUsername' => true,
				),
				'bot' => array(
					'MinimalPasswordLength' => 8,
					'MinimumPasswordLengthToLogin' => 1,
					'PasswordCannotMatchUsername' => true,
				),
				'steward' => array(
					'MinimalPasswordLength' => 9,
					'MinimumPasswordLengthToLogin' => 4,
					'PasswordCannotMatchUsername' => true,
				),
			),
		) );

		/** @var PHPUnit_Framework_MockObject_MockObject|CentralAuthUser $ca */
		$ca = $this->getMockBuilder( 'CentralAuthUser' )
			->disableOriginalConstructor()
			->setMethods( array( 'getLocalGroups', 'getGlobalGroups', 'exists' ) )
			->getMock();
		$ca->expects( $this->any() )->method( 'exists' )->will( $this->returnValue( true ) );
		$ca->expects( $this->any() )->method( 'getGlobalGroups' )->will( $this->returnValue( $globalgroups ) );
		$ca->expects( $this->any() )->method( 'getLocalGroups' )->will( $this->returnValue( $localgroups ) );
		$user = User::newFromName( 'MockedCentralAuthUser' );
		$user->centralAuthObj = $ca;
		$policy = array();
		CentralAuthHooks::onPasswordPoliciesForUser( $user, $policy );
		$this->assertEquals( $expected, $policy );
	}

	public function provideOnPasswordPoliciesForUser() {
		return array(
			array(
				array(),
				array(),
				array()
			),
			array(
				array( 'bot' ),
				array(),
				array(
					'MinimalPasswordLength' => 8,
					'MinimumPasswordLengthToLogin' => 1,
					'PasswordCannotMatchUsername' => true,
				)
			),
			array(
				array( 'bot', 'sysop' ),
				array(),
				array(
					'MinimalPasswordLength' => 9,
					'MinimumPasswordLengthToLogin' => 1,
					'PasswordCannotMatchUsername' => true,
				)
			),
			array(
				array( 'bureaucrat' ),
				array( 'steward' ),
				array(
					'MinimalPasswordLength' => 10,
					'MinimumPasswordLengthToLogin' => 4,
					'PasswordCannotMatchUsername' => true,
				)
			),

		);
	}
}
