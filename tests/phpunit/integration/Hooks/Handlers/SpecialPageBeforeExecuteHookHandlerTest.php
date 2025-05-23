<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialPageBeforeExecuteHookHandler;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\StringContains;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialPageBeforeExecuteHookHandler
 * @group Database
 */
class SpecialPageBeforeExecuteHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideSul3Redirect
	 */
	public function testSul3Redirect(
		string $page,
		bool $isLoggedIn,
		bool $isSharedDomain,
		bool $isSul3Enabled,
		bool $expectRedirect
	) {
		$requestContext = RequestContext::getMain();
		$requestContext->setUser( $isLoggedIn ? User::newFromName( 'Foo' ) : new User() );
		$request = new FauxRequest();
		// Avoid top-level autologin so we have to mock less things
		$request->setCookie( SpecialPageBeforeExecuteHookHandler::AUTOLOGIN_TRIED_COOKIE, '1', '' );
		$requestContext->setRequest( $request );

		$handler = $this->getHandler( [
			'isSharedDomain' => $isSharedDomain,
			'isSul3Enabled' => $isSul3Enabled,
		] );
		$output = $this->createNoOpMock( OutputPage::class, [ 'redirect' ] );
		if ( $expectRedirect ) {
			$output->expects( $this->once() )->method( 'redirect' )->with(
				LogicalAnd::fromConstraints(
					new StringContains( 'Special:UserLogin' ),
					new StringContains( 'sul3-action=signup' )
				)
			);
		} else {
			$output->expects( $this->never() )->method( 'redirect' );
		}
		$specialPage = $this->createNoOpMock( SpecialPage::class, [ 'getName', 'getOutput', 'getRequest', 'getUser' ] );
		$specialPage->method( 'getName' )->willReturn( $page );
		$specialPage->method( 'getOutput' )->willReturn( $output );
		$specialPage->method( 'getRequest' )->willReturn( RequestContext::getMain()->getRequest() );
		$specialPage->method( 'getUser' )->willReturn( RequestContext::getMain()->getUser() );
		$handler->onSpecialPageBeforeExecute( $specialPage, '' );
	}

	public static function provideSul3Redirect() {
		return [
			'redirect from signup with SUL3 enabled' => [
				'page' => 'CreateAccount',
				'isLoggedIn' => false,
				'isSharedDomain' => false,
				'isSul3Enabled' => true,
				'expectRedirect' => true
			],
			'...but not when on a shared domain' => [
				'page' => 'CreateAccount',
				'isLoggedIn' => false,
				'isSharedDomain' => true,
				'isSul3Enabled' => true,
				'expectRedirect' => false
			],
			'...or when SUL3 is disabled' => [
				'page' => 'CreateAccount',
				'isLoggedIn' => false,
				'isSharedDomain' => false,
				'isSul3Enabled' => false,
				'expectRedirect' => false
			],
			'...or from login' => [
				'page' => 'Userlogin',
				'isLoggedIn' => false,
				'isSharedDomain' => false,
				'isSul3Enabled' => true,
				'expectRedirect' => false
			],
			'...or when logged in' => [
				'page' => 'CreateAccount',
				'isLoggedIn' => true,
				'isSharedDomain' => false,
				'isSul3Enabled' => false,
				'expectRedirect' => false
			],
		];
	}

	private function getHandler( array $config ): SpecialPageBeforeExecuteHookHandler {
		$services = $this->getServiceContainer();
		$sharedDomainUtils = $this->getMockBuilder( SharedDomainUtils::class )
			->onlyMethods( [ 'isSharedDomain', 'isSul3Enabled' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$services->getSpecialPageFactory(),
				new HookRunner( $services->getHookContainer() ),
				null,
				false,
				$services->getTempUserConfig()
			] )
			->getMock();
		$sharedDomainUtils->method( 'isSharedDomain' )->willReturn( $config['isSharedDomain'] );
		$sharedDomainUtils->method( 'isSul3Enabled' )->willReturn( $config['isSul3Enabled'] );
		return new SpecialPageBeforeExecuteHookHandler(
			$services->getAuthManager(),
			$services->getHookContainer(),
			CentralAuthServices::getTokenManager( $services ),
			$services->get( 'CentralAuth.CentralDomainUtils' ),
			$sharedDomainUtils
		);
	}

}
