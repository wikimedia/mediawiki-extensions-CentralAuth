<?php

namespace MediaWiki\Extension\CentralAuth\UsersToRename;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class UsersToRenameDatabaseUpdates {

	/**
	 * Notified via talk page
	 */
	public const NOTIFIED = 4;
	/**
	 * A redirect, temporarily skipped
	 */
	private const REDIRECT = 5;
	/**
	 * Renamed!
	 */
	private const RENAMED = 8;

	/**
	 * @var IDatabase
	 */
	private $db;

	public function __construct( IDatabase $db ) {
		$this->db = $db;
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 * @param int $status
	 */
	protected function updateStatus( $name, $wiki, $status ) {
		$this->db->update(
			'users_to_rename',
			[ 'utr_status' => $status ],
			[ 'utr_wiki' => $wiki, 'utr_name' => $name ],
			__METHOD__
		);
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function markNotified( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::NOTIFIED );
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function markRenamed( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::RENAMED );
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function markRedirectSkipped( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::REDIRECT );
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function remove( $name, $wiki ) {
		$this->db->delete(
			'users_to_rename',
			[ 'utr_wiki' => $wiki, 'utr_name' => $name ],
			__METHOD__
		);
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function insert( $name, $wiki ) {
		$this->batchInsert( [ [
			'name' => $name,
			'wiki' => $wiki
		] ] );
	}

	/**
	 * Batch insert rows
	 *
	 * @param array[] $info Array with array members that have 'name' and 'wiki' keys
	 */
	public function batchInsert( array $info ) {
		$rows = [];
		foreach ( $info as $row ) {
			$rows[] = [
				'utr_name' => $row['name'],
				'utr_wiki' => $row['wiki'],
			];
		}

		$this->db->insert(
			'users_to_rename',
			$rows,
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
	 * @param string $wiki
	 * @param int $status
	 * @param int $limit
	 *
	 * @return IResultWrapper
	 */
	public function findUsers( $wiki, $status, $limit ) {
		// @todo this shouldn't return prefixed field names
		return $this->db->select(
			'users_to_rename',
			[ 'utr_name', 'utr_wiki' ],
			[ 'utr_status' => $status, 'utr_wiki' => $wiki ],
			__METHOD__,
			[ 'LIMIT' => $limit ]
		);
	}
}
