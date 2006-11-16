<?php

/**

# BLAAAAR HEY DUDE

Things we need:
* map of databases <-> URLs
* thingies!
** NEAT THINGIES
*** SHINY OMFGGGGG


*/

global $wgSecureUrlHost;
$wgSecureUrlHost = 'secure.wikimedia.org';

class WikiMap {
	function byDatabase( $dbname ) {
		$suffixes = array(
			'wiki'       => 'wikipedia',
			'wikisource' => 'wikisource',
			'wikinews'   => 'wikinews',
			'wiktionary' => 'wiktionary',
		);
		$overrides = array(
			'metawiki' => 'meta.wikimedia.org',
			'mediawikiwiki' => 'www.mediawiki.org',
		);
		foreach( $suffixes as $suffix => $family ) {
			if( substr( $dbname, -strlen( $suffix ) ) == $suffix ) {
				$major = $family;
				$minor = substr( $dbname, 0, strlen( $dbname ) - strlen( $suffix ) );
				break;
			}
		}
		if( !isset( $major ) ) {
			return null;
		}
		$hostname = @$overrides[$dbname];
		return new WikiReference( $major, $minor, $hostname );
	}
}

class WikiReference {
	private $mMinor; ///< 'en', 'meta', 'mediawiki', etc
	private $mMajor; ///< 'wiki', 'wiktionary', etc
	private $mHostname; ///< hostname override, 'www.mediawiki.org'
	
	function __construct( $major, $minor, $hostname=null ) {
		$this->mMajor = $major;
		$this->mMinor = $minor;
		$this->mHostname = $hostname;
	}
	
	function getHostname() {
		if( $this->mHostname ) {
			return $this->mHostname;
		} else {
			return $this->mMinor .
				'.' .
				$this->mMajor .
				'.org';
		}
	}
	
	private function getLocalUrl( $page ) {
		// FIXME: this may be generalized...
		return '/wiki/' . urlencode( $page );
	}
	
	function getCanonicalUrl( $page ) {
		return
			'http://' .
			$this->getHostname() .
			$this->getLocalUrl( $page );
	}
	
	function getSecureUrl( $page ) {
		global $wgSecureUrlHost;
		if( $wgSecureUrlHost ) {
			// For the current secure.wikimedia.org hack
			// In the future we'll want to move to a nice
			// clean https://en.wikipedia.org/ etc
			return
				'https://' .
				$wgSecureUrlHost .
				'/' . $this->mMajor .
				'/' . $this->mMinor .
				$this->getLocalUrl( $page );
		} else {
			return
				'https://' .
				$this->getHostname() .
				$this->getLocalUrl( $page );
		}
	}
	
	/**
	 * If the current user is coming over HTTPS, return
	 * the secure URL to match...
	 */
	function getUrl( $page ) {
		if( isset( $_SERVER['HTTPS'] ) ) {
			return $this->getSecureUrl( $page );
		} else {
			return $this->getCanonicalUrl( $page );
		}
	}
}

class SpecialMergeAccount extends SpecialPage {
	
	function __construct() {
		parent::__construct( 'MergeAccount', 'MergeAccount' );
		global $wgUser;
		$this->mUserName = $wgUser->getName();
	}

	function execute( $subpage ) {
		global $wgOut, $wgRequest;
		$this->setHeaders();
		
		// Possible demo states
		
		// success, all accounts merged
		// successful login, some accounts merged, others left
		// successful login, others left
		// not account owner, others left
		
		// is owner / is not owner
		// did / did not merge some accounts
		// do / don't have more accounts to merge
		
		$merged = array(
			'enwiki',
			'enwikisource',
			'frwiki',
			'metawiki' );
		$remainder = array(
			'mediawikiwiki',
			'enwikinews',
			'frwikisource',
			'zhwiktionary' );
		$this->complete( $merged, $remainder );
	}
	
	function complete( $merged, $remainder ) {
		global $wgOut;
		
		$wgOut->setPageTitle( wfMsg( 'centralauth-complete' ) );
		$wgOut->addWikiText( wfMsg( 'centralauth-complete-text' ) );
		$wgOut->addWikiText( wfMsg( 'centralauth-readmore-text' ) );
		
		if( $merged ) {
			$wgOut->addWikiText( wfMsg( 'centralauth-list-merged',
				$this->mUserName ) );
			$wgOut->addHtml( $this->listWikis( $merged ) );
		}
		
		if( $remainder ) {
			$wgOut->addWikiText( wfMsg( 'centralauth-list-unmerged',
				$this->mUserName ) );
			$wgOut->addHtml( $this->listWikis( $remainder ) );
		}
	}
	
	function listWikis( $list ) {
		asort( $list );
		$out = '<ul>';
		foreach( $list as $dbname ) {
			$out .= '<li>' .
				$this->foreignUserLink( $dbname ) .
				'</li>';
		}
		$out .= '</ul>';
		return $out;
	}
	
	function foreignUserLink( $dbname ) {
		$wiki = WikiMap::byDatabase( $dbname );
		if( !$wiki ) {
			throw new MWException( "no wiki for $dbname" );
		}
		
		$hostname = $wiki->getHostname();
		$userPageName = 'User:' . $this->mUserName;
		$url = $wiki->getUrl( $userPageName );
		return wfElement( 'a',
			array(
				'href' => $url,
				'title' => wfMsg( 'centralauth-foreign-link',
					$this->mUserName,
					$hostname ),
			),
			$hostname );
	}
}


?>