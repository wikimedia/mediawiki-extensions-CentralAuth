<?php

class SpecialUsersWhoWillBeRenamed extends SpecialPage {
	public function __construct() {
		parent::__construct( 'UsersWhoWillBeRenamed' );
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		$pager = new UsersWhoWillBeRenamedPager( $this, $this->getContext() );
		$this->getOutput()->addWikiMsg( 'centralauth-uwbr-intro' );
		$this->getOutput()->addParserOutput( $pager->getFullOutput() );
		$this->getOutput()->addModuleStyles( 'mediawiki.interface.helpers.styles' );
	}
}
