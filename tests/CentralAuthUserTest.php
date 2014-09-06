<?php

/**
 * Only for tests that do not require the database to be
 * set up.
 *
 * @group CentralAuth
 */
class CentralAuthUserTest extends MediaWikiTestCase {

	/**
	 * @covers CentralAuthUser::newUnattached
	 */
	public function testNewUnattached() {
		$ca = CentralAuthUser::newUnattached( 'FooBar' );
		$this->assertInstanceOf( 'CentralAuthUser', $ca );
		$this->assertEquals( 'FooBar', $ca->getName() );
		$this->assertFalse( $ca->isAttached() );
	}
}