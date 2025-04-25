<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use MediaWiki\Logging\LogFormatter;

/**
 * Handles the following log types:
 * - globalauth/delete
 * - suppress/cadelete
 */
class GlobalDeleteLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		return 'logentry-globalauth-delete';
	}

}
