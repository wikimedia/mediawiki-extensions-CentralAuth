<?php

use Wikimedia\Rdbms\ResultWrapper;

class CentralAuthUserArray {

	/**
	 * @param ResultWrapper $res
	 * @return CentralAuthUserArrayFromResult
	 */
	public static function newFromResult( ResultWrapper $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}
}
