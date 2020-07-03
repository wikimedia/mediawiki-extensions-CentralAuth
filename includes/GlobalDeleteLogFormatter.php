<?php

class GlobalDeleteLogFormatter extends LogFormatter {
	protected function getMessageKey() {
		return 'logentry-globalauth-delete';
	}
}