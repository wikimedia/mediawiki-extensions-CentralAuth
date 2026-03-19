<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use InvalidArgumentException;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Json\JwtCodec;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthApiTokenManager
 * @group Database
 */
class CentralAuthApiTokenManagerTest extends MediaWikiIntegrationTestCase {

	private function getTokenManager() {
		return CentralAuthServices::getApiTokenManager();
	}

	public function testGenerateToken() {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		$centralUser->register( $testUser->getPassword(), null );
		$centralUser->attach( WikiMap::getCurrentWikiId() );

		$token = $this->getTokenManager()->getToken(
			$user,
			'someSession',
			'someWiki',
		);

		$this->assertStringContainsString( dechex( $centralUser->getId() ), $token );
	}

	/** @covers \MediaWiki\Extension\CentralAuth\CentralAuthApiTokenManager::wrapTokenInJwt */
	public function testGenerateTokenJwt() {
		$jwtCodec = $this->createMock( JwtCodec::class );
		$jwtCodec->expects( $this->once() )->method( 'create' )->willReturn( 'FAKE.JWT' );
		$jwtCodec->method( 'isEnabled' )->willReturn( true );
		$this->setService( 'JwtCodec', $jwtCodec );

		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'examplewiki' => 'https://example.org',
			],
			'wgArticlePath' => [
				'examplewiki' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );

		$this->overrideConfigValues( [
			MainConfigNames::UseSessionCookieJwt => true,
			CAMainConfigNames::CentralAuthCentralWiki => 'examplewiki',
		] );

		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		$centralUser->register( $testUser->getPassword(), null );
		$centralUser->attach( WikiMap::getCurrentWikiId() );

		$token = $this->getTokenManager()->getToken(
			$user,
			'someSession',
			'someWiki',
		);

		$this->assertEquals( 'FAKE.JWT', $token );
	}

	public function testGenerateTokenUnregisteredUser() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot get a token for an unregistered user' );

		$this->getTokenManager()->getToken(
			UserIdentityValue::newAnonymous( '127.0.0.1' ),
			'someSession',
			'someWiki',
		);
	}

	public function testGenerateTokenNoCentralUser() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot get a token without an attached global user' );

		$this->getTokenManager()->getToken(
			$this->getTestUser()->getUser(),
			'someSession',
			'someWiki',
		);
	}
}
