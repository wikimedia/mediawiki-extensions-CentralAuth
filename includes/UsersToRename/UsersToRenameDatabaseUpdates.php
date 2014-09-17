<?php

class UsersToRenameDatabaseUpdates {

	const NOTIFIED = 4;
	const RENAMED = 8;

	/**
	 * @var DatabaseBase
	 */
	private $db;

	public function __construct( DatabaseBase $db ) {
		$this->db = $db;
	}

	public function markRenamed( $name, $wiki ) {
		$this->db->update(
			'users_to_rename',
			array( 'utr_status' => self::RENAMED ),
			array( 'utr_wiki' => $wiki, 'utr_name' => $name ),
			__METHOD__
		);
	}

	public function remove( $name, $wiki ) {
		$this->db->delete(
			'users_to_rename',
			array( 'utr_wiki' => $wiki, 'utr_name' => $name ),
			__METHOD__
		);
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function insert( $name, $wiki ) {
		$this->batchInsert( array( array(
			'name' => $name,
			'wiki' => $wiki
		) ) );
	}

	/**
	 * Batch insert rows
	 *
	 * @param array $info Array with array members that have 'name' and 'wiki' keys
	 */
	public function batchInsert( array $info ) {
		$rows = array();
		foreach ( $info as $row ) {
			$rows[] = array(
				'utr_name' => $row['name'],
				'utr_wiki' => $row['wiki'],
			);
		}

		$this->db->insert(
			'users_to_rename',
			$rows,
			__METHOD__,
			array( 'IGNORE' )
		);
	}
}