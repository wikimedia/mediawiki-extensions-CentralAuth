<?php

class SpecialGlobalUsers extends SpecialPage {

	function __construct() {
		wfLoadExtensionMessages('SpecialCentralAuth');
		parent::__construct( 'GlobalUsers', 'centralauth-listusers' );
	}

	function execute( $par ) {
		global $wgOut;
		$this->setHeaders();

		$pg = new GlobalUsersPager();
		$wgOut->addHTML( $pg->getNavigationBar() );
		$wgOut->addHTML( '<ul>' . $pg->getBody() . '</ul>' );
		$wgOut->addHTML( $pg->getNavigationBar() );
	}
}

class GlobalUsersPager extends AlphabeticPager {
	function __construct() {
		parent::__construct();
		$this->mDb = CentralAuthUser::getCentralSlaveDB();
	}

	function getIndexField() {
		return 'gu_name';
	}
	
	function getQueryInfo() {
		$localwiki = wfWikiID();
		return array(
			'tables' => " globaluser LEFT JOIN localuser ON gu_name = lu_name AND lu_dbname = '{$localwiki}' ",
			'fields' => array( 'gu_name', 'gu_locked', 'lu_attached_method' ),
			'conds' => array( 'gu_hidden' => 0 ),
		);
	}

	function formatRow( $row ) {
		$user = htmlspecialchars( $row->gu_name );
		$info = array();
		if( $row->gu_locked )
			$info[] = wfMsgHtml( 'centralauth-listusers-locked' );
		if( $row->lu_attached_method ) {
			$userPage = Title::makeTitle( NS_USER, $row->gu_name );
			$text = wfMsgHtml( 'centralauth-listusers-attached' );
			$info[] = $this->getSkin()->makeLinkObj( $userPage, $text );
		} else {
			$info[] = wfMsgHtml( 'centralauth-listusers-nolocal' );
		}
		$info = implode( ', ', $info );
		return '<li>' . wfSpecialList( $user, $info ) . '</li>';
	}

	function getBody() {
		if (!$this->mQueryDone) {
			$this->doQuery();
		}
		$batch = new LinkBatch;

		$this->mResult->rewind();

		while ( $row = $this->mResult->fetchObject() ) {
			$batch->addObj( Title::makeTitleSafe( NS_USER, $row->gu_name ) );
		}
		$batch->execute();
		$this->mResult->rewind();
		return parent::getBody();
	}
}
