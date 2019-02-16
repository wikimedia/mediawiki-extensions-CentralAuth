<?php

use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthUserArray {

	/**
	 * @param IResultWrapper $res
	 * @return CentralAuthUserArrayFromResult
	 */
	static function newFromResult( IResultWrapper $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}
}
