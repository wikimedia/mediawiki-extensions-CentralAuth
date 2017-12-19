<?php

use Wikimedia\Rdbms\ResultWrapper;

class CentralAuthUserArray {

	/**
	 * @param ResultWrapper $res
	 * @return CentralAuthUserArrayFromResult
	 */
	static function newFromResult( ResultWrapper $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}

}
