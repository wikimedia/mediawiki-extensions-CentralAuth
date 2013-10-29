<?php

class SpecialRenameProgress extends FormSpecialPage {
	function __construct() {
		parent::__construct( 'RenameProgress' );
	}

	function getFormFields() {
		return array(
			'username' => array(
				'id' => 'mw-renameprogress-username',
				'label-message' => 'centralauth-rename-progress-username',
				'type' => 'text',
				'name' => 'username',
				'default' => $this->getRequest()->getVal( 'username', $this->par ),
			)
		);
	}

	function alterForm( HTMLForm $form ) {
		$form->setMethod( 'GET' );
		$form->setSubmitText( $this->msg( 'centralauth-rename-viewprogress')->text() );
	}

	function showLogExtract( $name ) {
		$caTitle = Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $name );
		$out = $this->getOutput();
		LogEventsList::showLogExtract( $out, 'renameuser', $caTitle, '', array(
			'showIfEmpty' => false,
			'wrap' => Xml::fieldset( $this->msg( 'centralauth-rename-progress-logs-fieldset' )->text(), '$1' ),
		) );
	}

	function onSubmit( array $data ) {
		$name = $data['username'];
		if ( !$name ) {
			return false;
		}
		$out = $this->getOutput();
		$dbr = CentralAuthUser::getCentralSlaveDB();
		$res = $dbr->select(
			'renameuser_status',
			array( 'ru_wiki', 'ru_status', 'ru_newname' ),
			array( $dbr->makeList( array( 'ru_oldname' => $name, 'ru_newname' => $name ), LIST_OR ) ),
			__METHOD__
		);

		if ( $res->numRows() === 0 ) {
			$out->addWikiMsg( 'centralauth-rename-notinprogress', $name );
			$this->getForm()->displayForm( false );
			$this->showLogExtract( $name );
			return true;
		}
		$this->getForm()->displayForm( false );
		$statuses = array();
		$newname = null;
		foreach ( $res as $row ) {
			$statuses[$row->ru_wiki] = $row->ru_status;
			$newname = $row->ru_newname;
		}
		// $newname will always be defined since we check
		// for 0 result rows above
		$caUser = new CentralAuthUser( $newname );
		$attached = $caUser->listAttached();
		foreach ( $attached as $wiki ) {
			// If it's not in the db table, and there is
			// an attached acount, assume it's done.
			if ( !isset( $statuses[$wiki] ) ) {
				$statuses[$wiki] = 'done';
			}
		}
		ksort( $statuses );
		$table = Html::openElement( 'table', array( 'class' => 'wikitable sortable' ) );
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', array(), $this->msg( 'centralauth-rename-table-domain' )->text() );
		$table .= Html::element( 'th', array(), $this->msg( 'centralauth-rename-table-status' )->text() );
		$table .= Html::closeElement( 'tr' );
		foreach( $statuses as $wiki => $status ) {
			$table .= Html::openElement( 'tr' );
			$table .= Html::element( 'td', array(), WikiMap::getWiki( $wiki )->getDisplayName() );
			// Messages used: centralauth-rename-table-status-inprogress
			// centralauth-rename-table-status-queued, centralauth-rename-table-status-done
			$table .= Html::rawElement( 'td', array(), $this->msg( "centralauth-rename-table-status-$status" )->parse() );
			$table .= Html::closeElement( 'tr' );
		}
		$table .= Html::closeElement( 'table' );
		$fieldset = Xml::fieldset( $this->msg( 'centralauth-rename-progress-fieldset' )->text(), $table );

		$this->showLogExtract( $newname );
		$out->addHTML( $fieldset );
		return true;
	}
}
