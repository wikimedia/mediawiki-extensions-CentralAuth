<?php

class SpecialGlobalUsers extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	public function execute( $par ) {
		global $wgContLang;
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$pg = new GlobalUsersPager( $this->getContext(), $par );
		$req = $this->getRequest();

		if ( $par ) {
			if ( in_array( $par, CentralAuthUser::availableGlobalGroups() ) ) {
				$pg->setGroup( $par );
			} else {
				$pg->setUsername( $par );
			}
		}

		$rqGroup = $req->getVal( 'group' );
		if ( $rqGroup ) {
				$groupTitle = Title::newFromText( $rqGroup );
				if ( $groupTitle ) {
					$pg->setGroup( $groupTitle->getUserCaseDBKey() );
				}
		}

		$rqUsername = $wgContLang->ucfirst( $req->getVal( 'username' ) );
		if ( $rqUsername ) {
			$pg->setUsername( $rqUsername );
		}

		$this->getOutput()->addModuleStyles( 'ext.centralauth.special-misc.styles' );
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
