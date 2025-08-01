<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Session\SessionProviderTestTrait;

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
		$request = new FauxRequest();
		$request->setHeader( 'Authorization', "CentralAuthToken $token" );
		return $request;
	}

}
