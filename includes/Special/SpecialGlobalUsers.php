<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use DerivativeContext;
use Html;
use IncludableSpecialPage;
use Language;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use Title;

class SpecialGlobalUsers extends IncludableSpecialPage {
	/** @var Language */
	private $contentLanguage;

	/** @var CentralAuthDatabaseManager */
	private $dbManager;

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/**
	 * @param Language $contentLanguage
	 * @param CentralAuthDatabaseManager $dbManager
	 * @param GlobalGroupLookup $globalGroupLookup
	 */
	public function __construct(
		Language $contentLanguage,
		CentralAuthDatabaseManager $dbManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalUsers' );
		$this->contentLanguage = $contentLanguage;
		$this->dbManager = $dbManager;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage

		$pg = new GlobalUsersPager( $context, $this->dbManager, $this->globalGroupLookup );
		$req = $this->getRequest();

		if ( $par !== null && $par !== '' ) {
			if ( in_array( $par, $this->globalGroupLookup->getDefinedGroups() ) ) {
				$pg->setGroup( $par );
			} else {
				$pg->setUsername( $par );
			}
		}

		$rqGroup = $req->getVal( 'group', '' );
		if ( $rqGroup !== '' ) {
			// XXX This is a horrible hack. We should not use Title for normalization. We need to
			// prefix the group name so that the first letter doesn't get uppercased.
			$groupTitle = Title::newFromText( "A/$rqGroup" );
			if ( $groupTitle ) {
				$pg->setGroup( ltrim( substr( $groupTitle->getDBkey(), 2 ), '_' ) );
			}
		}

		$rqUsername = $this->contentLanguage->ucfirst( $req->getVal( 'username', '' ) );
		if ( $rqUsername !== '' ) {
			$pg->setUsername( $rqUsername );
		}

		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
		if ( !$this->including() ) {
			$pg->getPageHeader();
		}

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
