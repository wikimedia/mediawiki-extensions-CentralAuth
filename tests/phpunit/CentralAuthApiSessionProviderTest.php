<?php

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Tests\Session\SessionProviderTestTrait;

/**
 * @covers CentralAuthApiSessionProvider
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\ApiHookHandler
 * @group medium
 * @group Database
 */
class CentralAuthApiSessionProviderTest extends CentralAuthTokenSessionProviderTestBase {
	use SessionProviderTestTrait;

	/** @var \MediaWiki\HookContainer\HookContainer */
	private $hookContainer;

	public function setUp(): void {
		parent::setUp();

		$this->hookContainer = $this->createHookContainer();
	}

	protected function makeRequest( $token ) {
		return new FauxRequest( [ 'centralauthtoken' => $token ] );
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		?string $error = null,
		?string $code = null
	) {
		parent::assertSessionInfoError( $request, $result, $error, $code );
		$this->assertErrorFromApiBeforeMain( $request, $error );
	}

	private function assertErrorFromApiBeforeMain( WebRequest $request, $error ) {
		$context = new RequestContext();
		$context->setRequest( $request );
		$processor = new ApiMain( RequestContext::getMain(), true );

		try {
			( new HookRunner( $this->hookContainer ) )->onApiBeforeMain( $processor );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $ex ) {
			$this->assertStatusError( $error, $ex->getStatusValue() );
		}
	}

	protected function newSessionProvider() {
		$config = new HashConfig( [
			MainConfigNames::SecretKey => 'hunter2',
		] );

		$services = $this->getServiceContainer();

		$provider = new CentralAuthApiSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getSessionManager( $services ),
			CentralAuthServices::getTokenManager( $services )
		);

		$this->initProvider(
			$provider,
			null,
			$config,
			$services->getSessionManager(),
			$this->hookContainer,
			$services->getUserNameUtils()
		);
		return $provider;
	}

	public function testApiParameterDeclared() {
		// hook is registered dynamically when creating the SessionProvider
		$this->newSessionProvider();

		$this->overrideConfigValue( CAMainConfigNames::CentralAuthCookies, true );

		$main = new ApiMain();

		$params = $main->getFinalParams();
		$this->assertArrayHasKey( 'centralauthtoken', $params );
	}

	public function testOptionsRequestReuseToken() {
		$user = $this->makeCentralAuthUser( 'Frank' );
		$token = $this->makeValidToken( [ 'userName' => $user->getName() ] );
		$provider = $this->newSessionProvider();

		$request = new class( [ 'centralauthtoken' => $token ] ) extends FauxRequest {

			public function getMethod() {
				return 'OPTIONS';
			}
		};

		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoOk( $result );

		// The token should not have been consumed by the OPTIONS request,
		// and using it again should succeed
		$result = $provider->provideSessionInfo( $request );
		$this->assertSessionInfoOk( $result );
	}

}
