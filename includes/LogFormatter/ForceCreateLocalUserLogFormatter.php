<?php

namespace MediaWiki\Extension\CentralAuth\LogFormatter;

use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\User\User;

/**
 * Handles the following log types:
 * - newusers/forcecreatelocal
 */
class ForceCreateLocalUserLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		return 'logentry-newusers-forcecreatelocal';
	}

	/** @inheritDoc */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		$target = User::newFromName( $this->entry->getTarget()->getText(), false );
		$params[2] = Message::rawParam( $this->makeUserLink( $target ) );
		$params[3] = $target->getName();
		return $params;
	}
}
