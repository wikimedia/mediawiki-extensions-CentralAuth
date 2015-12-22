<?php

class CentralAuthUserArray {

	/**
	 * @param $res ResultWrapper
	 * @return CentralAuthUserArrayFromResult
	 */
	static function newFromResult( $res ) {
		return new CentralAuthUserArrayFromResult( $res );
	}
}

class CentralAuthUserArrayFromResult extends UserArrayFromResult {
	var $globalData;

	/**
	 * @param $res ResultWrapper
	 */
	function __construct( $res ) {
		parent::__construct( $res );

		if ( $res->numRows() == 0 ) {
			return;
		}

		/**
		 * Load global user data
		 */
		$names = array();
		foreach ( $res as $row ) {
			$names[] = $row->user_name;
		}
		$res->rewind();

		$dbr = CentralAuthUtils::getCentralSlaveDB();
		$caRes = $dbr->select(
			array( 'localuser', 'globaluser', 'renameuser_status' ),
			'*',
			array(
				'gu_name' => $names,
				'lu_name=gu_name',
				'lu_wiki' => wfWikiID()
			),
			__METHOD__,
			array(),
			array(
				'renameuser_status' => array( 'LEFT OUTER JOIN', array( $dbr->makeList(
					array( 'ru_oldname=gu_name', 'ru_newname=gu_name' ),
					LIST_OR
				) ) )
			)
		);
		$this->globalData = array();
		foreach ( $caRes as $row ) {
			$this->globalData[$row->gu_name] = $row;
		}
		wfDebug( __METHOD__ . ': got user data for ' . implode( ', ',
			array_keys( $this->globalData ) ) . "\n" );
	}

	/**
	 * @param $row
	 */
	function setCurrent( $row ) {
		parent::setCurrent( $row );

		if ( $row !== false ) {
			if ( isset( $this->globalData[$row->user_name] ) ) {
				$caRow = $this->globalData[$row->user_name];

				// Like taken from GlobalRenameUserStatus::getNames
				$renameUser = array();
				if ( $caRow->ru_oldname ) {
					$renameUser = array( $caRow->ru_oldname, $caRow->ru_newname );
				}

				$this->current->centralAuthObj = CentralAuthUser::newFromRow( $caRow, $renameUser );
			} else {
				$this->current->centralAuthObj = CentralAuthUser::newUnattached( $row->user_name );
			}
		}
	}
}
