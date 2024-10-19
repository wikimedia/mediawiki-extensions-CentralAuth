<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\Authority;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Status handler for CentralAuth users being renamed.
 * This can work based on the new or old user name (can be constructed
 * from whatever is available)
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserStatus {

	private CentralAuthDatabaseManager $databaseManager;

	private string $name;

	/**
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param string $name Either old or new name of the user
	 */
	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		string $name
	) {
		$this->databaseManager = $databaseManager;
		$this->name = $name;
	}

	/**
	 * Get the where clause to query rows by either old or new name
	 *
	 * @param IReadableDatabase $db
	 * @return IExpression
	 */
	private function getNameWhereClause( IReadableDatabase $db ): IExpression {
		return $db->expr( 'ru_oldname', '=', $this->name )->or( 'ru_newname', '=', $this->name );
	}

	/**
	 * Get the old and new name of a user being renamed (or an empty array if
	 * no rename is happening).
	 *
	 * This is useful if we have a user specified name, but don't know
	 * whether it's the old or new name.
	 *
	 * @param string|null $wiki Only look for renames on the given wiki.
	 * @param int $recency IDBAccessObject flags
	 *
	 * @return string[] (oldname, newname)
	 */
	public function getNames( ?string $wiki = null, int $recency = IDBAccessObject::READ_NORMAL ): array {
		$db = $this->databaseManager->getCentralDBFromRecency( $recency );

		$where = [ $this->getNameWhereClause( $db ) ];

		if ( $wiki ) {
			$where['ru_wiki'] = $wiki;
		}

		$names = $db->newSelectQueryBuilder()
			->select( [ 'ru_oldname', 'ru_newname' ] )
			->from( 'renameuser_status' )
			->where( $where )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$names ) {
			return [];
		}

		return [
			$names->ru_oldname,
			$names->ru_newname
		];
	}

	/**
	 * Get a user's rename status for all wikis.
	 * Returns an array ( wiki => status )
	 *
	 * @param int $recency IDBAccessObject flags
	 *
	 * @return string[]
	 */
	public function getStatuses( int $recency = IDBAccessObject::READ_NORMAL ): array {
		$db = $this->databaseManager->getCentralDBFromRecency( $recency );

		$res = $db->newSelectQueryBuilder()
			->select( [ 'ru_wiki', 'ru_status' ] )
			->from( 'renameuser_status' )
			->where( [ $this->getNameWhereClause( $db ) ] )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchResultSet();

		$statuses = [];
		foreach ( $res as $row ) {
			$statuses[$row->ru_wiki] = $row->ru_status;
		}

		return $statuses;
	}

	/**
	 * Get a user's rename status for the current wiki.
	 *
	 * @param int $recency IDBAccessObject flags
	 *
	 * @return string|null Null means no rename pending for this user on the current wiki (possibly
	 *   because it has finished already).
	 */
	public function getStatus( int $recency = IDBAccessObject::READ_NORMAL ): ?string {
		$statuses = $this->getStatuses( $recency );
		return $statuses[WikiMap::getCurrentWikiId()] ?? null;
	}

	/**
	 * Set the rename status for a certain wiki
	 *
	 * @param string $wiki
	 * @param string $status
	 */
	public function updateStatus( $wiki, $status ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();
		$fname = __METHOD__;

		$dbw->onTransactionPreCommitOrIdle(
			function () use ( $dbw, $status, $wiki, $fname ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'renameuser_status' )
					->set( [ 'ru_status' => $status ] )
					->where( [ $this->getNameWhereClause( $dbw ), 'ru_wiki' => $wiki ] )
					->caller( $fname )
					->execute();
			},
			$fname
		);
	}

	/**
	 * @param array $rows
	 *
	 * @return bool
	 */
	public function setStatuses( array $rows ): bool {
		if ( !$rows ) {
			return false;
		}

		$dbw = $this->databaseManager->getCentralPrimaryDB();

		$dbw->startAtomic( __METHOD__ );
		if ( $dbw->getType() === 'mysql' ) {
			// If there is duplicate key error, the RDBMs will rollback the INSERT statement.
			// http://dev.mysql.com/doc/refman/5.7/en/innodb-error-handling.html
			try {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'renameuser_status' )
					->rows( $rows )
					->caller( __METHOD__ )
					->execute();
				$ok = true;
			} catch ( DBQueryError $e ) {
				$ok = false;
			}
		} else {
			// At least Postgres does not like continuing after errors. Only options are
			// ROLLBACK or COMMIT as is. We could use SAVEPOINT here, but it's not worth it.
			$keyConds = [];
			foreach ( $rows as $row ) {
				$keyConds[] = $dbw->expr( 'ru_wiki', '=', $row->ru_wiki )
					->and( 'ru_oldname', '=', $row->ru_oldname );
			}
			// (a) Do a locking check for conflicting rows on the unique key
			$ok = !$dbw->newSelectQueryBuilder()
				->select( '1' )
				->from( 'renameuser_status' )
				->where( $dbw->orExpr( $keyConds ) )
				->forUpdate()
				->caller( __METHOD__ )
				->fetchField();
			// (b) Insert the new rows if no conflicts were found
			if ( $ok ) {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'renameuser_status' )
					->rows( $rows )
					->caller( __METHOD__ )
					->execute();
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
	public function done( string $wiki ): void {
		$dbw = $this->databaseManager->getCentralPrimaryDB();
		$fname = __METHOD__;

		$dbw->onTransactionPreCommitOrIdle(
			function () use ( $dbw, $wiki, $fname ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'renameuser_status' )
					->where( [ $this->getNameWhereClause( $dbw ), 'ru_wiki' => $wiki ] )
					->caller( $fname )
					->execute();
			},
			$fname
		);
	}

	/**
	 * Get a list of all currently in progress renames
	 *
	 * @param Authority $performer User viewing the list, for permissions checks
	 * @return string[] old username => new username
	 */
	public static function getInProgressRenames( Authority $performer ): array {
		$dbr = CentralAuthServices::getDatabaseManager()->getCentralReplicaDB();

		$qb = $dbr->newSelectQueryBuilder();

		$qb->select( [ 'ru_oldname', 'ru_newname' ] )
			->distinct()
			->from( 'renameuser_status' );

		if ( !$performer->isAllowed( 'centralauth-suppress' ) ) {
			$qb->join( 'globaluser', null, 'gu_name=ru_newname' );
			$qb->where( [ "gu_hidden_level" => CentralAuthUser::HIDDEN_LEVEL_NONE ] );
		}

		$res = $qb
			->caller( __METHOD__ )
			->fetchResultSet();

		$ret = [];
		foreach ( $res as $row ) {
			$ret[$row->ru_oldname] = $row->ru_newname;
		}

		return $ret;
	}
}
