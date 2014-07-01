<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthUserDBTest extends CentralAuthDBTest {


	public function testBasicAttrs() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertEquals( $caUser->exists(), true );
		$this->assertEquals( $caUser->getId(), 1001 );
		$this->assertEquals( $caUser->getHomeWiki(), wfWikiID() );
		$this->assertEquals( $caUser->isLocked(), false );
		$this->assertEquals(
			$caUser->getHiddenLevel(),
			CentralAuthUser::HIDDEN_NONE
		);
		$this->assertEquals(
			$caUser->getStateHash(),
			'2234d7949459185926a50073d174b673'
		);
	}

	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertEquals(
			strlen( $token ),
			32
		);
		$this->assertEquals(
			preg_match( '/[^a-f0-9]/', $token ),
			0
		);
	}

}
