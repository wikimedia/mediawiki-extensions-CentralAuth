<?php

use MediaWiki\MediaWikiServices;

class SpecialGlobalUsers extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	public function execute( $par ) {
		global $wgContLang;
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage

		$pg = new GlobalUsersPager( $context, $par );
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
			// XXX This is a horrible hack. We should not use Title for normalization. We need to
			// prefix the group name so that the first letter doesn't get uppercased.
			$groupTitle = Title::newFromText( "A/$rqGroup" );
			if ( $groupTitle ) {
				$pg->setGroup( ltrim( substr( $groupTitle->getDBkey(), 2 ), '_' ) );
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
