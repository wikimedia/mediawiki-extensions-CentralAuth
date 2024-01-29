<?php

namespace MediaWiki\Extension\CentralAuth\Widget;

use HTMLUserTextField;

/**
 * Implements a text input field for usernames.
 * Automatically auto-completes if using the OOUI display format.
 *
 * @since 1.33
 */
class HTMLGlobalUserTextField extends HTMLUserTextField {

	/** @inheritDoc */
	public function __construct( $params ) {
		parent::__construct( $params );
	}

	/** @inheritDoc */
	protected function getInputWidget( $params ) {
		return new GlobalUserInputWidget( $params );
	}

	/** @inheritDoc */
	protected function getOOUIModules() {
		return [ 'ext.widgets.GlobalUserInputWidget' ];
	}
}
