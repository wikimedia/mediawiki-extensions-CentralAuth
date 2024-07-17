<?php

namespace MediaWiki\Extension\CentralAuth\Widget;

use OOUI\TextInputWidget;

class GlobalUserInputWidget extends TextInputWidget {

	/** @var bool */
	protected $excludeNamed;

	/** @var bool */
	protected $excludeTemp;

	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		if ( isset( $config['excludenamed'] ) ) {
			$this->excludeNamed = $config['excludenamed'];
		}

		if ( isset( $config['excludetemp'] ) ) {
			$this->excludeTemp = $config['excludetemp'];
		}

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

		if ( $this->excludeNamed !== null ) {
			$config['excludenamed'] = $this->excludeNamed;
		}

		if ( $this->excludeTemp !== null ) {
			$config['excludetemp'] = $this->excludeTemp;
		}

		return parent::getConfig( $config );
	}
}
