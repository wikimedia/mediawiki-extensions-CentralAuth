<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthUserDBTest extends CentralAuthDBTest {

	/**
	 * @covers CentralAuthUser::exists
	 * @covers CentralAuthUser::getId
	 * @covers CentralAuthUser::getHomeWiki
	 * @covers CentralAuthUser::getStateHash
	 */
	public function testBasicAttrs() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertEquals( true, $caUser->exists() );
		$this->assertEquals( 1001, $caUser->getId() );
		$this->assertEquals( wfWikiID(), $caUser->getHomeWiki() );
		$this->assertEquals( false, $caUser->isLocked() );
		$this->assertEquals(
			CentralAuthUser::HIDDEN_NONE,
			$caUser->getHiddenLevel()
		);
		$this->assertEquals(
			'2234d7949459185926a50073d174b673',
			$caUser->getStateHash()
		);
	}

	/**
	 * @covers CentralAuthUser::getAuthToken
	 * @covers CentralAuthUser::resetAuthToken
	 */
	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertEquals(
			32,
			strlen( $token )
		);
		$this->assertEquals(
			0,
			preg_match( '/[^a-f0-9]/', $token )
		);
	}

}
