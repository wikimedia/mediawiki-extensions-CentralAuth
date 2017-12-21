<?php

class CentralAuthUserArray {

	/**
	 * @param ResultWrapper $res
	 * @return CentralAuthUserArrayFromResult
	 */
	static function newFromResult( $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}
}
