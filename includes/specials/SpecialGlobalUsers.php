<?php

use MediaWiki\MediaWikiServices;

class SpecialGlobalUsers extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$context = new DerivativeContext( $this->getContext() );
		// Remove subpage
		$context->setTitle( $this->getPageTitle() );

		$pg = new GlobalUsersPager( $context, $par );
		$req = $this->getRequest();
		$rqGroup = $req->getVal( 'group' );
		$rqUsername = $contLang->ucfirst( $req->getVal( 'username' ) );

		if ( $par ) {
			if ( in_array( $par, CentralAuthUser::availableGlobalGroups() ) && $rqGroup === null ) {
				$pg->setGroup( $par );
			} elseif ( $rqUsername === null ) {
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

		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
		$pg->getPageHeader();
		$this->getOutput()->addHTML(
			$pg->getNavigationBar() .
			Html::rawElement( 'ul', [], $pg->getBody() ) .
			$pg->getNavigationBar()
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
