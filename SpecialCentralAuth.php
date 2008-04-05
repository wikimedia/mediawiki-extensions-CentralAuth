<?php

class SpecialCentralAuth extends SpecialPage {

	function __construct() {
		wfLoadExtensionMessages('SpecialCentralAuth');
		parent::__construct( 'CentralAuth', 'centralauth-admin' );
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

		$this->mUserName =
			trim(
				str_replace( '_', ' ',
					$wgRequest->getText( 'target', $subpage ) ) );

		$this->mPosted = $wgRequest->wasPosted();
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

		if ( $this->mUserName === '' ) {
			# First time through
			$this->showUsernameForm();
			return;
		}

		$globalUser = new CentralAuthUser( $this->mUserName );

		if ( !$globalUser->exists() ) {
			$this->showError( wfMsgNoTrans( 'centralauth-admin-nonexistent', $this->mUserName ) );
			$this->showUsernameForm();
			return;
		}

		$deleted = false;

		if( $this->mPosted ) {
			if ( !$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
				$this->showError( wfMsg( 'centralauth-token-mismatch' ) );
			} elseif( $this->mMethod == 'unmerge' ) {
				$status = $globalUser->adminUnattach( $this->mDatabases );
				if ( !$status->isGood() ) {
					$this->showError( $status->getWikiText() );
				} else {
					global $wgLang;
					$this->showSuccess( wfMsgNoTrans( 'centralauth-admin-unmerge-success', 
						$wgLang->formatNum( $status->successCount ),
						$status->successCount ) );
				}
			} elseif ( $this->mMethod == 'delete' ) {
				$status = $globalUser->adminDelete();
				if ( !$status->isGood() ) {
					$this->showError( $status->getWikiText() );
				} else {
					global $wgLang;
					$this->showSuccess( wfMsgNoTrans( 'centralauth-admin-delete-success', $this->mUserName ) );
					$deleted = true;
				}
			} else {
				$this->showError( wfMsg( 'centralauth-admin-bad-input' ) );
			}

		}

		$this->showUsernameForm();
		if ( !$deleted ) {
			$this->showInfo();
			$this->showDeleteForm();
		}
	}

	function showError( $message ) {
		global $wgOut;
		$wgOut->addWikiText( "<div class='error'>\n$message</div>" );
	}

	function showSuccess( $message ) {
		global $wgOut;
		$wgOut->addWikiText( "<div class='success'>\n$message</div>" );
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
				return wfMsgExt( "centralauth-$unit-ago", 'parsemag', $span );
			}
			$span = intval( $span / $chunk );
		}
		return wfMsgExt( "centralauth-$unit-ago", 'parsemag', $span );
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
				'id' => $globalUser->getId(),
				'registered' => $wgLang->timeanddate( $reg ) . " ($age)",
				'locked' => $globalUser->isLocked() ? wfMsg( 'centralauth-admin-yes' ) : wfMsg( 'centralauth-admin-no' ),
				'hidden' => $globalUser->isHidden() ? wfMsg( 'centralauth-admin-yes' ) : wfMsg( 'centralauth-admin-no' ) );
			$out = '<ul>';
			foreach( $attribs as $tag => $data ) {
				$out .= Xml::element( 'li', array(), wfMsg( "centralauth-admin-info-$tag" ) . ' ' . $data );
			}
			$out .= '</ul>';
			$wgOut->addHtml( $out );

			$wgOut->addWikiText( '<h2>' . wfMsg( 'centralauth-admin-attached' ) . '</h2>' );
			$wgOut->addHtml( $this->listMerged( $merged ) );

			$wgOut->addWikiText( '<h2>' . wfMsg( 'centralauth-admin-unattached' ) . '</h2>' );
			if( $remainder ) {
				$wgOut->addHtml( $this->listRemainder( $remainder ) );
			} else {
				$wgOut->addWikiText( wfMsg( 'centralauth-admin-no-unattached' ) );
			}
		} else {
			$wgOut->addWikiText( wfMsg( 'centralauth-admin-no-unified' ) );
		}
	}

	function listMerged( $list ) {
		return $this->listForm( $list, 'listMergedWikiItem',
			'unmerge', wfMsg( 'centralauth-admin-unmerge' ) );
	}

	function listRemainder( $list ) {
		ksort( $list );
		$s = '<ul>';
		foreach ( $list as $row ) {
			$s .= '<li>' . $this->foreignUserLink( $row['dbName'] ) . "</li>\n";
		}
		$s .= '</ul>';
		return $s;
	}

	function listForm( $list, $listMethod, $action, $buttonText ) {
		global $wgUser;
		ksort( $list );
		return
			Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle( $this->mUserName )->getLocalUrl( 'action=submit' ) ) ) .
			Xml::hidden( 'wpMethod', $action ) .
			Xml::hidden( 'wpEditToken', $wgUser->editToken() ) .
			'<table>' .
			'<thead>' .
			$this->tableRow( 'th',
				array( '', wfMsgHtml( 'centralauth-admin-list-localwiki' ), wfMsgHtml( 'centralauth-admin-list-attached-on' ), wfMsgHtml( 'centralauth-admin-list-method' ) ) ) .
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
				htmlspecialchars( wfMsg( 'centralauth-merge-method-' . $row['attachedMethod'] ) ),
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

	function showDeleteForm() {
		global $wgOut, $wgUser;
		$wgOut->addHtml(
			Xml::element( 'h2', array(), wfMsg( 'centralauth-admin-delete-title' ) ) .
			Xml::openElement( 'form', array(
				'method' => 'POST',
				'action' => $this->getTitle()->getFullUrl( 'target=' . urlencode( $this->mUserName ) ) ) ) .
			Xml::hidden( 'wpMethod', 'delete' ) .
			Xml::hidden( 'wpEditToken', $wgUser->editToken() ) .
			wfMsgExt( 'centralauth-admin-delete-description', 'parse' ) .
			'<p>' .
			Xml::submitButton( wfMsg( 'centralauth-admin-delete-button' ) ) .
			'</p>' .
			'</form>' );
	}
}
