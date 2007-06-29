<?php

$wgExtensionCredits['specialpage'][] = array(
        'name' => 'MergeAccount',
        'author' => 'Brion Vibber',
        'url' => 'http://meta.wikimedia.org/wiki/H:UL',
        'description' => 'Merges multiple accounts for Single User Login',
);

class SpecialMergeAccount extends SpecialPage {
	
	function __construct() {
		parent::__construct( 'MergeAccount', 'MergeAccount' );
	}

	function execute( $subpage ) {
		global $wgOut, $wgRequest, $wgUser;
		$this->setHeaders();
		
		if( !$wgUser->isLoggedIn() ) {
			$wgOut->addWikiText(
				wfMsg( 'centralauth-merge-notlogged' ) .
				"\n\n" .
				wfMsg( 'centralauth-readmore-text' ) );
			
			return;
		}
		
		global $wgUser, $wgRequest;
		$this->mUserName = $wgUser->getName();
		
		$this->mAttemptMerge = $wgRequest->wasPosted();
		$this->mMethod = $wgRequest->getVal( 'wpMethod' );
		$this->mPassword = $wgRequest->getVal( 'wpPassword' );
		$this->mDatabases = $wgRequest->getArray( 'wpWikis' );
		
		// Possible demo states
		
		// success, all accounts merged
		// successful login, some accounts merged, others left
		// successful login, others left
		// not account owner, others left
		
		// is owner / is not owner
		// did / did not merge some accounts
		// do / don't have more accounts to merge
		
		$globalUser = new CentralAuthUser( $this->mUserName );
		$merged = $remainder = array();
		
		// Have we requested a merge?
		if( $this->mAttemptMerge ) {
			$wgOut->addWikiText( wfMsg( 'centralauth-merge-attempt' ) );
			
			$ok = $globalUser->attemptPasswordMigration(
				$this->mPassword,
				$merged,
				$remainder );
			// if ( ! $ok ) ....
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
			$wgOut->addHtml( $this->listMerged( $merged ) );
		}
		
		if( $remainder ) {
			$wgOut->addHtml( '<hr />' );
			$wgOut->addWikiText( wfMsg( 'centralauth-list-unmerged',
				$this->mUserName ) );
			$wgOut->addHtml( $this->listRemainder( $remainder ) );
			
			// Try the password form!
			$wgOut->addHtml( $this->passwordForm() );
		}
	}
	
	function listMerged( $dblist ) {
		return $this->listForm( $dblist, 'unmerge', wfMsg( 'centralauth-admin-unmerge' ) );
	}
	
	function listRemainder( $dblist ) {
		return $this->listForm( $dblist, 'admin', wfMsg( 'centralauth-admin-merge' ) );
	}
	
	function listForm( $dblist  /* Params not used: , $action, $buttonText */ ) {
		$list = $this->listWikis( $dblist );
		
		return $list;
	}
	
	function listWikis( $list ) {
		asort( $list );
		return $this->formatList( $list, array( $this, 'listWikiItem' ) );
	}
	
	function formatList( $items, $callback ) {
		if( empty( $items ) ) {
			return '';
		} else {
			return "<ul>\n<li>" .
				implode( "</li>\n<li>",
					array_map( $callback, $items ) ) .
				"</li>\n</ul>\n";
		}
	}
	
	function listWikiItem( $dbname ) {
		return
			$this->foreignUserLink( $dbname );
	}
	
	function foreignUserLink( $dbname ) {
		$wiki = WikiMap::byDatabase( $dbname );
		if( !$wiki ) {
			throw new MWException( "no wiki for $dbname" );
		}
		
		$hostname = $wiki->getDisplayName();
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
						Xml::hidden( 'wpMethod', 'password' ) .
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


