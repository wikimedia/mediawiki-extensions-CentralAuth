<?php

use MediaWiki\Session\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers CentralAuthHeaderSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthHeaderSessionProviderTest extends CentralAuthTokenSessionProviderTest {

	protected function newSessionProvider() {
		$config = new HashConfig( [
			'SecretKey' => 'hunter2',
		] );

		$logger = new NullLogger();

		$hookContainer = $this->getServiceContainer()->getHookContainer();

		$manager = new SessionManager( [
			'config' => $config,
			'logger' => $logger,
			'store' => $this->sessionStore,
			'hookContainer' => $hookContainer
		] );

		$provider = new CentralAuthHeaderSessionProvider();
		$provider->init(
			$logger,
			$config,
			$manager,
			$hookContainer,
			$this->getServiceContainer()->getUserNameUtils()
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
