<?php

/**
 * Status handler for CentralAuth users being renamed.
 * This can work based on the new or old user name (can be constructed
 * from whatever is available)
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */

class GlobalRenameUserStatus {

	/**
	 * Either old or new name of the user
	 *
	 * @var string
	 */
	private $name;

	/**
	 * @param string $name Either old or new name of the user
	 */
	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * Get a DatabaseBase object for the CentralAuth db
	 *
	 * @param int $type DB_SLAVE or DB_MASTER
	 *
	 * @return DatabaseBase
	 */
	protected function getDB( $type = DB_SLAVE ) {
		if ( $type === DB_MASTER ) {
			return CentralAuthUtils::getCentralDB();
		} else {
			return CentralAuthUtils::getCentralSlaveDB();
		}
	}

	/**
	 * Get the where clause to query rows by either old or new name
	 *
	 * @param DatabaseBase $db
	 *
	 * @return string
	 */
	private function getNameWhereClause( DatabaseBase $db ) {
		return $db->makeList(
			array( 'ru_oldname' => $this->name, 'ru_newname' => $this->name ),
			LIST_OR
		);
	}

	/**
	 * Get the old and new name of a user being renamed (or an emtpy array if
	 * no rename is happening).
	 *
	 * This is useful if we have a user specified name, but don't know
	 * whether it's the old or new name.
	 *
	 * @param string|null $wiki Only look for renames on the given wiki.
	 * @param string|null $useMaster Set to 'master' to query the master db
	 *
	 * @return array (oldname, newname)
	 */
	public function getNames( $wiki = null, $useMaster = null ) {
		$db = $this->getDB( $useMaster === 'master' ? DB_MASTER : DB_SLAVE );

		$where = array( $this->getNameWhereClause( $db ) );

		if ( $wiki ) {
			$where['ru_wiki'] = $wiki;
		}

		$names = $db->selectRow(
			'renameuser_status',
			array( 'ru_oldname', 'ru_newname' ),
			$where,
			__METHOD__
		);

		if ( !$names ) {
			return array();
		}

		return array(
			$names->ru_oldname,
			$names->ru_newname
		);
	}

	/**
	 * Get a user's rename status for all wikis.
	 * Returns an array ( wiki => status )
	 *
	 * @return array
	 */
	public function getStatuses() {
		$dbr = $this->getDB();

		$res = $dbr->select(
			'renameuser_status',
			array( 'ru_wiki', 'ru_status' ),
			array( $this->getNameWhereClause( $dbr ) ),
			__METHOD__
		);

		$statuses = array();
		foreach ( $res as $row ) {
			$statuses[$row->ru_wiki] = $row->ru_status;
		}

		return $statuses;
	}

	/**
	 * Set the rename status for a certain wiki
	 *
	 * @param string $wiki
	 * @param string $status
	 */
	public function setStatus( $wiki, $status ) {
		$dbw = $this->getDB( DB_MASTER );
		$nameWhere = $this->getNameWhereClause( $dbw ); // Can be inlined easily once we require more than 5.3
		$fname = __METHOD__;

		$dbw->onTransactionPreCommitOrIdle(
			function() use( $dbw, $status, $wiki, $nameWhere, $fname ) {
				$dbw->update(
					'renameuser_status',
					array( 'ru_status' => $status ),
					array( $nameWhere, 'ru_wiki' => $wiki ),
					$fname
				);
			}
		);
	}

	/**
	 * @param array $rows
	 *
	 * @return bool
	 */
	public function setStatuses( array $rows ) {
		$dbw = $this->getDB( DB_MASTER );

		$dbw->begin( __METHOD__ );
		$dbw->insert(
			'renameuser_status',
			$rows,
			__METHOD__,
			array( 'IGNORE' )
		);
		if ( $dbw->affectedRows() !== count( $rows ) ) {
			// Race condition, the rename was already started
			$dbw->rollback( __METHOD__ );
			return false;
		}
		$dbw->commit( __METHOD__ );

		return true;
	}

	/**
	 * Mark the process as done for a wiki (=> delete the renameuser_status row)
	 *
	 * @param string $wiki
	 */
	public function done( $wiki ) {
		$dbw = $this->getDB( DB_MASTER );
		$nameWhere = $this->getNameWhereClause( $dbw ); // Can be inlined easily once we require more than 5.3
		$fname = __METHOD__;

		$dbw->onTransactionPreCommitOrIdle(
			function() use( $dbw, $wiki, $nameWhere, $fname ) {
				$dbw->delete(
					'renameuser_status',
					array( $nameWhere, 'ru_wiki' => $wiki ),
					$fname
				);
			}
		);
	}
}
