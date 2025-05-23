<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

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

		$logger = new NullLogger();

		$services = $this->getServiceContainer();
		$hookContainer = $services->getHookContainer();

		$manager = new SessionManager( [
			'config' => $config,
			'logger' => $logger,
			'store' => $this->sessionStore,
			'hookContainer' => $hookContainer
		] );

		$provider = new CentralAuthHeaderSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getSessionManager( $services ),
			CentralAuthServices::getTokenManager( $services )
		);
		$this->initProvider(
			$provider, null, $config, $manager, $hookContainer, $services->getUserNameUtils()
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
