<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\WikiPage;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use Psr\Log\NullLogger;

/**
 * @author DannyS712
 * @covers \MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist
 */
class GlobalRenameDenylistTest extends MediaWikiUnitTestCase {

	public static function provideCheckUser() {
		// $pageText, $userName, $expected
		yield 'Good user does not match bad' => [ 'BadUser', 'GoodUser', true ];
		yield 'Bad user matches bad' => [ 'BadUser', 'BadUser', false ];
		yield 'Bad user does not match empty' => [ '', 'BadUser', true ];
		yield 'Bad user matches bad regex' => [ '^Bad[A-Za-z]+$', 'BadUser', false ];
		yield 'Good user does not match bad regex' => [ '^Bad[A-Za-z]+$', 'GoodUser', true ];
	}

	/**
	 * @dataProvider provideCheckUser
	 */
	public function testCheckUser( $pageText, $userName, $expected ) {
		// Current tests are only for the case when a Title is provided, not when a url
		// is used
		$sourceTitle = $this->createNoOpMock( Title::class );
		$sourceWikiPage = $this->createMock( WikiPage::class );
		$sourceContent = $this->createMock( WikitextContent::class );
		$sourceContent->method( 'getText' )->willReturn( $pageText );
		$sourceWikiPage->method( 'getContent' )->willReturn( $sourceContent );
		$wikiPageFactory = $this->createMock( WikiPageFactory::class );
		$wikiPageFactory->expects( $this->once() )
			->method( 'newFromTitle' )
			->with( $sourceTitle )
			->willReturn( $sourceWikiPage );

		$denylist = new GlobalRenameDenylist(
			new NullLogger(),
			$this->createNoOpMock( HttpRequestFactory::class ),
			$wikiPageFactory,
			$sourceTitle
		);
		$this->assertSame( $expected, $denylist->checkUser( $userName ) );
	}
}
