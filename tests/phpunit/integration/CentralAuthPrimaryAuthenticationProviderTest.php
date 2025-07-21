<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Extension\CentralAuth\CentralAuthPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Auth\AuthenticationProviderTestTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\TempUser\TempUserDetailsLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthPrimaryAuthenticationProvider
 */
class CentralAuthPrimaryAuthenticationProviderTest extends MediaWikiIntegrationTestCase {
	use AuthenticationProviderTestTrait;
	use TempUserTestTrait;

	/** @dataProvider provideTestUserForCreationWithTemporaryUser */
	public function testTestUserForCreationWithTemporaryUser( $expired ) {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();

		// Create a temporary user
		$user = $services->getTempUserCreator()
			->create( '~12345', new FauxRequest() )->getUser();

		// Simulate that the user is expired
		$tempUserDetailsLookup = $this->createMock( TempUserDetailsLookup::class );
		$tempUserDetailsLookup->method( 'getExpirationState' )
			->willReturn( $expired );

		$provider = new CentralAuthPrimaryAuthenticationProvider(
			$services->getReadOnlyMode(),
			$tempUserDetailsLookup,
			$services->getUserIdentityLookup(),
			CentralAuthServices::getAntiSpoofManager(),
			CentralAuthServices::getDatabaseManager(),
			CentralAuthServices::getUtilityService(),
			CentralAuthServices::getGlobalRenameRequestStore(),
			CentralAuthServices::getSharedDomainUtils()
		);
		$this->initProvider( $provider, null, null, null, null, $services->getUserNameUtils() );

		$status = $provider->testUserForCreation( $user, true );

		if ( $expired ) {
			$this->assertStatusNotGood( $status );
			$this->assertTrue( $status->hasMessage( 'centralauth-account-expired' ) );
		} else {
			$this->assertFalse( $status->hasMessage( 'centralauth-account-expired' ) );
		}
	}

	public static function provideTestUserForCreationWithTemporaryUser() {
		return [
			'Non-expired user' => [ false ],
			'Expired user' => [ true ],
		];
	}
}
