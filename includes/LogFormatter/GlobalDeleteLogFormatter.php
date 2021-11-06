<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - globalauth/delete
 * - suppress/cadelete
 */
class GlobalDeleteLogFormatter extends LogFormatter {

	protected function getMessageKey() {
		return 'logentry-globalauth-delete';
	}

}
