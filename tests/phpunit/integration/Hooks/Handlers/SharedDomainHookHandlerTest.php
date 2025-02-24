<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\LocalPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\UsernameAuthenticationRequest;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthSharedDomainPreAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\CentralAuthTemporaryPasswordPrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logger\Spi;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\MockWikiMapTrait;
use MWExceptionRenderer;
use PermissionsError;
use Psr\Log\LogLevel;
use StatusValue;
use TestLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\SharedDomainHookHandler
 */
class SharedDomainHookHandlerTest extends ApiTestCase {
	use MockWikiMapTrait;

	public function provideSharedDomainRestrictions() {
		return [
			// is SUL3 shared domain, should restrict
			[ false, true ],
			[ true, false ],
		];
	}

	/**
	 * @dataProvider provideSharedDomainRestrictions
	 */
	public function testOnGetUserPermissionsErrors( $isSul3SharedDomain, $allowSpecialPages ) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthRestrictSharedDomain => true,
			CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions => [
				'allowedSpecialPages' => [ 'Foo' ],
			],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSul3SharedDomain,
			'sul3' => $isSul3SharedDomain,
		] ) );
		$specialBlankTitle = $this->getServiceContainer()->getTitleFactory()
			->newFromText( 'Special:BlankPage' );
		$specialFooTitle = $this->getServiceContainer()->getTitleFactory()
			->newFromText( 'Special:Foo' );
		$this->assertSame( $allowSpecialPages,
			$this->getTestUser()->getAuthority()->probablyCan( 'read', $specialBlankTitle ) );
		$this->assertSame( $allowSpecialPages,
			$this->getTestUser()->getAuthority()->probablyCan( 'read', $specialFooTitle ) );
	}

	/**
	 * @dataProvider provideSharedDomainRestrictions
	 */
	public function testOnApiCheckCanExecute( $isSul3SharedDomain, $allowApiModules ) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthRestrictSharedDomain => true,
			CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions => [
				'allowedActionApiModules' => [ 'parse' ],
			],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSul3SharedDomain,
			'sul3' => $isSul3SharedDomain,
		] ) );

		$exception = null;
		try {
			$this->doApiRequest( [ 'action' => 'watch' ], null, false,
				$this->getTestUser()->getUser(), 'watch' );
		} catch ( ApiUsageException $e ) {
			$exception = $e;
		}
		if ( $allowApiModules ) {
			$this->assertSame( null, $exception );
		} else {
			$this->assertInstanceOf( ApiUsageException::class, $exception );
			$this->assertSame( 'The "watch" module has been disabled.', $exception->getMessage() );
		}

		// on default allowlist, always allowed
		$this->doApiRequest( [ 'action' => 'paraminfo' ] );

		// on custom allowlist, always allowed
		$this->doApiRequest( [ 'action' => 'parse', 'text' => '', 'contentmodel' => 'wikitext' ] );
	}

	/**
	 * @dataProvider provideSharedDomainRestrictions
	 */
	public function testOnApiQueryCheckCanExecute( $isSul3SharedDomain, $allowApiModules ) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthRestrictSharedDomain => true,
			CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions => [
				'allowedActionApiModules' => [ 'query+globalrenamestatus' ],
			],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSul3SharedDomain,
			'sul3' => $isSul3SharedDomain,
		] ) );

		$exception = null;
		try {
			$fauxContext = new DerivativeContext( RequestContext::getMain() );
			$fauxRequest = new FauxRequest( [ 'action' => 'query', 'meta' => 'allmessages', 'ammessages' => 'xxx' ] );
			$fauxContext->setRequest( $fauxRequest );
			$api = new ApiMain( $fauxContext, false, true );
			$api->execute();
		} catch ( ApiUsageException $e ) {
			$exception = $e;
		}
		if ( $allowApiModules ) {
			$this->assertSame( null, $exception );
		} else {
			$this->assertInstanceOf( ApiUsageException::class, $exception );
			$this->assertSame( 'The "query+allmessages" module has been disabled.', $exception->getMessage() );
		}

		// on default allowlist, always allowed
		$fauxContext = new DerivativeContext( RequestContext::getMain() );
		$fauxRequest = new FauxRequest( [ 'action' => 'query', 'meta' => 'tokens' ] );
		$fauxContext->setRequest( $fauxRequest );
		$api = new ApiMain( $fauxContext, false, true );
		$api->execute();

		// on custom allowlist, always allowed
		$fauxContext = new DerivativeContext( RequestContext::getMain() );
		$fauxRequest = new FauxRequest( [ 'action' => 'query', 'meta' => 'globalrenamestatus' ] );
		$fauxContext->setRequest( $fauxRequest );
		$api = new ApiMain( $fauxContext, false, true );
		$api->execute();
	}

	/**
	 * @dataProvider provideAuthManagerFilterProviders
	 */
	public function testOnAuthManagerFilterProviders( $isSharedDomain, $isSul3Enabled, $expectLocalPwdProvider ) {
		// Rather than having to keep track of LocalPasswordPrimaryAuthenticationProvider's
		// dependencies, get if from the TestSetup.php config
		$testSetupAuthManagerConfig = $GLOBALS['wgAuthManagerConfig'];
		$tmp = array_filter( $testSetupAuthManagerConfig['primaryauth'],
			static fn ( $cnf ) => $cnf['class'] === LocalPasswordPrimaryAuthenticationProvider::class );
		$localPwdProviderConfig = reset( $tmp );
		$this->overrideConfigValues( [
			MainConfigNames::AuthManagerConfig => [
				'preauth' => [
					'CentralAuthSharedDomainPreAuthenticationProvider' => [
						'class' => CentralAuthSharedDomainPreAuthenticationProvider::class,
						'services' => [
							'CentralAuth.FilteredRequestTracker',
						],
					],
				],
				'primaryauth' => [
					LocalPasswordPrimaryAuthenticationProvider::class => $localPwdProviderConfig,
					'CentralAuthTemporaryPasswordPrimaryAuthenticationProvider' => [
						'class' => CentralAuthTemporaryPasswordPrimaryAuthenticationProvider::class,
						'services' => [
							'ConnectionProvider',
							'Emailer',
							'LanguageNameUtils',
							'UserIdentityLookup',
							'UserOptionsLookup',
							'CentralAuth.CentralAuthDatabaseManager',
							'CentralAuth.CentralAuthUtilityService',
							'CentralAuth.SharedDomainUtils'
						],
						'args' => [ [
							'authoritative' => true,
						] ],
					],
				],
				'secondaryauth' => [],
			],
			CAMainConfigNames::CentralAuthStrict => true,
			CAMainConfigNames::CentralAuthSul3SharedDomainRestrictions => [
				'disallowedLocalProviders' => [
					'primaryauth' => [
						'CentralAuthTemporaryPasswordPrimaryAuthenticationProvider',
					],
				],
			],
		] );
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSharedDomain,
			'sul3' => $isSul3Enabled,
		] ) );
		$authManager = $this->getServiceContainer()->getAuthManager();
		// Check SharedDomainHookHandler defaults
		static::assertThat(
			$authManager->getAuthenticationProvider( LocalPasswordPrimaryAuthenticationProvider::class ),
			$expectLocalPwdProvider ? static::logicalNot( static::isNull() ) : static::isNull()
		);
		// Check $wgCentralAuthSul3SharedDomainRestrictions
		static::assertThat(
			$authManager->getAuthenticationProvider( CentralAuthTemporaryPasswordPrimaryAuthenticationProvider::class ),
			$expectLocalPwdProvider ? static::logicalNot( static::isNull() ) : static::isNull()
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
				'CentralAuthSharedDomainPreAuthenticationProvider'
				=> $oldConfig['preauth']['CentralAuthSharedDomainPreAuthenticationProvider'],
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
				'CentralAuthSharedDomainPreAuthenticationProvider'
				=> $oldConfig['preauth']['CentralAuthSharedDomainPreAuthenticationProvider'],
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

	/** @dataProvider provideSharedDomainRestrictions */
	public function testOnBeforePageDisplay( $isSul3SharedDomain, $shouldLoadSiteModule ) {
		$exceptionRenderer = TestingAccessWrapper::newFromClass( MWExceptionRenderer::class );
		// Need to set a title to make MWExceptionRenderer::reportHTML() use OutputPage
		RequestContext::getMain()->setTitle( $this->getServiceContainer()->getTitleFactory()->newFromText( 'Test' ) );
		// The test doesn't involve authentication but OutputPage calls the skin, the skin calls
		// AuthManager::canCreateAccounts() which loads providers, and SharedDomainHookHandler::onAuthManagerFilterProviders()
		// will error if it does not see CentralAuthSharedDomainPreAuthenticationProvider, which it wouldn't because
		// by default integration tests use a minimal provider configuration.
		$oldConfig = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::AuthManagerAutoConfig );
		$this->overrideConfigValue( MainConfigNames::AuthManagerConfig, [
			'preauth' => [
				'CentralAuthSharedDomainPreAuthenticationProvider'
					=> $oldConfig['preauth']['CentralAuthSharedDomainPreAuthenticationProvider'],
			],
			'primaryauth' => [],
			'secondaryauth' => [],
		] );
		$this->mockWikiMap();
		$this->setService( 'CentralAuth.SharedDomainUtils', $this->getSharedDomainUtils( [
			'shared' => $isSul3SharedDomain,
			'sul3' => $isSul3SharedDomain,
		] ) );

		$error = new PermissionsError( 'read' );
		ob_start();
		$exceptionRenderer->reportHTML( $error );
		ob_get_clean();
		static::assertThat( RequestContext::getMain()->getOutput()->getModules( true ),
			$shouldLoadSiteModule
				? static::containsIdentical( 'site' )
				: static::logicalNot( static::containsIdentical( 'site' ) )
		);
	}

	private function getSharedDomainUtils( array $config ): SharedDomainUtils {
		$isSharedDomain = $config['shared'];
		$isSul3Enabled = $config['sul3'];
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthRestrictSharedDomain, true );
		$sharedDomainUtils = $this->getMockBuilder( SharedDomainUtils::class )
			->setConstructorArgs( [
				$this->getServiceContainer()->getMainConfig(),
				$this->getServiceContainer()->getTitleFactory(),
				fn () => $this->getServiceContainer()->getUserOptionsManager(),
				new HookRunner( $this->getServiceContainer()->getHookContainer() ),
				$this->getServiceContainer()->get( "MobileFrontend.Context" ),
				false,
				$this->getServiceContainer()->getTempUserConfig()
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
