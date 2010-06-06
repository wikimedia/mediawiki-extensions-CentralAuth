<?php
/**
 * Special page to allow to edit "wikisets" which are used to restrict
 * specific global group permissions to certain wikis.
 *
 * @file
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralAuth extension\n";
	exit( 1 );
}


class SpecialWikiSets extends SpecialPage {
	var $mCanEdit;

	function __construct() {
		parent::__construct( 'WikiSets' );
		wfLoadExtensionMessages( 'SpecialCentralAuth' );
	}

	function getDescription() {
		return wfMsg( 'centralauth-editset' );
	}

	function execute( $subpage ) {
		global $wgRequest, $wgOut, $wgUser;

		$this->mCanEdit = $wgUser->isAllowed( 'globalgrouppermissions' );

		$this->setHeaders();

		if ( strpos( $subpage, 'delete/' ) === 0 && $this->mCanEdit ) {
			$subpage = substr( $subpage, 7 );	// Remove delete/ part
			if ( is_numeric( $subpage ) ) {
				if ( $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) )
					$this->doDelete( $subpage );
				else
					$this->buildDeleteView( $subpage );
			} else {
				$this->buildMainView();
			}
		} else {
			if ( $subpage && !is_numeric( $subpage ) ) {
				$set = WikiSet::newFromName( $subpage );
				if ( $set ) {
					$subpage = $set->getID();
				} else {
					$wgOut->setPageTitle( wfMsg( 'error' ) );
					$error = wfMsgExt( 'centralauth-editset-notfound', array( 'escapenoentities' ), $subpage );
					$this->buildMainView( "<strong class='error'>{$error}</strong>" );
					return;
				}
			}

			if ( ( $subpage || $subpage === '0' ) && $this->mCanEdit && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
				$this->doSubmit( $subpage );
			} else if ( ( $subpage || $subpage === '0' ) && is_numeric( $subpage ) ) {
				$this->buildSetView( $subpage );
			} else {
				$this->buildMainView();
			}
		}
	}

	function buildMainView( $msg = '' ) {
		global $wgOut, $wgScript, $wgUser;
		$sk = $wgUser->getSkin();

		$msgPostfix = $this->mCanEdit ? 'rw' : 'ro';
		$legend = wfMsg( "centralauth-editset-legend-{$msgPostfix}" );
		$wgOut->addHTML( "<fieldset><legend>{$legend}</legend>" );
		if ( $msg )
			$wgOut->addHTML( $msg );
		$wgOut->addWikiMsg( "centralauth-editset-intro-{$msgPostfix}" );
		$wgOut->addHTML( '<ul>' );

		$sets = WikiSet::getAllWikiSets();
		foreach ( $sets as $set ) {
			$text = wfMsgExt( "centralauth-editset-item-{$msgPostfix}", array( 'parseinline' ), $set->getName(), $set->getID() );
			$wgOut->addHTML( "<li>{$text}</li>" );
		}

		if ( $this->mCanEdit ) {
			$target = SpecialPage::getTitleFor( 'WikiSets', '0' );
			$newlink = $sk->makeLinkObj( $target, wfMsgHtml( 'centralauth-editset-new' ) );
			$wgOut->addHTML( "<li>{$newlink}</li>" );
		}

		$wgOut->addHTML( '</ul></fieldset>' );
	}

	function buildSetView( $subpage, $error = false, $name = null, $type = null, $wikis = null, $reason = null ) {
		global $wgOut, $wgUser;

		$wgOut->setSubtitle( wfMsgExt( 'centralauth-editset-subtitle', 'parseinline' ) );

		$set = $subpage ? WikiSet::newFromID( $subpage ) : null;
		if ( !$name ) $name = $set ? $set->getName() : '';
		if ( !$type ) $type = $set ? $set->getType() : WikiSet::OPTIN;
		if ( !$wikis ) $wikis = implode( "\n", $set ? $set->getWikisRaw() : array() );
		else $wikis = implode( "\n", $wikis );
		$url = SpecialPage::getTitleFor( 'WikiSets', $subpage )->getLocalUrl();
		if ( $this->mCanEdit ) {
			$legend = wfMsgHtml( 'centralauth-editset-legend-' . ( $set ? 'edit' : 'new' ), $name );
		} else {
			$legend = wfMsgHtml( 'centralauth-editset-legend-view', $name );
		}

		$wgOut->addHTML( "<fieldset><legend>{$legend}</legend>" );

		if ( $set ) {
			$groups = $set->getRestrictedGroups();
			if ( $groups ) {
				$usage = "<ul>\n";
				foreach ( $groups as $group )
					$usage .= "<li>" . wfMsgExt( 'centralauth-editset-grouplink', array( 'parseinline' ), $group ) . "</li>\n";
				$usage .= "</ul>";
			} else {
				$usage = wfMsgWikiHtml( 'centralauth-editset-nouse' );
			}
		} else {
			$usage = '';
		}

		if ( $this->mCanEdit ) {
			if ( $error ) {
				$wgOut->addHTML( "<strong class='error'>{$error}</strong>" );
			}
			$wgOut->addHTML( "<form action='{$url}' method='post'>" );

			$form = array();
			$form['centralauth-editset-name'] = Xml::input( 'wpName', false, $name );
			if ( $usage ) {
				$form['centralauth-editset-usage'] = $usage;
			}
			$form['centralauth-editset-type'] = $this->buildTypeSelector( 'wpType', $type );
			$form['centralauth-editset-wikis'] = Xml::textarea( 'wpWikis', $wikis );
			$form['centralauth-editset-reason'] = Xml::input( 'wpReason', false, $reason );

			$wgOut->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit' ) );

			$edittoken = Xml::hidden( 'wpEditToken', $wgUser->editToken() );
			$wgOut->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
		} else {
			$form = array();
			$form['centralauth-editset-name'] = htmlspecialchars( $name );
			$form['centralauth-editset-usage'] = $usage;
			$form['centralauth-editset-type'] = wfMsg( "centralauth-editset-{$type}" );
			$form['centralauth-editset-wikis'] = $this->buildWikiList( $set->getWikisRaw() );

			$wgOut->addHTML( Xml::buildForm( $form ) );
		}
	}

	function buildTypeSelector( $name, $value ) {
		$select = new XmlSelect( $name, 'set-type', $value );
		foreach ( array( WikiSet::OPTIN, WikiSet::OPTOUT ) as $type ) {
			$select->addOption( wfMsg( "centralauth-editset-{$type}" ), $type );
		}
		return $select->getHTML();
	}

	function buildWikiList( $list ) {
		sort( $list );
		$html = '<ul>';
		foreach ( $list as $wiki ) {
			$html .= "<li>{$wiki}</li>";
		}
		$html .= '</ul>';
		return $html;
	}

	function buildDeleteView( $subpage ) {
		global $wgOut, $wgUser;
		$wgOut->setSubtitle( wfMsgExt( 'centralauth-editset-subtitle', 'parseinline' ) );

		$set = WikiSet::newFromID( $subpage );
		if ( !$set ) {
			$this->buildMainView( '<strong class="error">' . wfMsgHtml( 'centralauth-editset-notfound', $subpage ) . '</strong>' );
			return;
		}

		$legend = wfMsgHtml( 'centralauth-editset-legend-delete', $set->getName() );
		$form = array( 'centralauth-editset-reason' => Xml::input( 'wpReason' ) );
		$url = htmlspecialchars( SpecialPage::getTitleFor( 'WikiSets', "delete/{$subpage}" )->getLocalUrl() );
		$edittoken = Xml::hidden( 'wpEditToken', $wgUser->editToken() );

		$wgOut->addHTML( "<fieldset><legend>{$legend}</legend><form action='{$url}' method='post'>" );
		$wgOut->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit-delete' ) );
		$wgOut->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
	}

	function doSubmit( $id ) {
		global $wgRequest, $wgContLang;

		$name = $wgContLang->ucfirst( $wgRequest->getVal( 'wpName' ) );
		$type = $wgRequest->getVal( 'wpType' );
		$wikis = array_unique( preg_split( '/(\s+|\s*\W\s*)/', $wgRequest->getVal( 'wpWikis' ), -1, PREG_SPLIT_NO_EMPTY ) );
		$reason = $wgRequest->getVal( 'wpReason' );
		$set = WikiSet::newFromId( $id );

		if ( !Title::newFromText( $name ) ) {
			$this->buildSetView( $id, wfMsgHtml( 'centralauth-editset-badname' ), $name, $type, $wikis, $reason );
			return;
		}
		if ( ( !$id || $set->getName() != $name ) && WikiSet::newFromName( $name ) ) {
			$this->buildSetView( $id, wfMsgHtml( 'centralauth-editset-setexists' ), $name, $type, $wikis, $reason );
			return;
		}
		if ( !in_array( $type, array( WikiSet::OPTIN, WikiSet::OPTOUT ) ) ) {
			$this->buildSetView( $id, wfMsgHtml( 'centralauth-editset-badtype' ), $name, $type, $wikis, $reason );
			return;
		}
		if ( !$wikis ) {
			$this->buildSetView( $id, wfMsgHtml( 'centralauth-editset-nowikis' ), $name, $type, $wikis, $reason );
			return;
		}
		$badwikis = array();
		$allwikis = CentralAuthUser::getWikiList();
		foreach ( $wikis as $wiki ) {
			if ( !in_array( $wiki, $allwikis ) ) {
				$badwikis[] = $wiki;
			}
		}
		if ( $badwikis ) {
			$this->buildSetView( $id, wfMsgExt( 'centralauth-editset-badwikis', array( 'escapenoentities', 'parsemag' ),
						implode( ', ', $badwikis ), count( $badwikis ) ), $name, $type, $wikis, $reason );
			return;
		}

		if ( $set ) {
			$oldname = $set->getName();
			$oldtype = $set->getType();
			$oldwikis = $set->getWikisRaw();
		} else {
			$set = new WikiSet();
			$oldname = $oldtype = null; $oldwikis = array();
		}
		$set->setName( $name );
		$set->setType( $type );
		$set->setWikisRaw( $wikis );
		$set->commit();

		// Now logging
		$log = new LogPage( 'gblrights' );
		$title = SpecialPage::getTitleFor( 'WikiSets', $set->getID() );
		if ( !$oldname ) {
			// New set
			$log->addEntry( 'newset', $title, $reason, array( $name, $type, implode( ', ', $wikis ) ) );
		} else {
			if ( $oldname != $name ) {
				$log->addEntry( 'setrename', $title, $reason, array( $name, $oldname ) );
			}
			if ( $oldtype != $type ) {
				$log->addEntry( 'setnewtype', $title, $reason, array( $name, $oldtype, $type ) );
			}
			$added = implode( ', ', array_diff( $wikis, $oldwikis ) );
			$removed = implode( ', ', array_diff( $oldwikis, $wikis ) );
			if ( $added || $removed ) {
				$log->addEntry( 'setchange', $title, $reason, array( $name, $added, $removed ) );
			}
		}

		global $wgUser, $wgOut;
		$sk = $wgUser->getSkin();
		$returnLink = $sk->makeKnownLinkObj( $this->getTitle(), wfMsg( 'centralauth-editset-return' ) );

		$wgOut->addHTML( '<strong class="success">' . wfMsgHtml( 'centralauth-editset-success' ) . '</strong> <p>' . $returnLink . '</p>' );
	}

	function doDelete( $set ) {
		global $wgRequest, $wgContLang;

		$set = WikiSet::newFromID( $set );
		if ( !$set ) {
			$this->buildMainView( '<strong class="error">' . wfMsgHtml( 'centralauth-editset-notfound', $subpage ) . '</strong>' );
			return;
		}

		$reason = $wgRequest->getVal( 'wpReason' );
		$name = $set->getName();
		$set->delete();

		$title = SpecialPage::getTitleFor( 'WikiSets', $set->getID() );
		$log = new LogPage( 'gblrights' );
		$log->addEntry( 'deleteset', $title, $reason, array( $name ) );

		$this->buildMainView( '<strong class="success">' . wfMsg( 'centralauth-editset-success-delete' ) . '</strong>' );
	}
}
