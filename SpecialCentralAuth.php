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
		$this->mDatabases = (array)$wgRequest->getArray( 'wpWikis' );
		
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
		$this->showUsernameForm();
		
		if( $this->mAttemptMerge ) {
			if( empty( $this->mDatabases ) ) {
				$this->showError( wfMsg( 'centralauth-admin-none-selected' ) );
			} elseif( $this->mMethod == 'admin' ) {
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
				$this->showError( wfMsg( 'centralauth-admin-bad-input' ) );
			}
			
			// if (! $ok) { ....
		} else {
			$merged = $globalUser->listAttached();
			$remainder = $globalUser->listUnattached();
		}
		
		$this->showInfo();
	}
	
	function showError( $message ) {
		global $wgOut;
		$wgOut->addWikiText( "<div class='error'>$message</div>" );
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
	
	function prettyTimespan( $span ) {
		$units = array(
			'seconds' => 60,
			'minutes' => 60,
			'hours' => 24,
			'days' => 30.417,
			'months' => 12,
			'years' => 1 );
		foreach( $units as $unit => $chunk ) {
			if( $span < 2*$chunk ) {
				return "$span $unit";
			}
			$span = intval( $span / $chunk );
		}
		return "$span $unit";
	}
	
	
	function showInfo() {
		$globalUser = new CentralAuthUser( $this->mUserName );
		
		$id = $globalUser->exists() ? $globalUser->getId() : "unified account not registered";
		$merged = $globalUser->queryAttached();
		$remainder = $globalUser->queryUnattached();
		
		global $wgOut, $wgLang;
		if( $globalUser->exists() ) {
			$reg = $globalUser->getRegistration();
			$age = $this->prettyTimespan( wfTimestamp( TS_UNIX ) - wfTimestamp( TS_UNIX, $reg ) );
			$attribs = array(
				'User id:' => $globalUser->getId(),
				'Registered:' => $wgLang->timeanddate( $reg ) . " ($age ago)",
				'Locked:' => $globalUser->isLocked() ? 'yes' : 'no',
				'Hidden:' => $globalUser->isHidden() ? 'yes' : 'no' );
			$out = '<ul>';
			foreach( $attribs as $tag => $data ) {
				$out .= Xml::element( 'li', array(), $tag . ' ' . $data );
			}
			$out .= '</ul>';
			$wgOut->addHtml( $out );
			
			$wgOut->addWikiText( "<h2>Fully merged accounts</h2>" );
			$wgOut->addHtml( $this->listMerged( $merged ) );
		
			$wgOut->addWikiText( "<h2>Unattached accounts</h2>" );
			if( $remainder ) {
				$wgOut->addHtml( $this->listRemainder( $remainder ) );
			} else {
				$wgOut->addWikiText( 'No unmerged accounts remain.' );
			}
		} else {
			$wgOut->addWikiText( "No unified account for this username." );
		}
	}

	function listMerged( $list ) {
		return $this->listForm( $list, 'listMergedWikiItem',
			'unmerge', wfMsg( 'centralauth-admin-unmerge' ) );
	}
	
	function listRemainder( $list ) {
		return $this->listForm( $list, 'listRemainingWikiItem',
		 	'admin', wfMsg( 'centralauth-admin-merge' ) );
	}
	
	function listForm( $list, $listMethod, $action, $buttonText ) {
		ksort( $list );
		return
			Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle( $this->mUserName )->getLocalUrl( 'action=submit' ) ) ) .
			Xml::hidden( 'wpMethod', $action ) .
			'<table>' .
			'<thead>' .
			$this->tableRow( 'th',
				array( '', 'Local wiki', 'Attached on', 'Method' ) ) .
			'</thead>' .
			'<tbody>' .
			implode( "\n",
				array_map( array( $this, $listMethod ),
				 	$list ) ) .
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
	
	function listMergedWikiItem( $row ) {
		global $wgLang;
		return $this->tableRow( 'td',
			array(
				$this->adminCheck( $row['dbName'] ),
				$this->foreignUserLink( $row['dbName'] ),
				htmlspecialchars( $wgLang->timeanddate( $row['attachedTimestamp'] ) ),
				htmlspecialchars( $row['attachedMethod'] ),
			)
		);
	}
	
	function listRemainingWikiItem( $row ) {
		global $wgLang;
		return $this->tableRow( 'td',
			array(
				$this->adminCheck( $row['dbName'] ),
				$this->foreignUserLink( $row['dbName'] ),
			)
		);
	}
	
	function tableRow( $element, $cols ) {
		return "<tr><$element>" .
			implode( "</$element><$element>", $cols ) .
			"</$element></tr>";
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


