<?php

use Wikimedia\Rdbms\IResultWrapper;

class CentralAuthUserArrayFromResult extends UserArrayFromResult {
	private $globalData;

	/**
	 * @param IResultWrapper $res
	 */
	public function __construct( IResultWrapper $res ) {
		parent::__construct( $res );

		if ( $res->numRows() == 0 ) {
			return;
		}

		/**
		 * Load global user data
		 */
		$names = [];
		foreach ( $res as $row ) {
			$names[] = $row->user_name;
		}
		$res->rewind();

		$dbr = CentralAuthUtils::getCentralReplicaDB();
		$caRes = $dbr->select(
			[ 'localuser', 'globaluser', 'renameuser_status' ],
			'*',
			[
				'gu_name' => $names,
				'lu_name=gu_name',
				'lu_wiki' => wfWikiID()
			],
			__METHOD__,
			[],
			[
				'renameuser_status' => [ 'LEFT OUTER JOIN', [ $dbr->makeList(
					[ 'ru_oldname=gu_name', 'ru_newname=gu_name' ],
					LIST_OR
				) ] ]
			]
		);
		$this->globalData = [];
		foreach ( $caRes as $row ) {
			$this->globalData[$row->gu_name] = $row;
		}
		wfDebug( __METHOD__ . ': got user data for ' . implode( ', ',
			array_keys( $this->globalData ) ) . "\n" );
	}

	/**
	 * @param stdClass|bool $row
	 */
	function setCurrent( $row ) {
		parent::setCurrent( $row );

		if ( $row !== false ) {
			if ( isset( $this->globalData[$row->user_name] ) ) {
				$caRow = $this->globalData[$row->user_name];

				// Like taken from GlobalRenameUserStatus::getNames
				$renameUser = [];
				if ( $caRow->ru_oldname ) {
					$renameUser = [ $caRow->ru_oldname, $caRow->ru_newname ];
				}

				CentralAuthUser::setInstance(
					$this->current, CentralAuthUser::newFromRow( $caRow, $renameUser )
				);
			} else {
				CentralAuthUser::setInstance(
					$this->current, CentralAuthUser::newUnattached( $row->user_name )
				);
			}
		}
	}
}
