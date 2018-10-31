<?php

/**
 * Implements a text input field for user names.
 * Automatically auto-completes if using the OOUI display format.
 *
 * @since 1.33
 */

class HTMLGlobalUserTextField extends HTMLUserTextField {
	public function __construct( $params ) {

		parent::__construct( $params );
	}

	protected function getInputWidget( $params ) {
		return new GlobalUserInputWidget( $params );
	}

	protected function getOOUIModules() {
		return [ 'ext.widgets.GlobalUserInputWidget' ];
	}

}