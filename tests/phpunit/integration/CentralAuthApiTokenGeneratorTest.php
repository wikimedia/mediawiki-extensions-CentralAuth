<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthApiTokenGenerator
 * @group Database
 */
class CentralAuthApiTokenGeneratorTest extends MediaWikiIntegrationTestCase {

	private function getTokenGenerator() {
		return CentralAuthServices::getApiTokenGenerator();
	}

	public function testGenerateToken() {
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();

		$centralUser = CentralAuthUser::getPrimaryInstance( $user );
		$centralUser->register( $testUser->getPassword(), null );
		$centralUser->attach( WikiMap::getCurrentWikiId() );

		$token = $this->getTokenGenerator()->getToken(
			$user,
			'someSession',
			'someWiki',
		);

		$this->assertStringContainsString( dechex( $centralUser->getId() ), $token );
	}

	public function testGenerateTokenUnregisteredUser() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot get a token for an unregistered user' );

		$this->getTokenGenerator()->getToken(
			UserIdentityValue::newAnonymous( '127.0.0.1' ),
			'someSession',
			'someWiki',
		);
	}

	public function testGenerateTokenNoCentralUser() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot get a token without an attached global user' );

		$this->getTokenGenerator()->getToken(
			$this->getTestUser()->getUser(),
			'someSession',
			'someWiki',
		);
	}
}
