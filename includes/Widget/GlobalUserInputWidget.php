<?php

namespace MediaWiki\Extension\CentralAuth\Widget;

use OOUI\TextInputWidget;

class GlobalUserInputWidget extends TextInputWidget {

	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'mw-widget-userInputWidget' ] );
	}

	/** @inheritDoc */
	protected function getJavaScriptClassName() {
		return 'mw.widgets.GlobalUserInputWidget';
	}

	/** @inheritDoc */
	public function getConfig( &$config ) {
		$config['$overlay'] = true;
		return parent::getConfig( $config );
	}
}
