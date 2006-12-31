<?php

class SpecialCentralAuth extends SpecialPage {
	
	function __construct() {
		parent::__construct( 'CentralAuth', 'CentralAuth' );
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
		
		if( $wgUser->isAllowed( 'centralauth-admin' ) ) {
			$this->mUserName = $subpage;
		} else {
			$wgOut->addWikiText(
				wfMsg( 'centralauth-admin-permission' ) .
				"\n\n" .
				wfMsg( 'centralauth-readmore-text' ) );
			return;
		}
		
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
		
		if( $this->mAttemptMerge ) {
			if( $this->mMethod == 'admin' ) {
				$ok = $globalUser->adminAttach(
					$this->mDatabases,
					$merged,
					$remainder );
			} elseif( $this->mMethod == 'unmerge' ) {
				$ok = $globalUser->adminUnattach(
					$this->mDatabases,
					$merged,
					$remainder );
			} else {
				// complain
				die( 'noooo' );
			}
		} else {
			$merged = $globalUser->listAttached();
			$remainder = $globalUser->listUnattached();
		}
		$this->showInfo();
	}
	
	function showInfo() {
		$globalUser = new CentralAuthUser( $this->mUserName );
		
		$name = $this->mUserName;
		$id = $globalUser->exists() ? $globalUser->getId() : "unified account not registered";
		$merged = $globalUser->listAttached();
		$remainder = $globalUser->listUnattached();
		
		global $wgOut;
		$wgOut->addWikiText( "User name: $name" );
		$wgOut->addWikiText( "User id: $id" );
		
		if( $globalUser->exists() ) {
			$wgOut->addWikiText( "<h2>Fully merged accounts:</h2>" );
			$wgOut->addHtml( $this->listMerged( $merged ) );
		
			$wgOut->addWikiText( "<h2>Unattached accounts:</h2>" );
			if( $remainder ) {
				$wgOut->addHtml( $this->listRemainder( $remainder ) );
			} else {
				$wgOut->addWikiText( 'No unmerged accounts remain.' );
			}
		}
	}

	function listMerged( $dblist ) {
		return $this->listForm( $dblist, 'unmerge', wfMsg( 'centralauth-admin-unmerge' ) );
	}
	
	function listRemainder( $dblist ) {
		return $this->listForm( $dblist, 'admin', wfMsg( 'centralauth-admin-merge' ) );
	}
	
	function listForm( $dblist, $action, $buttonText ) {
		$list = $this->listWikis( $dblist );
		
		return
			Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle( $this->mUserName )->getLocalUrl( 'action=submit' ) ) ) .
			Xml::hidden( 'wpMethod', $action ) .
			$list .
			Xml::submitButton( $buttonText ) .
			Xml::closeElement( 'form' );
	}
	
	function listWikis( $list ) {
		asort( $list );
		return $this->formatList( $list, array( $this, 'listWikiItem' ) );
		return $out;
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
			$this->adminCheck( $dbname ) .
			' ' .
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
	
	function adminCheck( $dbname ) {
		return
			Xml::check( 'wpWikis[]', false, array( 'value' => $dbname ) );
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


?>