<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use LogFormatter;

/**
 * Handles the following log types:
 * - newusers/forcecreatelocal
 */
class ForceCreateLocalUserLogFormatter extends LogFormatter {

	protected function getMessageKey() {
		return 'logentry-newusers-forcecreatelocal';
	}

}
