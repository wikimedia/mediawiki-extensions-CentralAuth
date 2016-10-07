<?php

/**
 * Status handler for CentralAuth users being renamed.
 * This can work based on the new or old user name (can be constructed
 * from whatever is available)
 *
 * @license GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */

class GlobalRenameUserStatus implements IDBAccessObject {

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
	 * Get a Database object for the CentralAuth db
	 *
	 * @param int $type DB_REPLICA or DB_MASTER
	 *
	 * @return Database
	 */
	protected function getDB( $type = DB_REPLICA ) {
		if ( $type === DB_MASTER ) {
			return CentralAuthUtils::getCentralDB();
		} else {
			return CentralAuthUtils::getCentralSlaveDB();
		}
	}

	/**
	 * Get the where clause to query rows by either old or new name
	 *
	 * @param Database $db
	 *
	 * @return string
	 */
	private function getNameWhereClause( Database $db ) {
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
		$db = $this->getDB( $useMaster === 'master' ? DB_MASTER : DB_REPLICA );

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
	 * @param integer $flags IDBAccessObject flags
	 *
	 * @return array
	 */
	public function getStatuses( $flags = 0 ) {
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = $this->getDB( $index );

		$res = $db->select(
			'renameuser_status',
			array( 'ru_wiki', 'ru_status' ),
			array( $this->getNameWhereClause( $db ) ),
			__METHOD__,
			$options
		);

		$statuses = array();
		foreach ( $res as $row ) {
			$statuses[$row->ru_wiki] = $row->ru_status;
		}

		return $statuses;
	}

	/**
	 * Get a user's rename status for the current wiki.
	 *
	 * @param integer $flags IDBAccessObject flags
	 *
	 * @return string|null Null means no rename pending for this user on the current wiki (possibly
	 *   because it has finished already).
	 */
	public function getStatus( $flags = 0 ) {
		$statuses = $this->getStatuses( $flags );
		return isset( $statuses[wfWikiID()] ) ? $statuses[wfWikiID()] : null;
	}

	/**
	 * Set the rename status for a certain wiki
	 *
	 * @param string $wiki
	 * @param string $status
	 */
	public function updateStatus( $wiki, $status ) {
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

		$dbw->startAtomic( __METHOD__ );
		if ( $dbw->getType() === 'mysql' ) {
			// If there is duplicate key error, the RDBMs will rollback the INSERT statement.
			// http://dev.mysql.com/doc/refman/5.7/en/innodb-error-handling.html
			try {
				$dbw->insert( 'renameuser_status', $rows, __METHOD__ );
				$ok = true;
			} catch ( DBQueryError $e ) {
				$ok = false;
			}
		} else {
			// At least Postgres does not like continuing after errors. Only options are
			// ROLLBACK or COMMIT as is. We could use SAVEPOINT here, but it's not worth it.
			$keyConds = [];
			foreach ( $rows as $row ) {
				$key = [ 'ru_wiki' => $row->ru_wiki, 'ru_oldname' => $row->ru_oldname ];
				$keyConds[] = $dbw->makeList( $key, LIST_AND );
			}
			// (a) Do a locking check for conflicting rows on the unique key
			$ok = !$dbw->selectField(
				'renameuser_status',
				'1',
				$dbw->makeList( $keyConds, LIST_OR ),
				__METHOD__,
				[ 'FOR UPDATE' ]
			);
			// (b) Insert the new rows if no conflicts were found
			if ( $ok ) {
				$dbw->insert( 'renameuser_status', $rows, __METHOD__ );
			}
		}
		$dbw->endAtomic( __METHOD__ );

		return $ok;
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
