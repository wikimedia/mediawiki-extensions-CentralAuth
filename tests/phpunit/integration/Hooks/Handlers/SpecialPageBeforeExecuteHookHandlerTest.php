<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
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
 */
class SpecialPageBeforeExecuteHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideSul3Redirect
	 */
	public function testSul3Redirect(
		string $page,
		bool $isLoggedIn,
		bool $isSharedDomain,
		?bool $isSul3Enabled,
		array $centralAuthEnableSul3,
		int $sul3RolloutAnonSignupPercentage,
		bool $expectRedirect
	) {
		$this->overrideConfigValues( [
			CAMainConfigNames::CentralAuthEnableSul3 => $centralAuthEnableSul3,
			CAMainConfigNames::Sul3RolloutAnonSignupPercentage => $sul3RolloutAnonSignupPercentage,
		] );

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

	public function provideSul3Redirect() {
		return [
			// page, isLoggedIn, isSharedDomain, isSul3Enabled, centralAuthEnableSul3,
			//   sul3RolloutAnonSignupPercentage, expectRedirect
			'redirect from signup with SUL3 enabled' => [ 'CreateAccount', false, false, true, [], 100, true ],
			'...but not when on a shared domain' => [ 'CreateAccount', false, true, true, [], 100, false ],
			'...or when SUL3 is disabled' => [ 'CreateAccount', false, false, false, [], 100, false ],
			'...or from login' => [ 'Userlogin', false, false, true, [], 100, false ],
			'use percentage when SUL3 enabled flag is neutral (0%)'
				=> [ 'CreateAccount', false, false, null, [ 'cookie' ], 0, false ],
			'use percentage when SUL3 enabled flag is neutral (100%)'
				=> [ 'CreateAccount', false, false, null, [ 'cookie' ], 100, true ],
			'dont use percentage when cookies are disabled'
				=> [ 'CreateAccount', false, false, null, [], 100, false ],
			'dont use percentage when logged in' => [ 'CreateAccount', true, false, null, [], 100, false ],

		];
	}

	private function getHandler( array $config ): SpecialPageBeforeExecuteHookHandler {
		$services = $this->getServiceContainer();
		$sharedDomainUtils = $this->getMockBuilder( SharedDomainUtils::class )
			->onlyMethods( [ 'isSharedDomain', 'isSul3Enabled' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$services->getTitleFactory(),
				static fn () => $services->getUserOptionsManager(),
				new HookRunner( $services->getHookContainer() ),
				null,
				false,
				$services->getTempUserConfig()
			] )
			->getMock();
		$sharedDomainUtils->method( 'isSharedDomain' )->willReturn( $config['isSharedDomain'] );
		$sharedDomainUtils->method( 'isSul3Enabled' )->willReturnCallback(
			static function ( $request, &$isUnset = null ) use ( $config ) {
				if ( $config['isSul3Enabled'] === null ) {
					$isUnset = true;
					return false;
				} else {
					$isUnset = false;
					return $config['isSul3Enabled'];
				}
			}
		);
		return new SpecialPageBeforeExecuteHookHandler(
			$services->getAuthManager(),
			$services->getHookContainer(),
			$services->getMainConfig(),
			$services->getUserNameUtils(),
			CentralAuthServices::getTokenManager( $services ),
			$services->get( 'CentralAuth.CentralDomainUtils' ),
			$sharedDomainUtils
		);
	}

}
