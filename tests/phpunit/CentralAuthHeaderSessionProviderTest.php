<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Hook\HookRunner;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Module\Module;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Session\SessionInfo;

/**
 * @covers \CentralAuthHeaderSessionProvider
 * @group medium
 * @group Database
 */
class CentralAuthHeaderSessionProviderTest extends CentralAuthTokenSessionProviderTestBase {

	protected function newSessionProviderClass(): CentralAuthHeaderSessionProvider {
		$services = $this->getServiceContainer();
		return new CentralAuthHeaderSessionProvider(
			$services->getUserIdentityLookup(),
			CentralAuthServices::getApiTokenManager( $services ),
			CentralAuthServices::getSessionManager( $services ),
		);
	}

	protected function makeRequest( $token ) {
		$request = new FauxRequest();
		$request->setHeader( 'Authorization', "CentralAuthToken $token" );
		return $request;
	}

	protected function assertSessionInfoError(
		WebRequest $request,
		?SessionInfo $result,
		?string $error = null,
		?string $code = null
	) {
		parent::assertSessionInfoError( $request, $result, $error, $code );
		$this->assertErrorFromRestCheckCanExecute( $request, $error );
		// $code is currently not used in REST API
	}

	private function assertErrorFromRestCheckCanExecute( WebRequest $request, $error ) {
		$context = new RequestContext();
		$context->setRequest( $request );

		$exception = null;
		( new HookRunner( $this->hookContainer ) )->onRestCheckCanExecute(
			$this->createNoOpMock( Module::class ),
			$this->createNoOpMock( Handler::class ),
			'/',
			$this->createNoOpMock( RequestInterface::class ),
			$exception
		);

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( $error, $exception->getMessageValue()->getKey() );
	}
}
