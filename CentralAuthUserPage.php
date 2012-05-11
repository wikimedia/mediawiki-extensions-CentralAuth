<?php
/**
 * Provides access to global user pages.
 *
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
 * @author Szymon Świerkosz
 */

/**
 * Provides access to global user pages.
 */
class CentralAuthUserPage extends Article {

	private $mForeignUrl = false;

	/**
	 * Returns a url to foreign user page.
	 * @return string
	 */
	private function getForeignUrl() {
		if ( $this->mForeignUrl === false ) {
			global $wgCentralAuthUserPageWiki;
			$this->mForeignUrl = WikiMap::getForeignURL( $wgCentralAuthUserPageWiki, "User:" . $this->getTitle()->getText() );
		}
		return $this->mForeignUrl;
	}

	/**
	 * Returns cache key for page existence status.
	 * @param $title Title
	 * @return string
	 */
	private static function getExistenceCacheKey( $title ) {
		return 'centralauth-userpage-exists-' . $title->getDbKey();
	}

	/**
	 * Checks whether the user page can be shown.
	 * @param $title Title
	 * @return bool
	 */
	private static function canShowUserPage( $title ) {
		global $wgCentralAuthUserPageWiki, $wgDBname, $wgMemc, $wgCentralAuthUserPageCacheTime;

		if ( $title->isSubpage() ) {
			return false;
		}

		if ( $wgCentralAuthUserPageWiki === $wgDBname ) {
			return false;
		}

		$key = wfMemcKey( 'centralauth', 'canShowUserPage', $title->getDbKey() );
		$value = $wgMemc->get( $key );

		if ( $value === false ) {
			$centralUser = new CentralAuthUser( $title->getText() );
			if ( !$centralUser->isAttached() || !$centralUser->attachedOn( $wgCentralAuthUserPageWiki ) ) {
				$value = 0;
			} else {
				$value = 1;
			}
			$wgMemc->set( $key, $value, $wgCentralAuthUserPageCacheTime );
		}

		return $value === 1;
	}

	/**
	 * Fetches the user page from a remote wiki.
	 * @return string
	 */
	private function fetchUserPage() {
		global $wgMemc, $wgCentralAuthUserPageCacheTime;

		$title = $this->getTitle();

		$existenceKey = self::getExistenceCacheKey( $title );
		if ( $wgMemc->get( $existenceKey ) === 0 ) {
			// Page does not exist, nothing to fetch
			return null;
		}

		wfDebug( __METHOD__ . ": fetching user page for " . $title->getPrefixedText() . "\n" );

		$code = $this->getContext()->getLanguage()->getCode();

		$key = 'centralauth-userpage-content-' . $code . '-' . $title->getDbKey();
		$text = $wgMemc->get( $key );

		if ( $text === false ) {
			// Fetch the user page
			wfDebug( __METHOD__ . ": cache miss " . $title->getPrefixedText() . "\n" );

			$url = $this->getForeignUrl();
			if ( $url === null ) {
				// Wikis are not set up correctly
				return null;
			}

			$text = Http::get( wfAppendQuery( $url, array( 'action' => 'render', 'uselang' => $code ) ) );
			if ( $text === false ) {
				// Save negative result to cache too
				$text = null;
			}

			$wgMemc->set( $key, $text, $wgCentralAuthUserPageCacheTime );
			$wgMemc->set( $existenceKey, $text !== null ? 1 : 0, $wgCentralAuthUserPageCacheTime );
		}

		return $text;
	}

	/**
	 * Handles missing user pages.
	 */
	public function showMissingArticle() {
		global $wgCentralAuthUserPageWiki;

		$content = null;
		if ( self::canShowUserPage( $this->getTitle() ) ) {
			$content = $this->fetchUserPage();
		}

		if ( $content === null ) {
			return parent::showMissingArticle();
		}

		$outputPage = $this->getContext()->getOutput();

		$header = wfMessage( 'centralauth-userpage-header' )->params(
			$this->getForeignUrl(),
			WikiMap::getWikiName( $wgCentralAuthUserPageWiki )
		)->parse();
		$outputPage->addHTML( '<div class="mw-centralauth-userpage-header">' . $header . '</div>' );
		$outputPage->addHTML( $content );
	}

	/**
	 * Checks if a global user page exists.
	 * @param $title Title
	 * @return bool
	 */
	public static function exists( $title ) {
		global $wgMemc, $wgCentralAuthUserPageWiki, $wgCentralAuthUserPageCacheTime;

		if ( !self::canShowUserPage( $title ) ) {
			return false;
		}

		$key = self::getExistenceCacheKey( $title );
		$status = $wgMemc->get( $key );

		if ( $status === false ) {
			// Fetch from the slave db
			$lb = wfGetLB( $wgCentralAuthUserPageWiki );
			$dbr = $lb->getConnection( DB_SLAVE, array(), $wgCentralAuthUserPageWiki );

			$result = $dbr->selectRow(
				'page',
				array( 'page_id' ),
				array( 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDbKey() ),
				__METHOD__
			);
			$status = $result !== false ? 1 : 0;
			$wgMemc->set( $key, $status, $wgCentralAuthUserPageCacheTime );

			$lb->reuseConnection( $dbr );
		}

		return $status === 1;
	}
}
