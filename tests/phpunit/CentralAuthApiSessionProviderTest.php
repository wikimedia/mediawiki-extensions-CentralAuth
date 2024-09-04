<?php

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\Session\SessionProviderTestTrait;
use Psr\Log\NullLogger;

/**
 * @covers CentralAuthApiSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthApiSessionProviderTest extends CentralAuthTokenSessionProviderTestBase {
	use SessionProviderTestTrait;

	/** @var \MediaWiki\HookContainer\HookContainer */
	private $hookContainer;

	public function setUp(): void {
		parent::setUp();

		if ( !defined( 'MW_API' ) ) {
			define( 'MW_API', 'TEST' );
		}

		$this->hookContainer = $this->createHookContainer();
	}

	protected function makeRequest( $token ) {
		return new FauxRequest( [ 'centralauthtoken' => $token ] );
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		string $error = null,
		string $code = null
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
			'CentralAuthTokenSessionTimeout' => 0
		] );

		$logger = new NullLogger();

		$manager = new SessionManager( [
			'config' => $config,
			'logger' => $logger,
			'store' => $this->sessionStore,
			'hookContainer' => $this->hookContainer
		] );

		$services = $this->getServiceContainer();

		$provider = new CentralAuthApiSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getSessionManager( $services ),
			CentralAuthServices::getTokenManager( $services )
		);

		$this->initProvider(
			$provider, null, $config, $manager, $this->hookContainer, $services->getUserNameUtils()
		);
		return $provider;
	}

	/**
	 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\ApiHookHandler::onAPIGetAllowedParams
	 */
	public function testApiParameterDeclared() {
		// hook is registered dynamically when creating the SessionProvider
		$this->newSessionProvider();

		$this->overrideConfigValue( 'CentralAuthCookies', true );

		$main = new ApiMain();

		$params = $main->getFinalParams();
		$this->assertArrayHasKey( 'centralauthtoken', $params );
	}

}
