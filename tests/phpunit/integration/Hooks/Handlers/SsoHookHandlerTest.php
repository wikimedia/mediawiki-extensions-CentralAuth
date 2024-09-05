<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\TemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\UsernameAuthenticationRequest;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logger\Spi;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Psr\Log\LogLevel;
use StatusValue;
use TestLogger;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\SsoHookHandler
 */
class SsoHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideAuthManagerFilterProviders
	 */
	public function testOnAuthManagerFilterProviders( $isSharedDomain, $isSul3Enabled, $expectOathProvider ) {
		// remove TestSetup override
		// FIXME once SsoHookHandler::DISALLOWED_LOCAL_PROVIDERS is configurable, reconfigure it
		//   to match TestSetup config
		$this->overrideConfigValue( MainConfigNames::AuthManagerConfig, null );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSharedDomain,
			'sul3' => $isSul3Enabled,
		] ) );
		$authManager = $this->getServiceContainer()->getAuthManager();
		static::assertThat(
			$authManager->getAuthenticationProvider( TemporaryPasswordPrimaryAuthenticationProvider::class ),
			$expectOathProvider ? static::logicalNot( static::isNull() ) : static::isNull()
		);
	}

	public function provideAuthManagerFilterProviders() {
		return [
			// shared, sul3, expect provider
			[ false, false, true ],
			[ true, false, true ],
			[ false, true, false ],
			[ true, true, true ],
		];
	}

	public function testOnAuthManagerVerifyAuthentication_singleStep() {
		$oldConfig = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::AuthManagerAutoConfig );
		$primaryConfig = [
			'getUniqueId' => 'mockPrimary',
			'testUserForCreation' => StatusValue::newGood(),
			'getAuthenticationRequests' => [],
			'beginPrimaryAuthentication' => AuthenticationResponse::newPass( 'UTDummy' ),
		];
		$this->overrideConfigValue( MainConfigNames::AuthManagerConfig, [
			'preauth' => [
				'CentralAuthSsoPreAuthenticationProvider'
				=> $oldConfig['preauth']['CentralAuthSsoPreAuthenticationProvider'],
			],
			'primaryauth' => [
				'mockPrimary' => [ 'factory' => function () use ( &$primaryConfig ) {
					return $this->createConfiguredMock( AbstractPrimaryAuthenticationProvider::class, $primaryConfig );
				} ],
			],
			'secondaryauth' => [],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => false,
			'sul3' => true,
		] ) );
		$logProvider = $this->getLogProvider();
		LoggerFactory::registerProvider( $logProvider );

		$authManager = $this->getServiceContainer()->getAuthManager();
		$response = $authManager->beginAuthentication( [], 'http://example.com' );
		$this->assertSame( AuthenticationResponse::FAIL, $response->status );
		$this->assertSame( 'internalerror', $response->message->getKey() );
		$this->assertLogged( $logProvider, 'exception', LogLevel::ERROR,
			'Providers were filtered but redirecting provider was not the primary' );
	}

	public function testOnAuthManagerVerifyAuthentication_multiStep() {
		$oldConfig = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::AuthManagerAutoConfig );
		$primaryConfig = [
			'getUniqueId' => 'mockPrimary',
			'testUserForCreation' => StatusValue::newGood(),
			'getAuthenticationRequests' => [],
			'beginPrimaryAuthentication'
				=> AuthenticationResponse::newUI( [ new UsernameAuthenticationRequest() ], wfMessage( 'test' ) ),
			'continuePrimaryAuthentication' => AuthenticationResponse::newPass( 'UTDummy2' ),
		];
		$this->overrideConfigValue( MainConfigNames::AuthManagerConfig, [
			'preauth' => [
				'CentralAuthSsoPreAuthenticationProvider'
				=> $oldConfig['preauth']['CentralAuthSsoPreAuthenticationProvider'],
			],
			'primaryauth' => [
				'mockPrimary' => [ 'factory' => function () use ( &$primaryConfig ) {
					return $this->createConfiguredMock( AbstractPrimaryAuthenticationProvider::class, $primaryConfig );
				} ],
			],
			'secondaryauth' => [],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => false,
			'sul3' => true,
		] ) );
		$logProvider = $this->getLogProvider();
		LoggerFactory::registerProvider( $logProvider );

		$authManager = $this->getServiceContainer()->getAuthManager();
		$response = $authManager->beginAuthentication( [], 'http://example.com' );
		$this->assertSame( AuthenticationResponse::UI, $response->status );

		$this->getServiceContainer()->get( 'CentralAuth.FilteredRequestTracker' )->reset();
		// attacker disables SUL3 flag for second step of login
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => false,
			'sul3' => false,
		] ) );

		$response = $authManager->continueAuthentication( [ new UsernameAuthenticationRequest() ] );
		$this->assertSame( AuthenticationResponse::FAIL, $response->status );
		$this->assertSame( 'internalerror', $response->message->getKey() );
		$this->assertLogged( $logProvider, 'exception', LogLevel::ERROR,
			'Providers were filtered but redirecting provider was not the primary' );
	}

	private function getSharedDomainUtils( array $config ): SharedDomainUtils {
		$isSharedDomain = $config['shared'];
		$isSul3Enabled = $config['sul3'];
		$this->overrideConfigValue( 'CentralAuthRestrictSsoDomain', true );
		$sharedDomainUtils = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getTitleFactory(),
				$this->getServiceContainer()->get( "MobileFrontend.Context" ),
			] )
			->onlyMethods( [ 'isSharedDomain', 'isSul3Enabled' ] )
			->getMock();
		$sharedDomainUtils->method( 'isSharedDomain' )->willReturn( $isSharedDomain );
		$sharedDomainUtils->method( 'isSul3Enabled' )->willReturn( $isSul3Enabled );
		return $sharedDomainUtils;
	}

	private function getLogProvider(): Spi {
		return new class() implements Spi {

			public $logs = [];

			public function getLogger( $channel ) {
				$this->logs[$channel] ??= new TestLogger( true );
				return $this->logs[$channel];
			}
		};
	}

	private function assertLogged(
		Spi $logProvider,
		string $channel,
		string $level,
		string $expectedLogMessage
	) {
		$logs = $logProvider->getLogger( $channel )->getBuffer();
		foreach ( $logs as $log ) {
			if ( $log[0] === $level && strpos( $log[1], $expectedLogMessage ) !== false ) {
				return;
			}
		}
		$this->fail( "Expected log message '$expectedLogMessage' not found in '$channel' logs; actual:\n"
			. print_r( $logs, true ) );
	}

}
