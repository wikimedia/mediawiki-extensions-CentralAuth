<?php

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiMessage;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionInfo;

/**
 * @covers \CentralAuthApiSessionProvider
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\ApiHookHandler
 * @group medium
 * @group Database
 */
class CentralAuthApiSessionProviderTest extends CentralAuthTokenSessionProviderTestBase {

	protected function newSessionProviderClass(): CentralAuthApiSessionProvider {
		$services = $this->getServiceContainer();
		return new CentralAuthApiSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getApiTokenManager( $services ),
			CentralAuthServices::getSessionManager( $services ),
		);
	}

	protected function makeRequest( $token ) {
		return new FauxRequest( [ 'centralauthtoken' => $token, 'origin' => '*' ] );
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		?string $error = null,
		?string $code = null
	) {
		parent::assertSessionInfoError( $request, $result, $error, $code );
		$this->assertErrorFromApiBeforeMain( $request, $error, $code );
	}

	private function assertErrorFromApiBeforeMain( WebRequest $request, $error, $code ) {
		$context = new RequestContext();
		$context->setRequest( $request );
		$processor = new ApiMain( $request, true );

		try {
			( new HookRunner( $this->hookContainer ) )->onApiBeforeMain( $processor );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( ApiUsageException $ex ) {
			$this->assertStatusError( $error, $ex->getStatusValue() );
			// Usually there is only one message inside the status
			foreach ( $ex->getStatusValue()->getMessages() as $msg ) {
				$this->assertInstanceOf( ApiMessage::class, $msg );
				$this->assertSame( $code, $msg->getApiCode() );
			}
		}

		// Assert headers after ApiBeforeMain hook handler, assuming that '&origin=*' was given in parameters
		$this->assertSame( '*', $request->response()->getHeader( 'Access-Control-Allow-Origin' ) );
		$this->assertSame( 'false', $request->response()->getHeader( 'Access-Control-Allow-Credentials' ) );
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

		$request = new class( [ 'centralauthtoken' => $token, 'origin' => '*' ] ) extends FauxRequest {

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
