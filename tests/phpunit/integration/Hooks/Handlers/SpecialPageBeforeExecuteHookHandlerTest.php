<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialPageBeforeExecuteHookHandler;
use MediaWiki\Extension\CentralAuth\SharedDomainUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\Constraint\StringEndsWith;
use PHPUnit\Framework\Constraint\StringStartsWith;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

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
		// Tested in testSul3CookieCheck()
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthSul3CookieCheck, false );
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
		$specialPage = $this->createNoOpMock( SpecialPage::class, [ 'getName', 'getContext', 'getOutput', 'getRequest', 'getUser' ] );
		$specialPage->method( 'getName' )->willReturn( $page );
		$specialPage->method( 'getContext' )->willReturn( $requestContext );
		$specialPage->method( 'getOutput' )->willReturn( $output );
		$specialPage->method( 'getRequest' )->willReturn( $requestContext->getRequest() );
		$specialPage->method( 'getUser' )->willReturn( $requestContext->getUser() );
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

	/**
	 * @dataProvider provideSul3CookieCheck
	 */
	public function testSul3CookieCheck( string $expect, array $options = [], ?string $expectCount = null ) {
		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );
		$options += [
			'enabled' => true,
			'hasCookie' => false,
			// Age of the query parameter in seconds, or null for no query parameter
			'markerAge' => null,
			'posted' => false,
			'subPage' => '',
			'loggedIn' => false,
		];
		$now = 1795000000;
		ConvertibleTimestamp::setFakeTime( $now );
		$this->overrideConfigValue( CAMainConfigNames::CentralAuthSul3CookieCheck, $options['enabled'] );
		$requestContext = RequestContext::getMain();
		$requestContext->setUser( $options['loggedIn'] ? $this->getTestUser()->getUser() : new User() );
		$param = SpecialPageBeforeExecuteHookHandler::SUL3_COOKIE_CHECK_QUERY_PARAM;
		$query = [ 'title' => 'Special:UserLogin', 'returnto' => 'Foo' ];
		if ( $options['markerAge'] !== null ) {
			$query[$param] = (string)( $now - $options['markerAge'] );
		}
		$request = new FauxRequest( $query, $options['posted'] );
		$request->setRequestURL( '/w/index.php?' . wfArrayToCgi( $query ) );
		$request->setCookie( SpecialPageBeforeExecuteHookHandler::AUTOLOGIN_TRIED_COOKIE, '1', '' );
		if ( $options['hasCookie'] ) {
			$request->setCookie( SpecialPageBeforeExecuteHookHandler::SUL3_COOKIE_CHECK_COOKIE, '1' );
		}
		$requestContext->setRequest( $request );

		$handler = $this->getHandler( [
			'isSharedDomain' => false,
			'isSul3Enabled' => true,
		] );
		$output = $this->createNoOpMock( OutputPage::class,
			[ 'redirect', 'showErrorPage', 'disableClientCache' ] );
		$output->expects( $expect === 'redirect' ? $this->once() : $this->never() )
			->method( 'redirect' )
			// A local URL that keeps the other parameters and has a fresh marker
			->with( LogicalAnd::fromConstraints(
				new StringStartsWith( '/' ),
				new StringContains( rtrim( 'Special:UserLogin/' . $options['subPage'], '/' ) . '&' ),
				new StringContains( 'returnto=Foo' ),
				new StringEndsWith( "$param=$now" )
			) );
		$output->expects( $expect === 'error' ? $this->once() : $this->never() )
			->method( 'showErrorPage' )
			->with( 'loginerror', 'nocookieslogin' );
		$specialPage = $this->createNoOpMock( SpecialPage::class, [ 'getName', 'getContext', 'getOutput', 'getRequest', 'getUser' ] );
		$specialPage->method( 'getName' )->willReturn( 'Userlogin' );
		$specialPage->method( 'getContext' )->willReturn( $requestContext );
		$specialPage->method( 'getOutput' )->willReturn( $output );
		$specialPage->method( 'getRequest' )->willReturn( $request );
		$specialPage->method( 'getUser' )->willReturn( $requestContext->getUser() );

		$result = $handler->onSpecialPageBeforeExecute( $specialPage, $options['subPage'] );

		$this->assertSame( $expect === 'continue', $result );
		// WebResponse::setCookie() adds the cookie prefix; FauxResponse::getCookie() does not.
		$cookieName = $this->getConfVar( MainConfigNames::CookiePrefix )
			. SpecialPageBeforeExecuteHookHandler::SUL3_COOKIE_CHECK_COOKIE;
		if ( $expect !== 'continue' ) {
			$this->assertSame( '1', $request->response()->getCookie( $cookieName ) );
			$this->assertSame( $now + 300, $request->response()->getCookieData( $cookieName )['expire'] );
		} else {
			$this->assertNull( $request->response()->getCookie( $cookieName ) );
		}
		$this->assertFalse( $request->getSession()->isPersistent() );
		$this->assertSame(
			$expectCount ? [ "mediawiki.CentralAuth.sul3_cookie_check_total:1|c|#result:$expectCount" ] : [],
			array_values( preg_grep( '/sul3_cookie_check/', $statsHelper->getAllFormatted() ) )
		);
	}

	public static function provideSul3CookieCheck() {
		return [
			'first visit: set cookie and redirect' => [ 'redirect', [], 'started' ],
			'cookie came back: continue with the login' =>
				[ 'continue', [ 'hasCookie' => true, 'markerAge' => 1 ], 'passed' ],
			'cookie present, no marker' => [ 'continue', [ 'hasCookie' => true ] ],
			'cookie did not come back: error, no session' => [ 'error', [ 'markerAge' => 1 ], 'failed' ],
			'old marker (reload, bookmark): check again' => [ 'redirect', [ 'markerAge' => 3600 ], 'started' ],
			'marker from the future: check again' => [ 'redirect', [ 'markerAge' => -3600 ], 'started' ],
			'check disabled' => [ 'continue', [ 'enabled' => false ] ],
			'POST' => [ 'continue', [ 'posted' => true ] ],
			'return subpage' => [ 'continue', [ 'subPage' => 'return' ] ],
			'other subpage' => [ 'redirect', [ 'subPage' => 'foo' ], 'started' ],
			'logged in' => [ 'continue', [ 'loggedIn' => true ] ],
		];
	}

	private function getHandler( array $config ): SpecialPageBeforeExecuteHookHandler {
		$services = $this->getServiceContainer();
		$sharedDomainUtils = $this->getMockBuilder( SharedDomainUtils::class )
			->onlyMethods( [ 'isSharedDomain', 'isSul3Enabled' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$services->getSpecialPageFactory(),
				null,
				false
			] )
			->getMock();
		$sharedDomainUtils->method( 'isSharedDomain' )->willReturn( $config['isSharedDomain'] );
		$sharedDomainUtils->method( 'isSul3Enabled' )->willReturn( $config['isSul3Enabled'] );
		return new SpecialPageBeforeExecuteHookHandler(
			$services->getAuthManager(),
			$services->getMainConfig(),
			$services->getStatsFactory(),
			CentralAuthServices::getTokenManager( $services ),
			$services->get( 'CentralAuth.CentralDomainUtils' ),
			$sharedDomainUtils
		);
	}

}
