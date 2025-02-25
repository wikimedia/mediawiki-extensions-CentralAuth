<?php

use MediaWiki\Extension\CentralAuth\CentralAuthUserCache;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthUserCache
 */
class CentralAuthUserCacheTest extends MediaWikiUnitTestCase {

	/**
	 * @param string $name
	 * @param bool $fromPrimary
	 * @return CentralAuthUser
	 */
	private function getMockUser( $name, $fromPrimary = false ) {
		$mock = $this->createMock( CentralAuthUser::class );
		$mock->method( 'getName' )->willReturn( $name );
		$mock->method( 'isFromPrimary' )->willReturn( $fromPrimary );
		return $mock;
	}

	private function getCache() {
		return new CentralAuthUserCache();
	}

	public function testSetGet() {
		$cache = $this->getCache();
		$user1 = $this->getMockUser( 'User1' );
		$cache->set( $user1 );
		$user2 = $this->getMockUser( 'User2' );
		$cache->set( $user2 );
		$this->assertSame( $user1, $cache->get( 'User1' ) );
		$this->assertSame( $user2, $cache->get( 'User2' ) );
		$this->assertNull( $cache->get( 'User3' ) );
	}

	public function testSetGetPrimary() {
		$cache = $this->getCache();
		$user1 = $this->getMockUser( 'User1', true );
		$cache->set( $user1 );
		$user2 = $this->getMockUser( 'User2' );
		$cache->set( $user2 );
		$this->assertSame( $user1, $cache->get( 'User1' ) );
		$this->assertSame( $user1, $cache->get( 'User1', true ) );
		$this->assertSame( $user2, $cache->get( 'User2' ) );
		$this->assertNull( $cache->get( 'User2', true ) );
	}

	public function testClear() {
		$cache = $this->getCache();
		$user1 = $this->getMockUser( 'User1', true );
		$cache->set( $user1 );
		$cache->clear();
		$this->assertNull( $cache->get( 'User1' ) );
	}

	public function testDelete() {
		$cache = $this->getCache();
		$user1 = $this->getMockUser( 'User1', true );
		$cache->set( $user1 );
		$user2 = $this->getMockUser( 'User2' );
		$cache->set( $user2 );
		$this->assertSame( $user1, $cache->get( 'User1' ) );
		$cache->delete( 'User1' );
		$cache->delete( 'User3' );
		$this->assertNull( $cache->get( 'User1' ) );
		$this->assertSame( $user2, $cache->get( 'User2' ) );
	}
}
