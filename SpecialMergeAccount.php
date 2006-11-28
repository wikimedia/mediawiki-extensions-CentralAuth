<?php

class WikiMap {
	function byDatabase( $dbname ) {
		global $wgConf;
		list( $major, $minor ) = $wgConf->siteFromDB( $dbname );
		if( isset( $major ) ) {
			$server = $wgConf->get( 'wgServer', $dbname,
				null,
				array( 'lang' => $minor, 'site' => $major ) );
			return new WikiReference( $major, $minor, $server );
		} else {
			return null;
		}
		
	}
}

class WikiReference {
	private $mMinor; ///< 'en', 'meta', 'mediawiki', etc
	private $mMajor; ///< 'wiki', 'wiktionary', etc
	private $mServer; ///< server override, 'www.mediawiki.org'
	
	function __construct( $major, $minor, $server ) {
		$this->mMajor = $major;
		$this->mMinor = $minor;
		$this->mServer = $server;
	}
	
	function getHostname() {
		if( substr( $this->mServer, 0, 7 ) === 'http://' ) {
			return substr( $this->mServer, 7 );
		} else {
			throw new MWException( "wtf" );
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
		global $wgUser, $wgRequest;
		$this->mUserName = $wgUser->getName();
		
		$this->mAttemptMerge = $wgRequest->wasPosted();
		$this->mPassword = $wgRequest->getVal( 'wpPassword' );
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
		
		/*
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
		*/
		
		global $wgUser;
		//$globalUser = CentralAuthUser::newFromUser( $wgUser );
		$globalUser = new CentralAuthUser( $wgUser->getName() );
		
		// Have we requested a merge?
		if( $this->mAttemptMerge ) {
			$wgOut->addWikiText( wfMsg( 'centralauth-merge-attempt' ) );
			
			$merged = $remainder = array();
			$ok = $globalUser->attemptPasswordMigration(
				$this->mPassword,
				$merged,
				$remainder );
		} else {
			$merged = array();
			$remainder = $globalUser->listUnattached();
		}
		$this->showStatus( $merged, $remainder );
	}
	
	function showStatus( $merged, $remainder ) {
		global $wgOut;
		
		if( count( $remainder ) > 0 ) {
			$wgOut->setPageTitle( wfMsg( 'centralauth-incomplete' ) );
			$wgOut->addWikiText( wfMsg( 'centralauth-incomplete-text' ) );
		} else {
			$wgOut->setPageTitle( wfMsg( 'centralauth-complete' ) );
			$wgOut->addWikiText( wfMsg( 'centralauth-complete-text' ) );
		}
		$wgOut->addWikiText( wfMsg( 'centralauth-readmore-text' ) );
		
		if( $merged ) {
			$wgOut->addHtml( '<hr />' );
			$wgOut->addWikiText( wfMsg( 'centralauth-list-merged',
				$this->mUserName ) );
			$wgOut->addHtml( $this->listWikis( $merged ) );
		}
		
		if( $remainder ) {
			$wgOut->addHtml( '<hr />' );
			$wgOut->addWikiText( wfMsg( 'centralauth-list-unmerged',
				$this->mUserName ) );
			$wgOut->addHtml( $this->listWikis( $remainder ) );
			
			// Try the password form!
			$wgOut->addHtml( $this->passwordForm() );
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
	
	function passwordForm() {
		return
			'<div id="userloginForm">' .
			Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle()->getLocalUrl( 'action=submit' ) ) ) .
			Xml::element( 'h2', array(), wfMsg( 'centralauth-finish-title' ) ) .
			
			wfMsgExt( 'centralauth-finish-text', array( 'parse' ) ) .
			
			'<table>' .
				'<tr>' .
					'<td>' .
						Xml::label(
							wfMsg( 'centralauth-finish-password' ),
							'wpPassword1' ) .
					'</td>' .
					'<td>' .
						Xml::input( 
							'wpPassword', 20, '',
								array(
									'type' => 'password',
									'id' => 'wpPassword1' ) ) .
					'</td>' .
				'</tr>' .
				'<tr>' .
					'<td></td>' .
					'<td>' .
						Xml::submitButton( wfMsg( 'centralauth-finish-login' ),
							array( 'name' => 'wpLogin' ) ) .
						Xml::submitButton(
							wfMsg( 'centralauth-finish-send-confirmation' ),
							array(
								'name' => 'wpMailmypassword',
								'id' => 'wpMailmypassword' ) ) .
					'</td>' .
				'</tr>' .
			'</table>' .
			
			Xml::closeElement( 'form' ) .
			
			wfMsgExt( 'centralauth-finish-problems', array( 'parse' ) ) .
			
			'</div>';
	}
}


?>