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
		
		if( !$wgUser->isAllowed( 'centralauth-admin' ) ) {
			$wgOut->addWikiText(
				wfMsg( 'centralauth-admin-permission' ) .
				"\n\n" .
				wfMsg( 'centralauth-readmore-text' ) );
			return;
		}

		$this->mUserName = $wgRequest->getText( 'target', $subpage );
		
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
		$this->showUsernameForm();
		$this->showInfo();
	}
	
	function showUsernameForm() {
		global $wgOut, $wgScript;
		$wgOut->addHtml(
			Xml::openElement( 'form', array(
				'method' => 'get',
				'action' => $wgScript ) ) .
			'<fieldset>' .
			Xml::element( 'legend', array(), wfMsg( 'centralauth-admin-manage' ) ) .
			Xml::hidden( 'title', $this->getTitle()->getPrefixedText() ) .
			'<p>' .
			Xml::inputLabel( wfMsg( 'centralauth-admin-username' ),
				'target', 'target', 25, $this->mUserName ) .
			'</p>' .
			'<p>' .
			Xml::submitButton( wfMsg( 'centralauth-admin-lookup' ) ) .
			'</p>' .
			'</fieldset>' .
			'</form>'
		);
	}
	
	function showInfo() {
		$globalUser = new CentralAuthUser( $this->mUserName );
		
		$name = $this->mUserName;
		$id = $globalUser->exists() ? $globalUser->getId() : "unified account not registered";
		$merged = $globalUser->listAttached();
		$remainder = $globalUser->listUnattached();
		
		global $wgOut;
		if( $globalUser->exists() ) {
			$wgOut->addWikiText( "User id: $id" );
			
			$wgOut->addWikiText( "<h2>Fully merged accounts:</h2>" );
			$wgOut->addHtml( $this->listMerged( $merged ) );
		
			$wgOut->addWikiText( "<h2>Unattached accounts:</h2>" );
			if( $remainder ) {
				$wgOut->addHtml( $this->listRemainder( $remainder ) );
			} else {
				$wgOut->addWikiText( 'No unmerged accounts remain.' );
			}
		} else {
			$wgOut->addWikiText( "No unified account for this username." );
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
			'<table>' .
			'<tbody>' .
			$list .
			'<tr>' .
			'<td></td>' .
			'<td>' .
			Xml::submitButton( $buttonText ) .
			'</td>' .
			'</tr>' .
			'</tbody>' .
			'</table>' .
			Xml::closeElement( 'form' );
	}
	
	function listWikis( $list ) {
		asort( $list );
		return implode( "\n",
			array_map( array( $this, 'listWikiItem' ),
			 	$list ) );
	}
	
	function listWikiItem( $dbname ) {
		return
			'<tr>' .
			'<td>' .
			$this->adminCheck( $dbname ) .
			'</td>' .
			'<td>' .
			$this->foreignUserLink( $dbname ) .
			'</td>' .
			'</tr>';
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
	
}


?>