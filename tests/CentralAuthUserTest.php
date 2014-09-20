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
}
