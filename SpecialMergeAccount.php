<?php

/**

# BLAAAAR HEY DUDE

Things we need:
* map of databases <-> URLs
* thingies!
** NEAT THINGIES
*** SHINY OMFGGGGG


*/

class CentralAuth {
	static function foreignHostname( $dbname ) {
		$map = array(
			'enwiki' => 'en.wikipedia.org',
			'enwikisource' => 'en.wikisource.org',
			'frwiki' => 'fr.wikipedia.org',
			'metawiki' => 'meta.wikimedia.org',
			'mediawikiwiki' => 'www.mediawiki.org',
			'enwikinews' => 'en.wikinews.org',
			'frwikisource' => 'fr.wikisource.org',
			'zhwiktionary' => 'zh.wiktionary.org',
		);
		return $map[$dbname];
	}
	
	static function foreignUrl( $dbname, $title ) {
		$host = self::foreignHostname( $dbname );
		return "http://$host/wiki/" .
			wfUrlencode( $title );
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
		$hostname = CentralAuth::foreignHostname( $dbname );
		$userPageName = 'User:' . $this->mUserName;
		$url = CentralAuth::foreignUrl( $dbname, $userPageName );
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