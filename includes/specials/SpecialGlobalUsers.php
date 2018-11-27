<?php

class SpecialGlobalUsers extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	function execute( $par ) {
		global $wgContLang;
		$this->setHeaders();

		$pg = new GlobalUsersPager( $this->getContext(), $par );
		$req = $this->getRequest();
		$rqGroup = $req->getVal( 'group' );
		$rqUsername = $wgContLang->ucfirst( $req->getVal( 'username' ) );

		if ( $par ) {
			if ( in_array( $par, CentralAuthUser::availableGlobalGroups() ) && is_null( $rqGroup ) ) {
				$pg->setGroup( $par );
			} elseif ( is_null( $rqUsername ) ) {
				$pg->setUsername( $par );
			}
		}

		if ( $rqGroup ) {
				$groupTitle = Title::newFromText( $rqGroup );
				if ( $groupTitle ) {
					$pg->setGroup( $groupTitle->getUserCaseDBKey() );
				}
		}

		if ( $rqUsername ) {
			$pg->setUsername( $rqUsername );
		}

		$this->getOutput()->addModuleStyles( 'ext.centralauth.globalusers' );
		$pg->getPageHeader();
		$this->getOutput()->addHTML(
			$pg->getNavigationBar() .
			Html::rawElement( 'ul', [], $pg->getBody() ) .
			$pg->getNavigationBar()
		);
	}

	protected function getGroupName() {
		return 'users';
	}
}
