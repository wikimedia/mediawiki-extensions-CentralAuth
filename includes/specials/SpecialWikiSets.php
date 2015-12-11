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
	}

	/**
	 * @return String
	 */
	function getDescription() {
		return $this->msg( 'centralauth-editset' )->text();
	}

	function execute( $subpage ) {
		$this->mCanEdit = $this->getUser()->isAllowed( 'globalgrouppermissions' );

		$this->setHeaders();

		if ( strpos( $subpage, 'delete/' ) === 0 && $this->mCanEdit ) {
			$subpage = substr( $subpage, 7 );	// Remove delete/ part
			if ( is_numeric( $subpage ) ) {
				if ( $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
					$this->doDelete( $subpage );
				} else {
					$this->buildDeleteView( $subpage );
				}
			} else {
				$this->buildMainView();
			}
		} else {
			$newPage = ( $subpage === '0' && $this->mCanEdit );
			if ( $subpage ) {
				$set = is_numeric( $subpage ) ? WikiSet::newFromId( $subpage ) : WikiSet::newFromName( $subpage );
				if ( $set ) {
					$subpage = $set->getID();
				} else {
					$this->getOutput()->setPageTitle( $this->msg( 'error' ) );
					$error = $this->msg( 'centralauth-editset-notfound', $subpage )->escaped();
					$this->buildMainView( "<strong class='error'>{$error}</strong>" );
					return;
				}
			}

			if ( ( $subpage || $newPage ) && $this->mCanEdit && $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
				$this->doSubmit( $subpage );
			} elseif ( ( $subpage || $newPage ) && is_numeric( $subpage ) ) {
				$this->buildSetView( $subpage );
			} else {
				$this->buildMainView();
			}
		}
	}

	/**
	 * @param string $msg
	 */
	function buildMainView( $msg = '' ) {
		// Give grep a chance to find the usages: centralauth-editset-legend-rw, centralauth-editset-legend-ro
		$msgPostfix = $this->mCanEdit ? 'rw' : 'ro';
		$legend = $this->msg( "centralauth-editset-legend-{$msgPostfix}" )->text();
		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend>" );
		if ( $msg )
			$this->getOutput()->addHTML( $msg );
		// Give grep a chance to find the usages: centralauth-editset-intro-rw, centralauth-editset-intro-ro
		$this->getOutput()->addWikiMsg( "centralauth-editset-intro-{$msgPostfix}" );
		$this->getOutput()->addHTML( '<ul>' );

		// Give grep a chance to find the usages: centralauth-editset-item-rw, centralauth-editset-item-ro
		$sets = WikiSet::getAllWikiSets();
		/**
		 * @var $set WikiSet
		 */
		foreach ( $sets as $set ) {
			$text = $this->msg( "centralauth-editset-item-{$msgPostfix}", $set->getName(), $set->getID() )->parse();
			$this->getOutput()->addHTML( "<li>{$text}</li>" );
		}

		if ( $this->mCanEdit ) {
			$target = SpecialPage::getTitleFor( 'WikiSets', '0' );
			$newlink = Linker::link( $target, $this->msg( 'centralauth-editset-new' )->escaped() );
			$this->getOutput()->addHTML( "<li>{$newlink}</li>" );
		}

		$this->getOutput()->addHTML( '</ul></fieldset>' );
	}

	/**
	 * @param $subpage
	 * @param $error bool
	 * @param $name
	 * @param $type
	 * @param $wikis
	 * @param $reason
	 */
	function buildSetView( $subpage, $error = false, $name = null, $type = null, $wikis = null, $reason = null ) {
		global $wgLocalDatabases;

		$this->getOutput()->setSubtitle( $this->msg( 'centralauth-editset-subtitle' )->parse() );

		$set = ( $subpage || $subpage === '0' ) ? WikiSet::newFromID( $subpage ) : null;

		if ( !$name ) {
			$name = $set ? $set->getName() : '';
		}
		if ( !$type ) {
			$type = $set ? $set->getType() : WikiSet::OPTIN;
		}
		if ( !$wikis ) {
			$wikis = $set ? $set->getWikisRaw() : array();
		}
		sort( $wikis );
		$wikis = implode( "\n", $wikis );
		$url = SpecialPage::getTitleFor( 'WikiSets', $subpage )->getLocalUrl();
		if ( $this->mCanEdit ) {
			// Give grep a chance to find the usages:
			// centralauth-editset-legend-edit, centralauth-editset-legend-new
			$legend = $this->msg( 'centralauth-editset-legend-' . ( $set ? 'edit' : 'new' ), $name )->escaped();
		} else {
			$legend = $this->msg( 'centralauth-editset-legend-view', $name )->escaped();
		}

		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend>" );

		if ( $set ) {
			$groups = $set->getRestrictedGroups();
			if ( $groups ) {
				$usage = "<ul>\n";
				foreach ( $groups as $group ) {
					$usage .= "<li>" . $this->msg( 'centralauth-editset-grouplink', $group )->parse() . "</li>\n";
				}
				$usage .= "</ul>";
			} else {
				$usage = $this->msg( 'centralauth-editset-nouse' )->parseAsBlock();
			}
			$sortedWikis = $set->getWikisRaw();
			sort( $sortedWikis );
		} else {
			$usage = '';
			$sortedWikis = array();
		}

		# Make an array of the opposite list of wikis
		# (all databases *excluding* the defined ones)
		$restWikis = array();
		foreach( $wgLocalDatabases as $wiki ) {
			if( !in_array( $wiki, $sortedWikis ) ) {
				$restWikis[] = $wiki;
			}
		}
		sort( $restWikis );

		if ( $this->mCanEdit ) {
			if ( $error ) {
				$this->getOutput()->addHTML( "<strong class='error'>{$error}</strong>" );
			}
			$this->getOutput()->addHTML( "<form action='{$url}' method='post'>" );

			$form = array();
			$form['centralauth-editset-name'] = Xml::input( 'wpName', false, $name );
			if ( $usage ) {
				$form['centralauth-editset-usage'] = $usage;
			}
			$form['centralauth-editset-type'] = $this->buildTypeSelector( 'wpType', $type );
			$form['centralauth-editset-wikis'] = Xml::textarea( 'wpWikis', $wikis );
			$form['centralauth-editset-restwikis'] = Xml::textarea( 'wpRestWikis',
				implode( "\n", $restWikis ), 40, 5, array( 'readonly' => true ) );
			$form['centralauth-editset-reason'] = Xml::input( 'wpReason', 50, $reason );

			$this->getOutput()->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit' ) );

			$edittoken = Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
			$this->getOutput()->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
		} else {
			// Give grep a chance to find the usages: centralauth-editset-optin, centralauth-editset-optout
			$form = array();
			$form['centralauth-editset-name'] = htmlspecialchars( $name );
			$form['centralauth-editset-usage'] = $usage;
			$form['centralauth-editset-type'] = $this->msg( "centralauth-editset-{$type}" )->text();
			$form['centralauth-editset-wikis'] = self::buildTableByList( $sortedWikis, 3, array( 'width' => '100%' ) );
			$form['centralauth-editset-restwikis'] = self::buildTableByList( $restWikis, 3, array( 'width' => '100%' ) );

			$this->getOutput()->addHTML( Xml::buildForm( $form ) );
		}

		$this->showLogFragment( $subpage );
	}

	/**
	 * @param $name
	 * @param $value
	 * @return string
	 */
	function buildTypeSelector( $name, $value ) {
		// Give grep a chance to find the usages: centralauth-editset-optin, centralauth-editset-optout
		$select = new XmlSelect( $name, 'set-type', $value );
		foreach ( array( WikiSet::OPTIN, WikiSet::OPTOUT ) as $type ) {
			$select->addOption( $this->msg( "centralauth-editset-{$type}" )->text(), $type );
		}
		return $select->getHTML();
	}

	/**
	 * Builds a table of several columns, and divides the items of
	 * $list equally among each column. All items are escaped.
	 *
	 * Could in the future be replaced by CSS column-count.
	 *
	 * @param $list array
	 * @param $columns int: number of columns
	 * @param $tableAttribs array: <table> attributes
	 * @return string Table
	 */
	function buildTableByList( $list, $columns = 2, $tableAttribs = array() ) {
		if( !is_array( $list ) ) {
			return '';
		}
		$count = count( $list );
		if ( $count === 0 ) {
			return $this->msg( 'centralauth-editset-nowikis' )->parse();
		}
		# If there are less items than columns, limit the number of columns
		$columns = $count < $columns ? $count : $columns;
		$itemsPerCol = ceil( $count / $columns );
		$i = 0;
		$splitLists = array();
		while( $i < $columns ) {
			$splitLists[$i] = array_slice( $list, $itemsPerCol*$i, $itemsPerCol );
			$i++;
		}
		$body = '';
		foreach( $splitLists as $splitList ) {
			$body .= '<td width="' . round( 100 / $columns ) . '%"><ul>';
			foreach( $splitList as $listitem ) {
				$body .= Html::element( 'li', array(), $listitem );
			}
			$body .= '</ul></td>';
		}
		return Html::rawElement( 'table', $tableAttribs,
			'<tbody>' .
				Html::rawElement( 'tr', array( 'style' => 'vertical-align:top;' ), $body ) .
			'</tbody>'
		);
	}

	/**
	 * @param $subpage
	 * @return mixed
	 */
	function buildDeleteView( $subpage ) {
		$this->getOutput()->setSubtitle( $this->msg( 'centralauth-editset-subtitle' )->parse() );

		$set = WikiSet::newFromID( $subpage );
		if ( !$set ) {
			$this->buildMainView( '<strong class="error">' . $this->msg( 'centralauth-editset-notfound', $subpage )->escaped() . '</strong>' );
			return;
		}

		$legend = $this->msg( 'centralauth-editset-legend-delete', $set->getName() )->escaped();
		$form = array( 'centralauth-editset-reason' => Xml::input( 'wpReason' ) );
		$url = htmlspecialchars( SpecialPage::getTitleFor( 'WikiSets', "delete/{$subpage}" )->getLocalUrl() );
		$edittoken = Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );

		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend><form action='{$url}' method='post'>" );
		$this->getOutput()->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit-delete' ) );
		$this->getOutput()->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
	}

	/**
	 * @param $id
	 * @return mixed
	 */
	function doSubmit( $id ) {
		global $wgContLang;

		$name = $wgContLang->ucfirst( $this->getRequest()->getVal( 'wpName' ) );
		$type = $this->getRequest()->getVal( 'wpType' );
		$wikis = array_unique( preg_split( '/(\s+|\s*\W\s*)/', $this->getRequest()->getVal( 'wpWikis' ), -1, PREG_SPLIT_NO_EMPTY ) );
		$reason = $this->getRequest()->getVal( 'wpReason' );
		$set = WikiSet::newFromId( $id );

		if ( !Title::newFromText( $name ) ) {
			$this->buildSetView( $id, $this->msg( 'centralauth-editset-badname' )->escaped(), $name, $type, $wikis, $reason );
			return;
		}
		if ( ( !$id || $set->getName() != $name ) && WikiSet::newFromName( $name ) ) {
			$this->buildSetView( $id, $this->msg( 'centralauth-editset-setexists' )->escaped(), $name, $type, $wikis, $reason );
			return;
		}
		if ( !in_array( $type, array( WikiSet::OPTIN, WikiSet::OPTOUT ) ) ) {
			$this->buildSetView( $id, $this->msg( 'centralauth-editset-badtype' )->escaped(), $name, $type, $wikis, $reason );
			return;
		}
		if ( !$wikis ) {
			$this->buildSetView( $id, $this->msg( 'centralauth-editset-nowikis' )->escaped(), $name, $type, $wikis, $reason );
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
			$this->buildSetView( $id, $this->msg(
				'centralauth-editset-badwikis',
				implode( ', ', $badwikis ) )
				->numParams( count( $badwikis ) )
				->escaped(),
				$name, $type, $wikis, $reason
			);
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
		$set->saveToDB();

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

		$returnLink = Linker::linkKnown( $this->getPageTitle(), $this->msg( 'centralauth-editset-return' )->escaped() );

		$this->getOutput()->addHTML( '<strong class="success">' . $this->msg( 'centralauth-editset-success' )->escaped() . '</strong> <p>' . $returnLink . '</p>' );
	}

	/**
	 * @param $set
	 * @return mixed
	 */
	function doDelete( $set ) {
		$set = WikiSet::newFromID( $set );
		if ( !$set ) {
			$this->buildMainView( '<strong class="error">' . $this->msg( 'centralauth-editset-notfound', $set )->escaped() . '</strong>' );
			return;
		}

		$reason = $this->getRequest()->getVal( 'wpReason' );
		$name = $set->getName();
		$set->delete();

		$title = SpecialPage::getTitleFor( 'WikiSets', $set->getID() );
		$log = new LogPage( 'gblrights' );
		$log->addEntry( 'deleteset', $title, $reason, array( $name ) );

		$this->buildMainView( '<strong class="success">' . $this->msg( 'centralauth-editset-success-delete' )->escaped() . '</strong>' );
	}

	/**
	 * @param string $number
	 */
	protected function showLogFragment( $number ) {
		$title = SpecialPage::getTitleFor( 'WikiSets', $number );
		$logPage = new LogPage( 'gblrights' );
		$out = $this->getOutput();
		$out->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $out, 'gblrights', $title->getPrefixedText() );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
