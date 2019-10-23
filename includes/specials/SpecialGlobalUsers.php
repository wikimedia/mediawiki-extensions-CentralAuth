<?php

use MediaWiki\MediaWikiServices;

class SpecialGlobalUsers extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage

		$pg = new GlobalUsersPager( $context, $par );
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
				$pg->setGroup( $groupTitle->getDBkey() );
			}
		}

		$rqUsername = MediaWikiServices::getInstance()->getContentLanguage()
			->ucfirst( $req->getVal( 'username' ) );
		if ( $rqUsername ) {
			$pg->setUsername( $rqUsername );
		}

		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
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
