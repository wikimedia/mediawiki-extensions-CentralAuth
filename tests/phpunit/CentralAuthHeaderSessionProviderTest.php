<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers CentralAuthHeaderSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthHeaderSessionProviderTest extends CentralAuthTokenSessionProviderTestBase {
	use SessionProviderTestTrait;

	protected function newSessionProvider() {
		$config = new HashConfig( [
			MainConfigNames::SecretKey => 'hunter2',
		] );

		$services = $this->getServiceContainer();
		$hookContainer = $this->createHookContainer();

		$provider = new CentralAuthHeaderSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getSessionManager( $services ),
			CentralAuthServices::getTokenManager( $services )
		);

		$this->initProvider(
			$provider,
			null,
			$config,
			$services->getSessionManager(),
			$hookContainer,
			$services->getUserNameUtils()
		);
		return $provider;
	}

	protected function makeRequest( $token ) {
		$authorization = "CentralAuthToken $token";

		/** @var MockObject|WebRequest $request */
		$request = $this->getMockBuilder( FauxRequest::class )
			->setConstructorArgs( [] )
			->onlyMethods( [ 'getHeader' ] )
			->getMock();
		$request->method( 'getHeader' )->willReturnCallback(
			static function ( $name ) use ( $authorization ) {
				return $name === 'Authorization' ? $authorization : null;
			}
		);

		return $request;
	}

}
