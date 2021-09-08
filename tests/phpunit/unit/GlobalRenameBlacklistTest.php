<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\WikiPageFactory;
use Psr\Log\NullLogger;

/**
 * @author DannyS712
 * @covers GlobalRenameBlacklist
 */
class GlobalRenameBlacklistTest extends MediaWikiUnitTestCase {

	public function provideCheckUser() {
		// $pageText, $userName, $expected
		yield 'Good user does not match bad' => [ 'BadUser', 'GoodUser', true ];
		yield 'Bad user matches bad' => [ 'BadUser', 'BadUser', false ];
		yield 'Bad user does not match empty' => [ '', 'BadUser', true ];
	}

	/**
	 * @dataProvider provideCheckUser
	 * @covers GlobalRenameBlacklist::checkUser
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

		$blacklist = new GlobalRenameBlacklist(
			new NullLogger(),
			$this->createNoOpMock( HttpRequestFactory::class ),
			$wikiPageFactory,
			$sourceTitle,
			false // No regex
		);
		$this->assertSame( $expected, $blacklist->checkUser( $userName ) );
	}
}
