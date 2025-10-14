<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use MWCryptRand;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthSessionManager
 * @group Database
 */
class CentralAuthSessionManagerTest extends MediaWikiIntegrationTestCase {

	public function testNoCentralSession() {
		$request = new FauxRequest();
		$session = $this->getServiceContainer()->getSessionManager()->getEmptySession( $request );
		$centralSessionManager = CentralAuthServices::getSessionManager( $this->getServiceContainer() );

		$centralSession = $centralSessionManager->getCentralSession( $session );
		$this->assertSame( [], $centralSession );
	}

	public function testCentralSession() {
		ConvertibleTimestamp::setFakeTime( '2025-01-01 00:00:00' );
		$request = new FauxRequest();
		$session = $this->getServiceContainer()->getSessionManager()->getEmptySession( $request );
		$centralSessionManager = CentralAuthServices::getSessionManager( $this->getServiceContainer() );

		$centralSessionId = $centralSessionManager->setCentralSession( [ 'foo' => 'bar' ], false, $session );
		$centralSession = $centralSessionManager->getCentralSessionById( $centralSessionId );
		$this->assertNotEmpty( $centralSession );
		$this->assertSame( 'bar', $centralSession['foo'] );
		$this->assertSame( $centralSessionId, $centralSession['sessionId'] );
		$this->assertSame( ConvertibleTimestamp::time() + BagOStuff::TTL_DAY, $centralSession['expiry'] );
		$this->assertSame( $centralSessionId, $session->get( 'CentralAuth::centralSessionId' ) );

		$centralSession2 = $centralSessionManager->getCentralSession( $session );
		$this->assertSame( $centralSession, $centralSession2 );

		$centralSessionId2 = $centralSessionManager->setCentralSession( [ 'foo2' => 'bar2' ], false, $session );
		$centralSession2 = $centralSessionManager->getCentralSessionById( $centralSessionId );
		$this->assertSame( $centralSessionId, $centralSessionId2 );
		$this->assertArrayNotHasKey( 'foo', $centralSession2 );
		$this->assertSame( 'bar2', $centralSession2['foo2'] );

		$centralSessionId2 = $centralSessionManager->setCentralSession( [], true, $session );
		$this->assertNotSame( $centralSessionId, $centralSessionId2 );
		$this->assertSame( $centralSessionId2, $session->get( 'CentralAuth::centralSessionId' ) );

		$newId = MWCryptRand::generateHex( 32 );
		$centralSessionId3 = $centralSessionManager->setCentralSession( [], $newId, $session );
		$this->assertSame( $newId, $centralSessionId3 );
	}

	public function testCentralSession_refresh() {
		ConvertibleTimestamp::setFakeTime( '2025-01-01 00:00:00' );
		$sessionCreationTime = ConvertibleTimestamp::time();
		$request = new FauxRequest();
		$session = $this->getServiceContainer()->getSessionManager()->getEmptySession( $request );
		$centralSessionManager = CentralAuthServices::getSessionManager( $this->getServiceContainer() );

		$centralSessionId = $centralSessionManager->setCentralSession( [], false, $session );
		$centralSession = $centralSessionManager->getCentralSessionById( $centralSessionId );
		$this->assertSame( $sessionCreationTime + BagOStuff::TTL_DAY, $centralSession['expiry'] );

		// 1h later - no refresh
		ConvertibleTimestamp::setFakeTime( '2025-01-01 01:00:00' );
		$centralSessionManager->setCentralSession( [], false, $session );
		$centralSession = $centralSessionManager->getCentralSessionById( $centralSessionId );
		$this->assertSame( $sessionCreationTime + BagOStuff::TTL_DAY, $centralSession['expiry'] );

		// 20h later - refresh
		ConvertibleTimestamp::setFakeTime( '2025-01-01 20:00:00' );
		$centralSessionManager->setCentralSession( [], false, $session );
		$centralSession = $centralSessionManager->getCentralSessionById( $centralSessionId );
		$this->assertNotSame( $sessionCreationTime + BagOStuff::TTL_DAY, $centralSession['expiry'] );
		$this->assertSame( ConvertibleTimestamp::time() + BagOStuff::TTL_DAY, $centralSession['expiry'] );
	}

}
