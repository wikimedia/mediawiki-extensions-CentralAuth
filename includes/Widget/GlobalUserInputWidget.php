<?php

class GlobalUserInputWidget extends \OOUI\TextInputWidget {

	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'mw-widget-userInputWidget' ] );
	}

	protected function getJavaScriptClassName() {
		return 'mw.widgets.GlobalUserInputWidget';
	}

	public function getConfig( &$config ) {
		$config['$overlay'] = true;
		return parent::getConfig( $config );
	}
}
