<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Exception\ErrorPageError;

/**
 * Show an error when the CentralAuth database is locked/read-only
 * and the user tries to do something that requires CentralAuth
 * write access
 * @ingroup Exception
 */
class CentralAuthReadOnlyError extends ErrorPageError {

	/**
	 * @param string $reason
	 */
	public function __construct( $reason ) {
		parent::__construct(
			'centralauth-readonly',
			'centralauth-readonlytext',
			[ $reason ]
		);
	}
}
