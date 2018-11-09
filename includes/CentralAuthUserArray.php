<?php

use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthUserArray {

	/**
	 * @param IResultWrapper $res
	 * @return CentralAuthUserArrayFromResult
	 */
	public static function newFromResult( IResultWrapper $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}
}
