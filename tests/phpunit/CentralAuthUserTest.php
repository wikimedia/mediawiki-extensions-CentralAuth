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
			array( array( 'foowiki' => array( 'attachedMethod' => 'password' ) ), 'foowiki' ),
			array(
				array(
					'foowiki' => array( 'attachedMethod' => 'primary', 'editCount' => 4 ),
					'barwiki' => array( 'attachedMethod' => 'password', 'editCount' => 6 )
				),
				'foowiki' // Primary account "wins" over edit count
			),
			array(
				array(
					'foowiki' => array( 'attachedMethod' => 'password', 'editCount' => 4 ),
					'barwiki' => array( 'attachedMethod' => 'password', 'editCount' => 6 )
				),
				'barwiki'
			)
		);
	}

	/**
	 * @covers CentralAuthUser::chooseHomeWiki
	 * @dataProvider provideChooseHomeWiki
	 */
	public function testChooseHomeWiki( $expected, $attached ) {
		$ca = new CentralAuthUser( 'FooBar' );
		$this->assertEquals( $expected, $ca->chooseHomeWiki( $attached ) );
	}

	public static function provideChooseHomeWiki() {
		return array(
			// Groups win
			array( 'barwiki', array(
				'foowiki' => array(
					'groups' => array( 'sysop' ),
				),
				'barwiki' => array(
					'groups' => array( 'checkuser' ),
				),
			) ),
			// Groups tie, editcount wins
			array( 'barwiki', array(
				'foowiki' => array(
					'groups' => array( 'sysop', 'checkuser' ),
					'editCount' => '100',
				),
				'barwiki' => array(
					'groups' => array( 'checkuser' ),
					'editCount' => '100000000',
				),
			) ),
			// No groups, Editcount wins
			array( 'barwiki', array(
				'foowiki' => array(
					'groups' => array(),
					'editCount' => '100'
				),
				'barwiki' => array(
					'groups' => array(),
					'editCount' => '1000'
				),
			) ),
			// Edit count ties, super old registration (null) wins
			array( 'foowiki', array(
				'foowiki' => array(
					'groups' => array(),
					'editCount' => '5',
					'registration' => null
				),
				'barwiki' => array(
					'groups' => array(),
					'editCount' => '5',
					'registration' => '20150305220251',
				),
			) ),
			// Edit count ties, registration wins
			array( 'foowiki', array(
				'foowiki' => array(
					'groups' => array(),
					'editCount' => '5',
					'registration' => '20100305220251'
				),
				'barwiki' => array(
					'groups' => array(),
					'editCount' => '5',
					'registration' => '20150305220251',
				),
			) )

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

	/**
	 * @covers CentralAuthUser::getLocalGroups
	 * @dataProvider provideOnPasswordPoliciesForUser
	 */
	public function testGetLocalGroups( $attached, $expected ) {

		/** @var PHPUnit_Framework_MockObject_MockObject|CentralAuthUser $ca */
		$ca = $this->getMockBuilder( 'CentralAuthUser' )
			->disableOriginalConstructor()
			->setMethods( array( 'queryAttached' ) )
			->getMock();
		$ca->expects( $this->any() )->method( 'queryAttached' )->will( $this->returnValue( $attached ) );

		$this->assertEquals( $expected, $ca->getLocalGroups() );
	}

	public function provideOnPasswordPoliciesForUser() {
		return array(
			array(
				array(
					'enwiki' => array(
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183725',
						'attachedMethod' => 'login',
						'id' => '1234',
						'groups' => array(),
					),
					'commonswiki' => array(
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183726',
						'attachedMethod' => 'login',
						'id' => '4321',
						'groups' => array(),
					),
				),
				array()
			),
			array(
				array(
					'enwiki' => array(
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183727',
						'attachedMethod' => 'login',
						'id' => '12345',
						'groups' => array( 'sysop' ),
					),
					'commonswiki' => array(
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183728',
						'attachedMethod' => 'login',
						'id' => '54321',
						'groups' => array( 'sysop' ),
					),
				),
				array( 'sysop' )
			),
			array(
				array(
					'enwiki' => array(
						'wiki' => 'enwiki',
						'attachedTimestamp' => '20130627183729',
						'attachedMethod' => 'login',
						'id' => '123456',
						'groups' => array( 'bureaucrat' ),
					),
					'commonswiki' => array(
						'wiki' => 'commonswiki',
						'attachedTimestamp' => '20130627183720',
						'attachedMethod' => 'login',
						'id' => '654321',
						'groups' => array(),
					),
				),
				array( 'bureaucrat' )
			),
		);
	}

}
