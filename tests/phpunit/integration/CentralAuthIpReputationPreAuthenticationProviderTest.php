<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use HashBagOStuff;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CentralAuth\CentralAuthIpReputationPreAuthenticationProvider;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\Tests\Unit\Auth\AuthenticationProviderTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use MWHttpRequest;
use StatusValue;
use WANObjectCache;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthIpReputationPreAuthenticationProvider
 * @group Database
 */
class CentralAuthIpReputationPreAuthenticationProviderTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;
	use AuthenticationProviderTestTrait;

	public function testTestForAccountCreationDenyIfIPMatchButNoRisksOrTunnels() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( json_encode( [ $ip => [ 'data' ] ] ) );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
				'CentralAuthIpoidCheckAtAccountCreationLogOnly' => false,
			] ),
			null,
			$authManager
		);
		$this->assertStatusNotGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return fatal status if IP matches'
		);
	}

	public function testTestForAccountCreationMalformedData() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( 'foo' );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
			] ),
			null,
			$authManager
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return good status if malformed data'
		);
	}

	public function testTestForAccountCreationIPNotInData() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( json_encode( [ 'foo' => 'bar' ] ) );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
			] ),
			null,
			$authManager
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return good status if IP is not in returned data'
		);
	}

	public function testTestForAccountCreationTunnelType() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( json_encode( [ '1.2.3.4' => [
				'risks' => [ 'TUNNEL' ],
				'tunnels' => [ 'PROXY' ]
			] ] ) );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
				'CentralAuthIpoidDenyAccountCreationRiskTypes' => [ 'TUNNEL' ],
				'CentralAuthIpoidDenyAccountCreationTunnelTypes' => [ 'PROXY' ],
				'CentralAuthIpoidCheckAtAccountCreationLogOnly' => false,
			] ),
			null,
			$authManager
		);
		$this->assertStatusNotGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return bad status if IP is a proxy tunnel and configured to deny those types.'
		);
	}

	public function testTestForAccountCreationTunnelTypeAllowVPNIfDesired() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( json_encode( [ '1.2.3.4' => [
				'risks' => [ 'TUNNEL' ],
				'tunnels' => [ 'VPN' ]
			] ] ) );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
				'CentralAuthIpoidDenyAccountCreationRiskTypes' => [ 'TUNNEL', 'GEO_MISMATCH' ],
				'CentralAuthIpoidDenyAccountCreationTunnelTypes' => [ 'PROXY' ],
				'CentralAuthIpoidCheckAtAccountCreationLogOnly' => true,
			] ),
			null,
			$authManager
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return good status if IP is a VPN tunnel and app is configured to block only proxies.'
		);
	}

	public function testTestForAccountCreationRiskTypesConfig() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( true );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$ip = '1.2.3.4';
		$mwHttpRequest->method( 'getContent' )
			->willReturn( json_encode( [ '1.2.3.4' => [
				'risks' => [ 'TUNNEL', 'GEO_MISMATCH' ],
				'tunnels' => [ 'PROXY' ]
			] ] ) );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $ip );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
				'CentralAuthIpoidDenyAccountCreationRiskTypes' => [ 'GEO_MISMATCH' ],
				'CentralAuthIpoidDenyAccountCreationTunnelTypes' => [ 'PROXY' ],
				'CentralAuthIpoidCheckAtAccountCreationLogOnly' => false,
			] ),
			null,
			$authManager
		);
		$this->assertStatusNotGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Return bad status if IP matches configured risk types'
		);
	}

	public function testTestForAccountCreationDoNothingWithoutIpoidUrl() {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->never() )->method( 'request' );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( '127.0.0.1' );
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$httpRequestFactory,
			$this->getServiceContainer()->getMainWANObjectCache(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => null,
			] ),
			null,
			$authManager
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Do nothing if ipoid URL is not set'
		);
	}

	public function testTestForAccountCreationDoNothingWithoutFeatureFlag() {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->never() )->method( 'request' );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$httpRequestFactory,
			$this->getServiceContainer()->getMainWANObjectCache(),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => null,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
			] )
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Do nothing if feature flag is off'
		);
	}

	public function testTestForAccountCreationDoNothingIfIpoidHasNoMatch() {
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$status = new StatusValue();
		$status->setOK( false );
		$mwHttpRequest->method( 'execute' )
			->willReturn( $status );
		$this->installMockHttp( $mwHttpRequest );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );
		$provider = new CentralAuthIpReputationPreAuthenticationProvider(
			$this->getServiceContainer()->getFormatterFactory(),
			$this->getServiceContainer()->getHttpRequestFactory(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$this->getServiceContainer()->getPerDbNameStatsdDataFactory(),
			$this->getServiceContainer()->getPermissionManager()
		);
		$authManager = $this->createMock( AuthManager::class );
		$authManager->method( 'getRequest' )->willReturn( $request );
		$this->initProvider(
			$provider,
			new HashConfig( [
				'CentralAuthIpoidCheckAtAccountCreation' => true,
				'CentralAuthIpoidUrl' => 'http://localhost:6035',
				'CentralAuthIpoidRequestTimeoutSeconds' => 2,
			] ),
			null,
			$authManager
		);
		$this->assertStatusGood(
			$provider->testForAccountCreation(
				$this->createMock( User::class ),
				$this->createMock( User::class ),
				[]
			),
			'Do nothing if ipoid has no match'
		);
	}
}
