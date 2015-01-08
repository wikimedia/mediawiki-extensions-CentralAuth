<?php

/**
 * Only for tests that do not require the database to be
 * set up.
 *
 * @group CentralAuth
 */
class CentralAuthUserTest extends MediaWikiTestCase {

	/**
	 * @covers CentralAuthUser::getInstance
	 */
	public function testGetInstance() {
		$user = User::newFromName( 'FooBarBaz' );
		unset( $user->centralAuthObj );
		$caUser = CentralAuthUser::getInstance( $user );
		$this->assertInstanceOf( 'CentralAuthUser', $caUser );
		$this->assertEquals( $user->getName(), $caUser->getName() );
		$this->assertSame( $user->centralAuthObj, $caUser );

		// Now test it just reads from the cache, no matter what
		$user2 = User::newFromName( 'BazBarFoo' );
		$user2->centralAuthObj = 'blahblahblah';
		$this->assertEquals( 'blahblahblah', CentralAuthUser::getInstance( $user2 ) );
	}

	/**
	 * @covers CentralAuthUser::newUnattached
	 */
	public function testNewUnattached() {
		$ca = CentralAuthUser::newUnattached( 'FooBar' );
		$this->assertInstanceOf( 'CentralAuthUser', $ca );
		$this->assertEquals( 'FooBar', $ca->getName() );
		$this->assertFalse( $ca->isAttached() );
	}

	/**
	 * @covers CentralAuthUser::getHomeWiki
	 * @dataProvider provideGetHomeWiki
	 */
	public function testGetHomeWiki( $attached, $expected ) {
		/** @var PHPUnit_Framework_MockObject_MockObject|CentralAuthUser $ca */
		$ca = $this->getMockBuilder( 'CentralAuthUser' )
			->disableOriginalConstructor()
			->setMethods( array( 'queryAttached', 'loadState' ) )
			->getMock();

		$ca->expects( $this->any() )->method( 'queryAttached' )->will( $this->returnValue( $attached ) );
		$this->assertEquals( $expected, $ca->getHomeWiki() );
	}

	public function provideGetHomeWiki() {
		return array(
			array( array(), null ),
			array( array( 'foowiki' => array( 'attachedMethod' => 'new' ) ), 'foowiki' ),
			array( array( 'foowiki' => array( 'attachedMethod' => 'primary' ) ), 'foowiki' ),
			array( array( 'foowiki' => array( 'attachedMethod' => 'password' ), 'bazwiki' => array( 'attachedMethod' => 'new' ) ), 'bazwiki' ),
			array( array( 'foowiki' => array( 'attachedMethod' => 'password' ) ), null ),
		);
	}

	/**
	 * @covers CentralAuthUser::getPasswordFromString
	 * @dataProvider provideGetPasswordFromString
	 */
	public function testGetPasswordFromString( $pass, $salt, $type ) {
		$this->setMwGlobals( 'wgPasswordSalt', true );
		$class = new ReflectionClass( 'CentralAuthUser' );
		$method = $class->getMethod( 'getPasswordFromString' );
		$method->setAccessible( true );
		$ca = new CentralAuthUser( 'DoesNotExist' );
		$password = $method->invokeArgs( $ca, array( $pass, $salt ) );
		$this->assertInstanceOf( 'Password', $password );
		$this->assertInstanceOf( $type, $password );
	}

	public static function provideGetPasswordFromString() {
		return array(
			array(
				':pbkdf2:sha256:10000:128:Oin6/F737E41pY7dza46Dw==:f6LNAySaUdEnjI2omuj+CX1aPDnt5bzgZcdLsEcLWqF7vG0CcMyviqWaq8smXCj2HBY0sV/w2kxpsTXXOgUrJJTEjuXmEsxHTtpMO4fCfZ5nb3a1kCYA44owCzKu96i8I6VrmGYu3waxmVAzlXld3bNIxrhGUjra/Y0TmWOe1q0=',
				'',
				'Pbkdf2Password'
			),
			array(
				':B:6540e6ad:b02a3700be1eec9488a46b042a831646',
				'',
				'MWSaltedPassword'
			),
			array(
				'b02a3700be1eec9488a46b042a831646',
				'6540e6ad',
				'MWSaltedPassword',
			),
		);
	}
}
